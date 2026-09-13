<?php
session_start();
require_once 'config.php';

session_timeout_check();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Please login to access your dashboard."));
    exit;
}

$userRentals    = [];
$adminRentals   = [];
$inquiries      = [];
$inquiryThreads = [];
$notifications  = [];
$brandStats     = [];
$dbError        = '';

$totalFleet       = 0;
$totalRented      = 0;
$availableCars    = 0;
$totalIncome      = 0;
$pendingApprovals = 0;
$cancelRequests   = 0;

$role     = $_SESSION['role'] ?? 'customer';
$username = $_SESSION['username'] ?? '';
$email    = $_SESSION['email'] ?? '';
$userId   = (int) $_SESSION['user_id'];


function buildBrandStats(array $vehicles): array
{
    $stats = [];
    $seen  = [];

    foreach ($vehicles as $v) {
        $name = $v['vehicle_name'];
        if (isset($seen[$name])) continue;
        $seen[$name] = true;

        $total     = (int) $v['total_units'];
        $available = (int) $v['available_units'];
        $booked    = max(0, $total - $available);

        $stats[] = [
            'id'              => (int) $v['id'],
            'vehicle_name'    => $name,
            'brand'           => $v['vehicle_brand'] ?? 'Other',
            'total_units'     => $total,
            'available_today' => $available,
            'booked_today'    => $booked
        ];
    }

    return $stats;
}

function statusBadgeClass(string $status): string
{
    switch ($status) {
        case 'Approved':          return 'dash-badge-approved';
        case 'Picked Up':         return 'dash-badge-pickedup';
        case 'Returned':          return 'dash-badge-returned';
        case 'Cancel Requested':  return 'dash-badge-cancelreq';
        case 'Cancelled':         return 'dash-badge-cancelled';
        case 'Rejected':          return 'dash-badge-rejected';
        case 'Pending':
        default:                  return 'dash-badge-pending';
    }
}


try {

    $pdo = getConnection();
    syncAllVehicleStock($pdo);

    /* Notifications for current user */
    try {
        if ($role === 'admin') {
            $nStmt = $pdo->query("
                SELECT *
                FROM notifications
                WHERE recipient_role = 'admin'
                  AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 20
            ");
        } else {
            $nStmt = $pdo->prepare("
                SELECT *
                FROM notifications
                WHERE recipient_role = 'customer'
                  AND recipient_id = :uid
                  AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $nStmt->execute([':uid' => $userId]);
        }

        $notifications = $nStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $notifications = [];
    }

    $vehicleRows = $pdo->query("
        SELECT id, vehicle_name, vehicle_brand, price_per_day, total_units, available_units
        FROM vehicles
        WHERE id IN (SELECT MIN(id) FROM vehicles GROUP BY vehicle_name)
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $brandStats = buildBrandStats($vehicleRows);

    foreach ($brandStats as $b) {
        $totalFleet    += (int)$b['total_units'];
        $availableCars += (int)$b['available_today'];
    }


    if ($role === 'admin') {

        $adminRentals = $pdo->query("
            SELECT
                r.*,
                COALESCE(u.username, r.customer_name) AS username,
                v.vehicle_name,
                v.price_per_day
            FROM rentals r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            ORDER BY r.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($adminRentals as $r) {
            $s = $r['status'] ?? 'Pending';

            if ($s === 'Pending') {
                $pendingApprovals++;
            } elseif ($s === 'Cancel Requested') {
                $cancelRequests++;
            }

            if (in_array($s, ['Approved', 'Picked Up', 'Returned'], true)) {
                $totalRented++;
                $totalIncome += (float)($r['total_fee'] ?? 0);
            }
        }

        try {
            $inquiries = $pdo->query("
                SELECT * FROM inquiries ORDER BY created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($inquiries)) {
                $threadStmt = $pdo->query("
                    SELECT id, inquiry_id, sender_role, sender_name, message, created_at
                    FROM inquiry_messages
                    ORDER BY created_at ASC
                ");
                foreach ($threadStmt->fetchAll(PDO::FETCH_ASSOC) as $msg) {
                    $inquiryThreads[(int)$msg['inquiry_id']][] = $msg;
                }
            }
        } catch (PDOException $e) {
            $inquiries = [];
            $inquiryThreads = [];
        }

    } else {

        $stmt = $pdo->prepare("
            SELECT
                r.*,
                v.vehicle_name
            FROM rentals r
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            WHERE r.user_id = :uid
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        $userRentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $inqStmt = $pdo->prepare("
            SELECT * FROM inquiries WHERE user_id = :uid ORDER BY created_at DESC
        ");
        $inqStmt->execute([':uid' => $userId]);
        $inquiries = $inqStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($inquiries)) {
            $ids = array_column($inquiries, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $threadStmt = $pdo->prepare("
                SELECT id, inquiry_id, sender_role, sender_name, message, created_at
                FROM inquiry_messages
                WHERE inquiry_id IN ($ph)
                ORDER BY created_at ASC
            ");
            $threadStmt->execute($ids);
            foreach ($threadStmt->fetchAll(PDO::FETCH_ASSOC) as $msg) {
                $inquiryThreads[(int)$msg['inquiry_id']][] = $msg;
            }
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $dbError = "Database error: " . $e->getMessage();
}

$custTotalBookings = count($userRentals);
$custApproved      = 0;
$custPending       = 0;
$custRejected      = 0;
$custSpent         = 0;

foreach ($userRentals as $r) {
    $s = $r['status'] ?? 'Pending';
    if (in_array($s, ['Approved', 'Picked Up', 'Returned'], true)) {
        $custApproved++;
        $custSpent += (float)($r['total_fee'] ?? 0);
    } elseif ($s === 'Pending' || $s === 'Cancel Requested') {
        $custPending++;
    } elseif (in_array($s, ['Rejected', 'Cancelled'], true)) {
        $custRejected++;
    }
}

$hasApprovedBooking = false;
foreach ($userRentals as $r) {
    if (($r['status'] ?? '') === 'Approved') {
        $hasApprovedBooking = true;
        break;
    }
}

$unreadCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RC Drive</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="dash-wrapper">

    <header class="dash-header">
        <div class="dash-logo">
            <img src="images/Asset 1.png" alt="RC Drive Logo">
        </div>

        <div class="dash-header-actions">
            <span class="dash-role-badge <?= $role === 'admin' ? 'dash-role-admin' : 'dash-role-customer' ?>">
                <?= $role === 'admin' ? 'ADMINISTRATOR' : 'CUSTOMER' ?>
            </span>

            <?php if ($role === 'admin'): ?>
                <a href="dashboard.php" class="dash-btn dash-btn-outline">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </a>
            <?php else: ?>
                <a href="index.php" class="dash-btn dash-btn-outline">
                    <i class="fa-solid fa-house"></i> Home
                </a>
            <?php endif; ?>

            <a href="logout.php" class="dash-btn dash-btn-danger">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </header>

    <?php if (isset($_GET['message'])): ?>
        <div class="dash-alert <?= ($_GET['status'] ?? '') === 'success' ? 'dash-alert-success' : 'dash-alert-error' ?>">
            <i class="fa-solid <?= ($_GET['status'] ?? '') === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= htmlspecialchars($_GET['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($dbError): ?>
        <div class="dash-alert dash-alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($dbError) ?>
        </div>
    <?php endif; ?>

    <!-- NOTIFICATIONS -->
    <?php if ($unreadCount > 0): ?>

        <div class="dash-notif-banner">

            <div class="dash-notif-head">
                <i class="fa-solid fa-bell"></i>
                <strong>You have <?= $unreadCount ?> new notification<?= $unreadCount === 1 ? '' : 's' ?></strong>
                <a href="mark_notifications_read.php" class="dash-notif-clear">
                    <?= $role === 'admin' ? 'Mark all as read' : 'Dismiss' ?>
                </a>
            </div>

            <ul class="dash-notif-list">
                <?php foreach (array_slice($notifications, 0, 5) as $n): ?>
                    <li class="dash-notif-item">
                        <div class="dash-notif-title">
                            <i class="fa-solid fa-circle-dot"></i>
                            <?= htmlspecialchars($n['title']) ?>
                        </div>
                        <div class="dash-notif-msg">
                            <?= nl2br(htmlspecialchars($n['message'] ?? '')) ?>
                        </div>
                        <div class="dash-notif-time">
                            <?= htmlspecialchars($n['created_at']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>

    <!-- PICKUP REMINDER FOR CUSTOMER -->
    <?php if ($role === 'customer' && $hasApprovedBooking): ?>

        <div class="pickup-info-banner">

            <h4>
                <i class="fa-solid fa-circle-check"></i>
                Your booking is ready for pickup
            </h4>

            <p>
                Please come to our shop to collect your vehicle. Bring your
                <strong style="color:#FCC113;">driver's license</strong> and a
                <strong style="color:#FCC113;">valid government ID</strong>.
            </p>

            <div class="pickup-info-grid">

                <div>
                    <div class="info-label">Our Location</div>
                    <div class="info-value">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= htmlspecialchars(COMPANY_NAME) ?><br>
                        <?= htmlspecialchars(COMPANY_ADDRESS) ?>
                    </div>
                </div>

                <div>
                    <div class="info-label">Operating Hours</div>
                    <div class="info-value">
                        <i class="fa-solid fa-clock"></i>
                        <?= htmlspecialchars(COMPANY_HOURS) ?>
                    </div>
                </div>

                <div>
                    <div class="info-label">Contact</div>
                    <div class="info-value">
                        <i class="fa-solid fa-phone"></i>
                        <?= htmlspecialchars(COMPANY_PHONE) ?><br>
                        <i class="fa-solid fa-envelope"></i>
                        <?= htmlspecialchars(COMPANY_EMAIL) ?>
                    </div>
                </div>

            </div>

            <p style="color:#a0a5b1; font-size:0.8rem; margin-top:16px; margin-bottom:0;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#FCC113;"></i>
                If you do not claim your vehicle within 4 hours of your pickup date, your reservation may be released.
            </p>

        </div>

    <?php endif; ?>

    <div class="dash-page-title">
        <h2>Welcome back, <span><?= htmlspecialchars($username) ?></span></h2>
        <p>
            <?php if ($role === 'admin'): ?>
                Manage bookings, approve rentals, reply to inquiries, and monitor fleet availability.
            <?php else: ?>
                Track your reservations, request cancellations, and manage your account.
            <?php endif; ?>
        </p>
    </div>

    <!-- VEHICLE AVAILABILITY -->
    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-warehouse"></i> Vehicle Availability by Brand</h3>
            <span class="title-note">Live stock</span>
        </div>

        <div class="brand-grid">
            <?php foreach ($brandStats as $brand): ?>
                <div class="brand-card">
                    <div class="brand-name"><?= htmlspecialchars($brand['vehicle_name']) ?></div>
                    <span class="brand-sub">Brand: <?= htmlspecialchars($brand['brand']) ?></span>

                    <div class="brand-row">
                        <span>Total Units</span>
                        <span><?= (int)$brand['total_units'] ?></span>
                    </div>
                    <div class="brand-row">
                        <span>Available</span>
                        <span class="available"><?= (int)$brand['available_today'] ?></span>
                    </div>
                    <div class="brand-row">
                        <span>Booked</span>
                        <span class="rented"><?= (int)$brand['booked_today'] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php if ($role === 'admin'): ?>

    <div class="dash-stats">
        <div class="dash-stat-card">
            <span class="stat-label">Available Cars</span>
            <div class="stat-value"><?= $availableCars ?></div>
            <span class="stat-sub">Out of <?= $totalFleet ?> fleet</span>
        </div>
        <div class="dash-stat-card blue">
            <span class="stat-label">Cars Rented</span>
            <div class="stat-value"><?= $totalRented ?></div>
            <span class="stat-sub">Active + returned</span>
        </div>
        <div class="dash-stat-card green">
            <span class="stat-label">Total Income</span>
            <div class="stat-value">₱<?= number_format($totalIncome, 0) ?></div>
            <span class="stat-sub">From approved bookings</span>
        </div>
        <div class="dash-stat-card red">
            <span class="stat-label">Pending Approvals</span>
            <div class="stat-value"><?= $pendingApprovals ?></div>
            <span class="stat-sub">Awaiting action</span>
        </div>
    </div>

    <?php if ($cancelRequests > 0): ?>
        <div class="dash-alert dash-alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= $cancelRequests ?> cancellation request<?= $cancelRequests === 1 ? '' : 's' ?> awaiting your decision.
        </div>
    <?php endif; ?>

    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-car-side"></i> Customer Rentals & Approvals</h3>
            <span class="title-note"><?= count($adminRentals) ?> total booking(s)</span>
        </div>

        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Pickup</th>
                        <th>Return</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($adminRentals)): ?>
                    <tr class="empty-row">
                        <td colspan="9">No rental bookings registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($adminRentals as $rental): ?>
                        <?php
                        $status     = $rental['status'] ?? 'Pending';
                        $badgeClass = statusBadgeClass($status);
                        $reason     = '';
                        if ($status === 'Cancel Requested') {
                            $reason = $rental['cancel_reason'] ?? '';
                            if ($reason === 'Other' && !empty($rental['cancel_reason_other'])) {
                                $reason = 'Other: ' . $rental['cancel_reason_other'];
                            }
                        }
                        ?>
                        <tr>
                            <td class="dash-vehicle-name">
                                <?= htmlspecialchars($rental['username'] ?? $rental['customer_name'] ?? 'Customer') ?>
                            </td>
                            <td><?= htmlspecialchars($rental['phone_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($rental['vehicle_type'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($rental['rental_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($rental['return_date'] ?? '') ?></td>
                            <td class="dash-money">
                                ₱<?= number_format((float)($rental['total_fee'] ?? 0), 2) ?>
                            </td>
                            <td>
                                <span class="dash-badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                <?php if (!empty($rental['late_days']) && (int)$rental['late_days'] > 0): ?>
                                    <div style="font-size:0.72rem; color:#ff5560; margin-top:4px;">
                                        <i class="fa-solid fa-clock"></i>
                                        <?= (int)$rental['late_days'] ?> day(s) late
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $reason ? htmlspecialchars($reason) : '—' ?>
                            </td>
                            <td>
                                <?php if ($status === 'Pending'): ?>

                                    <a href="admin_action.php?action=approve&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-approve" title="Approve">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
                                    <a href="admin_action.php?action=reject&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-reject" title="Reject">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                    <a href="admin_action.php?action=force_cancel&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-warn"
                                       title="Cancel (admin override)"
                                       onclick="return confirm('Cancel this booking as admin?');">
                                        <i class="fa-solid fa-ban"></i>
                                    </a>

                                <?php elseif ($status === 'Approved'): ?>

                                    <a href="admin_action.php?action=pickup&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-info" title="Mark Picked Up">
                                        <i class="fa-solid fa-key"></i>
                                    </a>
                                    <a href="admin_action.php?action=force_cancel&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-warn"
                                       title="Cancel (admin override)"
                                       onclick="return confirm('Cancel this booking as admin?');">
                                        <i class="fa-solid fa-ban"></i>
                                    </a>

                                <?php elseif ($status === 'Picked Up'): ?>

                                    <button type="button"
                                            class="dash-action-btn dash-action-approve"
                                            title="Mark Returned"
                                            onclick="openReturnModal(
                                                <?= (int)$rental['id'] ?>,
                                                '<?= htmlspecialchars($rental['vehicle_type'] ?? '', ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($rental['return_date'] ?? '', ENT_QUOTES) ?>',
                                                <?= (float)($rental['total_fee'] ?? 0) ?>
                                            )">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <a href="admin_action.php?action=force_cancel&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-warn"
                                       title="Cancel (admin override)"
                                       onclick="return confirm('Cancel this booking as admin? The vehicle has already been picked up.');">
                                        <i class="fa-solid fa-ban"></i>
                                    </a>

                                <?php elseif ($status === 'Cancel Requested'): ?>

                                    <a href="admin_action.php?action=approve_cancel&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-approve" title="Approve Cancellation">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
                                    <a href="admin_action.php?action=reject_cancel&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-reject" title="Reject Cancellation">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>

                                <?php else: ?>

                                    <?php if (!empty($rental['late_fee']) && (float)$rental['late_fee'] > 0): ?>
                                        <span class="dash-action-processed">
                                            Late fee: ₱<?= number_format((float)$rental['late_fee'], 0) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="dash-action-processed">Processed</span>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-comments"></i> Client Conversations</h3>
            <span class="title-note"><?= count($inquiries) ?> conversation(s)</span>
        </div>

        <?php if (empty($inquiries)): ?>
            <div class="dash-empty">
                <i class="fa-solid fa-envelope-open"></i>
                <p>No inquiries yet.</p>
            </div>
        <?php else: ?>
            <div class="conv-list">
                <?php foreach ($inquiries as $inq): ?>
                    <?php
                    $inqId   = (int)$inq['id'];
                    $thread  = $inquiryThreads[$inqId] ?? [];
                    $preview = '';
                    if (!empty($thread)) {
                        $lastMsg = end($thread);
                        $preview = $lastMsg['sender_name'] . ': ' . $lastMsg['message'];
                    } else {
                        $preview = $inq['message'] ?? '';
                    }
                    ?>
                    <div class="conv-item" id="inquiry-<?= $inqId ?>">
                        <div class="conv-head" onclick="toggleConv(<?= $inqId ?>)">
                            <div class="conv-head-info">
                                <strong>
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($inq['name'] ?? 'Client') ?>
                                    <span style="color:#8a8f9d; font-weight:600; font-size:0.8rem; margin-left:6px;">
                                        (<?= htmlspecialchars($inq['email'] ?? 'N/A') ?>)
                                    </span>
                                </strong>
                                <small><?= htmlspecialchars($inq['created_at'] ?? '') ?></small>
                                <div class="conv-preview">
                                    <?= htmlspecialchars(mb_substr($preview, 0, 120)) ?>
                                    <?= mb_strlen($preview) > 120 ? '…' : '' ?>
                                </div>
                            </div>
                            <div class="conv-head-meta">
                                <span class="dash-badge dash-badge-pending"><?= count($thread) ?> message(s)</span>
                                <button type="button" class="conv-toggle" data-target="body-<?= $inqId ?>">
                                    VIEW CONVERSATION
                                </button>
                            </div>
                        </div>
                        <div class="conv-body" id="body-<?= $inqId ?>">
                            <div class="conv-thread">
                                <?php if (empty($thread)): ?>
                                    <div class="conv-empty-thread">No messages in this thread yet.</div>
                                <?php else: ?>
                                    <?php foreach ($thread as $msg): ?>
                                        <?php $bubbleClass = $msg['sender_role'] === 'admin' ? 'admin' : 'customer'; ?>
                                        <div class="conv-bubble <?= $bubbleClass ?>">
                                            <span class="bubble-meta">
                                                <?= htmlspecialchars($msg['sender_name']) ?> ·
                                                <?= htmlspecialchars($msg['created_at']) ?>
                                            </span>
                                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <form class="conv-reply-form" action="inquiry_reply.php" method="POST">
                                <input type="hidden" name="inquiry_id" value="<?= $inqId ?>">
                                <textarea name="message" placeholder="Type your reply..." required></textarea>
                                <button type="submit" class="conv-send-btn">
                                    <i class="fa-solid fa-paper-plane"></i> SEND
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="dash-stats">
        <div class="dash-stat-card">
            <span class="stat-label">Total Bookings</span>
            <div class="stat-value"><?= $custTotalBookings ?></div>
            <span class="stat-sub">All time</span>
        </div>
        <div class="dash-stat-card green">
            <span class="stat-label">Approved</span>
            <div class="stat-value"><?= $custApproved ?></div>
            <span class="stat-sub">Confirmed rentals</span>
        </div>
        <div class="dash-stat-card">
            <span class="stat-label">Pending</span>
            <div class="stat-value"><?= $custPending ?></div>
            <span class="stat-sub">Awaiting approval</span>
        </div>
        <div class="dash-stat-card blue">
            <span class="stat-label">Total Spent</span>
            <div class="stat-value">₱<?= number_format($custSpent, 0) ?></div>
            <span class="stat-sub">On approved bookings</span>
        </div>
    </div>

    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-user"></i> Account Information</h3>
        </div>

        <div class="dash-table-wrap">
            <table class="dash-table">
                <tbody>
                    <tr>
                        <td style="width:180px; color:#FCC113; font-weight:800;">Username</td>
                        <td><?= htmlspecialchars($username) ?></td>
                    </tr>
                    <tr>
                        <td style="color:#FCC113; font-weight:800;">Email</td>
                        <td><?= htmlspecialchars($email ?: 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td style="color:#FCC113; font-weight:800;">Role</td>
                        <td>Customer</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Rental History & Reservations</h3>
            <span class="title-note"><?= count($userRentals) ?> booking(s)</span>
        </div>

        <?php if (empty($userRentals)): ?>
            <div class="dash-empty">
                <i class="fa-solid fa-car"></i>
                <p>You have no booking requests yet. Pick a car from the fleet to get started.</p>
                <a href="index.php#fleet" class="dash-btn dash-btn-yellow">
                    <i class="fa-solid fa-car"></i> Browse Fleet
                </a>
            </div>
        <?php else: ?>
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Pickup</th>
                            <th>Return</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($userRentals as $rental): ?>
                        <?php
                        $status     = $rental['status'] ?? 'Pending';
                        $badgeClass = statusBadgeClass($status);

                        $createdTs    = isset($rental['created_at']) ? strtotime($rental['created_at']) : 0;
                        $hoursSince   = $createdTs > 0 ? (time() - $createdTs) / 3600 : 9999;
                        $withinWindow = $hoursSince <= CANCEL_WINDOW_HOURS;

                        $canSelfCancel = ($status === 'Pending' && $withinWindow);
                        $showButton    = in_array($status, ['Pending', 'Approved'], true);

                        $reason = '';
                        if ($status === 'Cancel Requested') {
                            $reason = $rental['cancel_reason'] ?? '';
                            if ($reason === 'Other' && !empty($rental['cancel_reason_other'])) {
                                $reason = 'Other: ' . $rental['cancel_reason_other'];
                            }
                        }
                        ?>
                        <tr>
                            <td class="dash-vehicle-name">
                                <?= htmlspecialchars($rental['vehicle_name'] ?? $rental['vehicle_type'] ?? 'Unknown Vehicle') ?>
                            </td>
                            <td><?= htmlspecialchars($rental['rental_date']) ?></td>
                            <td><?= htmlspecialchars($rental['return_date']) ?></td>
                            <td><?= htmlspecialchars($rental['payment_method'] ?? 'N/A') ?></td>
                            <td class="dash-money">
                                ₱<?= number_format((float)$rental['total_fee'], 2) ?>

                                <?php if (!empty($rental['late_fee']) && (float)$rental['late_fee'] > 0): ?>
                                    <div style="font-size:0.72rem; color:#ff5560; margin-top:4px; font-family:'Segoe UI',sans-serif; font-weight:600;">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Incl. late fee ₱<?= number_format((float)$rental['late_fee'], 0) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="dash-badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>

                                <?php if (!empty($rental['late_days']) && (int)$rental['late_days'] > 0): ?>
                                    <div style="font-size:0.72rem; color:#ff5560; margin-top:4px;">
                                        <i class="fa-solid fa-clock"></i>
                                        <?= (int)$rental['late_days'] ?> day(s) late
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $reason ? htmlspecialchars($reason) : '—' ?>
                            </td>
                            <td>
                                <?php if ($canSelfCancel): ?>

                                    <button
                                        type="button"
                                        class="dash-action-btn dash-action-reject"
                                        title="Request cancellation"
                                        onclick="openCancelModal(
                                            <?= (int)$rental['id'] ?>,
                                            '<?= htmlspecialchars($rental['vehicle_type'] ?? '', ENT_QUOTES) ?>'
                                        )"
                                    >
                                        <i class="fa-solid fa-ban"></i>
                                    </button>

                                <?php elseif ($showButton): ?>

                                    <button
                                        type="button"
                                        class="dash-action-btn dash-action-approve"
                                        title="View booking instructions"
                                        onclick="showApprovedNotice(
                                            '<?= htmlspecialchars($rental['vehicle_type'] ?? 'this vehicle', ENT_QUOTES) ?>'
                                        )"
                                    >
                                        <i class="fa-solid fa-circle-info"></i>
                                    </button>

                                <?php elseif ($status === 'Cancel Requested'): ?>

                                    <span class="dash-action-processed">Waiting for admin</span>

                                <?php else: ?>

                                    <span class="dash-action-processed">—</span>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-comments"></i> My Conversations with RC Drive</h3>
            <span class="title-note"><?= count($inquiries) ?> conversation(s)</span>
        </div>

        <?php if (empty($inquiries)): ?>
            <div class="dash-empty">
                <i class="fa-solid fa-envelope-open"></i>
                <p>You haven't sent any inquiries yet.</p>
                <a href="index.php#contact" class="dash-btn dash-btn-yellow">
                    <i class="fa-solid fa-envelope"></i> Contact Us
                </a>
            </div>
        <?php else: ?>
            <div class="conv-list">
                <?php foreach ($inquiries as $inq): ?>
                    <?php
                    $inqId   = (int)$inq['id'];
                    $thread  = $inquiryThreads[$inqId] ?? [];
                    $preview = '';
                    if (!empty($thread)) {
                        $lastMsg = end($thread);
                        $preview = $lastMsg['sender_name'] . ': ' . $lastMsg['message'];
                    } else {
                        $preview = $inq['message'] ?? '';
                    }
                    ?>
                    <div class="conv-item" id="inquiry-<?= $inqId ?>">
                        <div class="conv-head" onclick="toggleConv(<?= $inqId ?>)">
                            <div class="conv-head-info">
                                <strong><i class="fa-solid fa-headset me-1"></i> RC Drive Support</strong>
                                <small>Started <?= htmlspecialchars($inq['created_at'] ?? '') ?></small>
                                <div class="conv-preview">
                                    <?= htmlspecialchars(mb_substr($preview, 0, 120)) ?>
                                    <?= mb_strlen($preview) > 120 ? '…' : '' ?>
                                </div>
                            </div>
                            <div class="conv-head-meta">
                                <span class="dash-badge dash-badge-pending"><?= count($thread) ?> message(s)</span>
                                <button type="button" class="conv-toggle" data-target="body-<?= $inqId ?>">
                                    VIEW CONVERSATION
                                </button>
                            </div>
                        </div>
                        <div class="conv-body" id="body-<?= $inqId ?>">
                            <div class="conv-thread">
                                <?php if (empty($thread)): ?>
                                    <div class="conv-empty-thread">No messages in this thread yet.</div>
                                <?php else: ?>
                                    <?php foreach ($thread as $msg): ?>
                                        <?php $bubbleClass = $msg['sender_role'] === 'admin' ? 'admin' : 'customer'; ?>
                                        <div class="conv-bubble <?= $bubbleClass ?>">
                                            <span class="bubble-meta">
                                                <?= htmlspecialchars($msg['sender_name']) ?> ·
                                                <?= htmlspecialchars($msg['created_at']) ?>
                                            </span>
                                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <form class="conv-reply-form" action="inquiry_reply.php" method="POST">
                                <input type="hidden" name="inquiry_id" value="<?= $inqId ?>">
                                <textarea name="message" placeholder="Type your reply..." required></textarea>
                                <button type="submit" class="conv-send-btn">
                                    <i class="fa-solid fa-paper-plane"></i> SEND
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dash-section">
        <div class="dash-section-title">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</h3>
        </div>
        <p style="color:#a0a5b1; font-size:0.9rem; margin-bottom:18px;">
            Permanently delete your account and all associated bookings. This action cannot be undone.
        </p>
        <a href="delete_account.php"
           class="dash-btn dash-btn-danger"
           onclick="return confirm('Are you sure you want to permanently delete your account? This cannot be undone.');">
            <i class="fa-solid fa-trash"></i> Delete My Account
        </a>
    </div>

<?php endif; ?>

</div>

<footer class="dash-footer">
    &copy; 2026 <?= htmlspecialchars(COMPANY_NAME) ?>. All rights reserved.
</footer>


<!-- ============================================================
     RETURN VEHICLE MODAL
     ============================================================ -->
<div class="modal fade" id="returnVehicleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-warning">

            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning">
                    <i class="fa-solid fa-rotate-left"></i> Record Vehicle Return
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form action="return_vehicle.php" method="POST">
                <input type="hidden" name="id" id="returnRentalId">

                <div class="modal-body">

                    <div style="background:rgba(252,193,19,0.08); border-left:4px solid #FCC113; border-radius:4px; padding:14px 16px; margin-bottom:18px;">
                        <div style="font-family:'Montserrat',sans-serif; font-size:0.7rem; font-weight:800; color:#FCC113; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">
                            Vehicle
                        </div>
                        <div id="returnVehicleName" style="font-weight:800; color:#fff; margin-bottom:8px;">
                            —
                        </div>
                        <div style="font-size:0.82rem; color:#a0a5b1; line-height:1.5;">
                            Agreed return date:
                            <strong id="returnAgreedDate" style="color:#FCC113;">—</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Actual Return Date <span style="color:#ff5560;">*</span>
                        </label>
                        <input type="date"
                               name="actual_return_date"
                               id="actualReturnDate"
                               class="form-control bg-secondary text-white border-0"
                               required>
                    </div>

                    <div id="lateFeePreview" style="display:none; background:rgba(220,53,69,0.1); border-left:4px solid #dc3545; border-radius:4px; padding:14px 16px;">
                        <div style="font-family:'Montserrat',sans-serif; font-size:0.7rem; font-weight:800; color:#ff5560; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">
                            Late Return Penalty
                        </div>
                        <div style="font-size:0.88rem; color:#ff8590; line-height:1.7;">
                            <div>Late by <strong id="previewLateDays" style="color:#fff;">0</strong> day(s)</div>
                            <div style="margin-top:8px;">
                                Late fee: <strong id="previewLateFee" style="color:#fff;">₱0.00</strong>
                                <span style="color:#8a8f9d; font-size:0.78rem;">(₱1,000/day)</span>
                            </div>
                            <div>
                                New total: <strong id="previewNewTotal" style="color:#fff;">₱0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div id="onTimeMessage" style="display:none; background:rgba(40,167,69,0.1); border-left:4px solid #28a745; border-radius:4px; padding:14px 16px;">
                        <div style="font-size:0.88rem; color:#7eec9a; line-height:1.6;">
                            <i class="fa-solid fa-circle-check"></i>
                            Returned on time. No late fee.
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="dash-btn dash-btn-outline" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="dash-btn dash-btn-yellow">
                        <i class="fa-solid fa-check"></i> Confirm Return
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>


<!-- ============================================================
     CANCEL REQUEST MODAL
     ============================================================ -->
<div class="modal fade" id="cancelBookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-danger">

            <div class="modal-header border-secondary">
                <h5 class="modal-title text-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i> Request Cancellation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form action="customer_cancel_booking.php" method="POST">
                <input type="hidden" name="id" id="cancelId">

                <div class="modal-body">

                    <div class="cancel-policy-box">
                        <div style="font-family:Montserrat; font-size:0.8rem; letter-spacing:1px; text-transform:uppercase; margin-bottom:6px;">
                            Vehicle
                        </div>
                        <div id="cancelVehicleName"><strong>—</strong></div>
                        <div style="margin-top:8px; color:#a0a5b1; font-size:0.82rem;">
                            Your request will be reviewed by RC Drive staff.
                            Your booking stays active until they approve it.
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Reason for cancellation <span style="color:#ff5560;">*</span></label>
                        <select name="reason" id="cancelReason" class="form-select bg-secondary text-white border-0" required>
                            <option value="" selected disabled>Select a reason...</option>
                            <option value="Change of date">Change of date</option>
                            <option value="Change of plans">Change of plans</option>
                            <option value="No longer needed">No longer needed</option>
                            <option value="Found another vehicle">Found another vehicle</option>
                            <option value="Emergency">Emergency</option>
                            <option value="Other">Other (please specify)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="otherReasonWrap" style="display:none;">
                        <label class="form-label">Please specify</label>
                        <textarea name="reason_other" id="reasonOther"
                                  class="form-control bg-secondary text-white border-0"
                                  rows="3"
                                  placeholder="Tell us why you're cancelling..."></textarea>
                    </div>

                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="dash-btn dash-btn-outline" data-bs-dismiss="modal">
                        Keep Booking
                    </button>
                    <button type="submit" class="dash-btn dash-btn-danger">
                        <i class="fa-solid fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>


<!-- ============================================================
     APPROVED NOTICE MODAL
     ============================================================ -->
<div class="modal fade" id="approvedNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white" style="border:2px solid #28a745; border-radius:6px;">

            <div class="modal-header" style="border-bottom:1px solid #222730;">
                <h5 class="modal-title" style="color:#28a745; font-family:'Montserrat',sans-serif; font-weight:900;">
                    <i class="fa-solid fa-circle-check"></i> Reservation Approved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div style="background:rgba(40,167,69,0.1); border-left:4px solid #28a745; border-radius:4px; padding:16px 18px; margin-bottom:18px;">
                    <div style="font-family:'Montserrat',sans-serif; font-size:0.8rem; font-weight:900; color:#28a745; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
                        Your booking is confirmed
                    </div>
                    <div style="font-size:0.9rem; color:#d4d8dd; line-height:1.6;">
                        Your reservation for
                        <strong id="approvedVehicleName" style="color:#FCC113;">this vehicle</strong>
                        has been approved by our team. Please contact the admin to confirm your pickup schedule.
                    </div>
                </div>

                <p style="color:#a0a5b1; font-size:0.88rem; margin-bottom:16px;">
                    Reach us through any of the following:
                </p>

                <div style="display:grid; gap:12px; margin-bottom:18px;">

                    <div style="background:#12161c; border:1px solid #222730; border-radius:4px; padding:14px 16px; display:flex; align-items:center; gap:14px;">
                        <div style="flex:0 0 42px; width:42px; height:42px; background:rgba(252,193,19,0.1); border-radius:4px; display:flex; align-items:center; justify-content:center; color:#FCC113; font-size:1.1rem;">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div>
                            <div style="font-family:'Montserrat',sans-serif; font-size:0.7rem; font-weight:800; color:#8a8f9d; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">
                                Phone
                            </div>
                            <a href="tel:<?= htmlspecialchars(COMPANY_PHONE) ?>" style="color:#FCC113; font-weight:800; text-decoration:none; font-size:0.95rem;">
                                <?= htmlspecialchars(COMPANY_PHONE) ?>
                            </a>
                        </div>
                    </div>

                    <div style="background:#12161c; border:1px solid #222730; border-radius:4px; padding:14px 16px; display:flex; align-items:center; gap:14px;">
                        <div style="flex:0 0 42px; width:42px; height:42px; background:rgba(252,193,19,0.1); border-radius:4px; display:flex; align-items:center; justify-content:center; color:#FCC113; font-size:1.1rem;">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <div style="font-family:'Montserrat',sans-serif; font-size:0.7rem; font-weight:800; color:#8a8f9d; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">
                                Email
                            </div>
                            <a href="mailto:<?= htmlspecialchars(COMPANY_EMAIL) ?>" style="color:#FCC113; font-weight:800; text-decoration:none; font-size:0.95rem;">
                                <?= htmlspecialchars(COMPANY_EMAIL) ?>
                            </a>
                        </div>
                    </div>

                    <div style="background:#12161c; border:1px solid #222730; border-radius:4px; padding:14px 16px; display:flex; align-items:center; gap:14px;">
                        <div style="flex:0 0 42px; width:42px; height:42px; background:rgba(252,193,19,0.1); border-radius:4px; display:flex; align-items:center; justify-content:center; color:#FCC113; font-size:1.1rem;">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <div>
                            <div style="font-family:'Montserrat',sans-serif; font-size:0.7rem; font-weight:800; color:#8a8f9d; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">
                                Chat Box
                            </div>
                            <div style="color:#d4d8dd; font-size:0.88rem;">
                                Use the
                                <strong style="color:#FCC113;">"My Conversations with RC Drive"</strong>
                                section below
                            </div>
                        </div>
                    </div>

                </div>

                <p style="color:#a0a5b1; font-size:0.82rem; margin-bottom:0; line-height:1.6;">
                    <i class="fa-solid fa-location-dot" style="color:#FCC113;"></i>
                    Pickup at: <strong style="color:#fff;"><?= htmlspecialchars(COMPANY_ADDRESS) ?></strong><br>
                    <i class="fa-solid fa-clock" style="color:#FCC113;"></i>
                    <?= htmlspecialchars(COMPANY_HOURS) ?>
                </p>

            </div>

            <div class="modal-footer" style="border-top:1px solid #222730;">
                <button type="button" class="dash-btn dash-btn-outline" data-bs-dismiss="modal">
                    Close
                </button>
                <button type="button" class="dash-btn dash-btn-yellow" id="scrollToChatBtn">
                    <i class="fa-solid fa-comments"></i> Go to Chat Box
                </button>
            </div>

        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function toggleConv(id) {
        var body = document.getElementById('body-' + id);
        if (!body) return;
        body.classList.toggle('open');

        if (body.classList.contains('open')) {
            var thread = body.querySelector('.conv-thread');
            if (thread) {
                setTimeout(function () { thread.scrollTop = thread.scrollHeight; }, 50);
            }
        }
    }

    function openCancelModal(bookingId, vehicleName) {
        var modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById('cancelBookingModal')
        );

        document.getElementById('cancelId').value = bookingId;
        document.getElementById('cancelVehicleName').innerHTML = '<strong>' + vehicleName + '</strong>';
        document.getElementById('cancelReason').value = '';
        document.getElementById('otherReasonWrap').style.display = 'none';
        document.getElementById('reasonOther').value = '';

        modal.show();
    }

    function showApprovedNotice(vehicleName) {
        var modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById('approvedNoticeModal')
        );

        document.getElementById('approvedVehicleName').textContent = vehicleName;

        modal.show();
    }

    function openReturnModal(rentalId, vehicleName, agreedDate, currentTotal) {
        var modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById('returnVehicleModal')
        );

        document.getElementById('returnRentalId').value = rentalId;
        document.getElementById('returnVehicleName').textContent = vehicleName;
        document.getElementById('returnAgreedDate').textContent = agreedDate;

        var today = new Date().toISOString().split('T')[0];
        var actualInput = document.getElementById('actualReturnDate');
        actualInput.value = today;
        actualInput.min = agreedDate;

        actualInput._agreedDate   = agreedDate;
        actualInput._currentTotal = parseFloat(currentTotal) || 0;

        document.getElementById('lateFeePreview').style.display = 'none';
        document.getElementById('onTimeMessage').style.display = 'none';

        updateLateFeePreview();
        modal.show();
    }

    function updateLateFeePreview() {
        var input  = document.getElementById('actualReturnDate');
        var agreed = input._agreedDate;
        var total  = input._currentTotal || 0;
        var actual = input.value;

        if (!agreed || !actual) return;

        var agreedDate = new Date(agreed + 'T00:00:00');
        var actualDate = new Date(actual + 'T00:00:00');

        var daysLate = 0;
        if (actualDate > agreedDate) {
            daysLate = Math.round((actualDate - agreedDate) / (1000 * 60 * 60 * 24));
        }

        if (daysLate > 0) {
            var lateFee = daysLate * 1000;
            document.getElementById('previewLateDays').textContent = daysLate;
            document.getElementById('previewLateFee').textContent =
                '₱' + lateFee.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            document.getElementById('previewNewTotal').textContent =
                '₱' + (total + lateFee).toLocaleString('en-PH', { minimumFractionDigits: 2 });

            document.getElementById('lateFeePreview').style.display = 'block';
            document.getElementById('onTimeMessage').style.display = 'none';
        } else {
            document.getElementById('lateFeePreview').style.display = 'none';
            document.getElementById('onTimeMessage').style.display = 'block';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {

        var reasonSel = document.getElementById('cancelReason');
        var otherWrap = document.getElementById('otherReasonWrap');

        if (reasonSel) {
            reasonSel.addEventListener('change', function () {
                if (this.value === 'Other') {
                    otherWrap.style.display = 'block';
                } else {
                    otherWrap.style.display = 'none';
                }
            });
        }

        var actualReturn = document.getElementById('actualReturnDate');
        if (actualReturn) {
            actualReturn.addEventListener('change', updateLateFeePreview);
        }

        var scrollBtn = document.getElementById('scrollToChatBtn');
        if (scrollBtn) {
            scrollBtn.addEventListener('click', function () {
                var modalEl = document.getElementById('approvedNoticeModal');
                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                var chatSection = document.querySelector('.conv-list') ||
                                  document.querySelector('[id^="inquiry-"]');

                if (chatSection) {
                    setTimeout(function () {
                        chatSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            });
        }

        if (window.location.hash && window.location.hash.startsWith('#inquiry-')) {
            var id = window.location.hash.replace('#inquiry-', '');
            var body = document.getElementById('body-' + id);
            if (body && !body.classList.contains('open')) {
                toggleConv(id);
                body.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
</script>

</body>
</html>