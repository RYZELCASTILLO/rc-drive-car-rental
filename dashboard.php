<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Please login to access your dashboard."));
    exit;
}

$userRentals    = [];
$adminRentals   = [];
$inquiries      = [];
$inquiryThreads = [];
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
                v.vehicle_name
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

    <style>
        body { background-color: #0b0c0e; color: #ffffff; }

        .dash-wrapper { max-width: 1300px; margin: 0 auto; padding: 40px 6% 80px; }

        .dash-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 0 30px; border-bottom: 1px solid #222730;
            margin-bottom: 40px; flex-wrap: wrap; gap: 20px;
        }
        .dash-logo img { height: 55px; width: auto; display: block; }
        .dash-header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        .dash-role-badge {
            padding: 8px 16px; border-radius: 4px; font-size: 0.75rem;
            font-weight: 900; letter-spacing: 1px; font-family: 'Montserrat', sans-serif;
        }
        .dash-role-admin    { background: #dc3545; color: #fff; }
        .dash-role-customer { background: #FCC113; color: #212529; }

        .dash-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 4px;
            font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 800;
            letter-spacing: 0.5px; text-decoration: none;
            border: 2px solid transparent; cursor: pointer; transition: all 0.25s ease;
        }
        .dash-btn-yellow  { background:#FCC113; color:#212529; border-color:#FCC113; }
        .dash-btn-yellow:hover { background:transparent; color:#FCC113; }
        .dash-btn-outline { background:transparent; color:#fff; border-color:rgba(255,255,255,0.25); }
        .dash-btn-outline:hover { border-color:#FCC113; color:#FCC113; }
        .dash-btn-danger  { background:transparent; color:#ff5560; border-color:#ff5560; }
        .dash-btn-danger:hover { background:#ff5560; color:#fff; }

        .dash-page-title { margin-bottom: 30px; }
        .dash-page-title h2 { font-size: 2rem; font-weight: 900; margin: 0 0 6px; }
        .dash-page-title p  { color: #a0a5b1; font-size: 0.95rem; margin: 0; }

        .dash-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; margin-bottom:45px; }
        .dash-stat-card {
            background:#1a1e24; border:1px solid #2a2f38; border-left:4px solid #FCC113;
            border-radius:4px; padding:24px; transition:0.3s;
        }
        .dash-stat-card:hover { border-color:#FCC113; transform:translateY(-3px); }
        .dash-stat-card .stat-label {
            font-family:'Montserrat',sans-serif; font-size:0.75rem; font-weight:800;
            letter-spacing:1px; color:#a0a5b1; text-transform:uppercase;
            margin-bottom:8px; display:block;
        }
        .dash-stat-card .stat-value {
            font-family:'Montserrat',sans-serif; font-size:2rem; font-weight:900;
            color:#FCC113; line-height:1;
        }
        .dash-stat-card .stat-sub { font-size:0.8rem; color:#8a8f9d; margin-top:6px; display:block; }
        .dash-stat-card.green { border-left-color:#28a745; } .dash-stat-card.green .stat-value { color:#28a745; }
        .dash-stat-card.red   { border-left-color:#dc3545; } .dash-stat-card.red   .stat-value { color:#dc3545; }
        .dash-stat-card.blue  { border-left-color:#0dcaf0; } .dash-stat-card.blue  .stat-value { color:#0dcaf0; }

        .brand-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px; }
        .brand-card { background:#1a1e24; border:1px solid #2a2f38; border-radius:4px; padding:24px; transition:0.3s; }
        .brand-card:hover { border-color:#FCC113; }
        .brand-card .brand-name {
            font-family:'Montserrat',sans-serif; font-size:1.1rem; font-weight:900;
            color:#FCC113; margin-bottom:4px;
        }
        .brand-card .brand-sub {
            font-size:0.75rem; color:#8a8f9d; text-transform:uppercase;
            letter-spacing:1px; margin-bottom:18px; display:block;
        }
        .brand-card .brand-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:8px 0; border-bottom:1px solid #222730; font-size:0.9rem;
        }
        .brand-card .brand-row:last-child { border-bottom:none; }
        .brand-card .brand-row span:first-child { color:#a0a5b1; font-weight:600; }
        .brand-card .brand-row span:last-child {
            font-family:'Montserrat',sans-serif; font-weight:900; color:#fff;
        }
        .brand-card .brand-row .available { color:#28a745; }
        .brand-card .brand-row .rented    { color:#FCC113; }

        .dash-section {
            background:#161a20; border:1px solid #222730; border-radius:4px;
            padding:30px; margin-bottom:30px;
        }
        .dash-section-title {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:22px; padding-bottom:16px; border-bottom:1px solid #222730;
            flex-wrap:wrap; gap:12px;
        }
        .dash-section-title h3 {
            font-size:1.15rem; font-weight:800; margin:0; color:#fff;
            display:flex; align-items:center; gap:10px;
        }
        .dash-section-title h3 i { color:#FCC113; }
        .dash-section-title .title-note { font-size:0.8rem; color:#8a8f9d; }

        .dash-table-wrap { overflow-x:auto; }
        .dash-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
        .dash-table thead th {
            text-align:left; font-family:'Montserrat',sans-serif; font-size:0.75rem;
            font-weight:800; letter-spacing:1px; text-transform:uppercase;
            color:#FCC113; padding:14px 12px; border-bottom:2px solid #2a2f38; white-space:nowrap;
        }
        .dash-table tbody td {
            padding:16px 12px; border-bottom:1px solid #222730;
            color:#d4d8dd; vertical-align:middle;
        }
        .dash-table tbody tr:hover { background:rgba(252,193,19,0.04); }
        .dash-table .empty-row td {
            text-align:center; color:#8a8f9d; padding:40px 12px; font-style:italic;
        }
        .dash-vehicle-name { color:#FCC113; font-weight:800; font-family:'Montserrat',sans-serif; }
        .dash-money { color:#28a745; font-weight:800; font-family:'Montserrat',sans-serif; }

        .dash-badge {
            display:inline-block; padding:5px 12px; border-radius:3px;
            font-family:'Montserrat',sans-serif; font-size:0.7rem; font-weight:900;
            letter-spacing:0.5px; text-transform:uppercase;
        }
        .dash-badge-approved  { background:#28a745; color:#fff; }
        .dash-badge-pending   { background:#FCC113; color:#212529; }
        .dash-badge-rejected  { background:#dc3545; color:#fff; }
        .dash-badge-pickedup  { background:#0dcaf0; color:#0b0c0e; }
        .dash-badge-returned  { background:#17a2b8; color:#fff; }
        .dash-badge-cancelreq { background:#fd7e14; color:#fff; }
        .dash-badge-cancelled { background:#6c757d; color:#fff; }

        .dash-action-btn {
            display:inline-flex; align-items:center; justify-content:center;
            width:34px; height:32px; border-radius:4px; font-size:0.8rem;
            text-decoration:none; margin-right:6px; transition:0.2s;
            border:none; cursor:pointer;
        }
        .dash-action-approve { background:#28a745; color:#fff; }
        .dash-action-approve:hover { background:#1e7e34; }
        .dash-action-reject  { background:#dc3545; color:#fff; }
        .dash-action-reject:hover  { background:#b02a37; }
        .dash-action-info    { background:#0dcaf0; color:#0b0c0e; }
        .dash-action-info:hover    { background:#0aa2c0; }
        .dash-action-warn    { background:#fd7e14; color:#fff; }
        .dash-action-warn:hover    { background:#e06400; }
        .dash-action-processed { font-size:0.8rem; color:#6c757d; font-style:italic; }

        .dash-alert {
            padding:16px 20px; border-radius:4px; margin-bottom:24px;
            font-size:0.9rem; border-left:4px solid;
            display:flex; align-items:center; gap:12px;
        }
        .dash-alert-success { background:rgba(40,167,69,0.1); border-color:#28a745; color:#7eec9a; }
        .dash-alert-error   { background:rgba(220,53,69,0.1); border-color:#dc3545; color:#ff8590; }

        .dash-empty { text-align:center; padding:60px 20px; color:#8a8f9d; }
        .dash-empty i { font-size:3rem; color:#2a2f38; margin-bottom:18px; display:block; }
        .dash-empty p { font-size:0.95rem; margin-bottom:22px; }

        .conv-list { display:flex; flex-direction:column; gap:20px; }
        .conv-item {
            background:#12161c; border:1px solid #222730; border-left:4px solid #FCC113;
            border-radius:4px; overflow:hidden; transition:0.3s;
        }
        .conv-item:hover { border-color:#FCC113; }
        .conv-head {
            display:flex; justify-content:space-between; align-items:center;
            padding:18px 22px; cursor:pointer; gap:15px; flex-wrap:wrap;
        }
        .conv-head-info { display:flex; flex-direction:column; gap:4px; min-width:0; }
        .conv-head-info strong {
            color:#FCC113; font-family:'Montserrat',sans-serif;
            font-size:0.95rem; font-weight:800;
        }
        .conv-head-info small { color:#8a8f9d; font-size:0.8rem; }
        .conv-head-info .conv-preview {
            color:#a0a5b1; font-size:0.85rem; margin-top:4px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:500px;
        }
        .conv-head-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .conv-toggle {
            background:transparent; border:1px solid #2a2f38; color:#FCC113;
            border-radius:4px; padding:6px 12px; font-size:0.75rem; font-weight:800;
            font-family:'Montserrat',sans-serif; letter-spacing:0.5px;
            cursor:pointer; transition:0.25s;
        }
        .conv-toggle:hover { background:#FCC113; color:#212529; border-color:#FCC113; }

        .conv-body { display:none; padding:0 22px 22px; border-top:1px solid #222730; }
        .conv-body.open { display:block; }
        .conv-thread {
            max-height:380px; overflow-y:auto; padding:18px 0;
            display:flex; flex-direction:column; gap:14px;
        }
        .conv-bubble {
            max-width:75%; padding:12px 16px; border-radius:12px;
            font-size:0.9rem; line-height:1.5; position:relative;
        }
        .conv-bubble .bubble-meta {
            display:block; font-size:0.7rem; font-weight:800;
            font-family:'Montserrat',sans-serif; letter-spacing:0.5px;
            margin-bottom:6px; opacity:0.75;
        }
        .conv-bubble.customer {
            align-self:flex-start; background:#1f2530; color:#fff; border-top-left-radius:4px;
        }
        .conv-bubble.customer .bubble-meta { color:#FCC113; }
        .conv-bubble.admin {
            align-self:flex-end; background:#FCC113; color:#212529; border-top-right-radius:4px;
        }
        .conv-bubble.admin .bubble-meta { color:#2b2500; }
        .conv-reply-form {
            display:flex; gap:10px; padding-top:18px;
            border-top:1px solid #222730; margin-top:6px;
        }
        .conv-reply-form textarea {
            flex:1; background:#0b0c0e; border:1px solid #2a2f38; border-radius:4px;
            color:#fff; padding:12px 14px; font-size:0.9rem; font-family:inherit;
            resize:vertical; min-height:50px; outline:none; transition:0.25s;
        }
        .conv-reply-form textarea:focus { border-color:#FCC113; }
        .conv-send-btn {
            background:#FCC113; color:#212529; border:2px solid #FCC113;
            border-radius:4px; font-family:'Montserrat',sans-serif; font-weight:900;
            font-size:0.8rem; letter-spacing:0.5px; padding:0 22px;
            cursor:pointer; transition:0.25s;
            display:inline-flex; align-items:center; gap:6px;
        }
        .conv-send-btn:hover { background:transparent; color:#FCC113; }
        .conv-empty-thread {
            padding:20px; text-align:center; color:#8a8f9d;
            font-size:0.85rem; font-style:italic;
        }

        .cancel-policy-box {
            border-radius:4px;
            padding:14px 16px;
            font-size:0.85rem;
            line-height:1.6;
            border-left:4px solid #fd7e14;
            background:rgba(253,126,20,0.08);
        }

        .chat-required-box {
            border-radius: 4px;
            padding: 16px 18px;
            font-size: 0.9rem;
            line-height: 1.6;
            border-left: 4px solid #FCC113;
            background: rgba(252, 193, 19, 0.08);
            color: #e6e6e6;
        }

        .chat-required-box strong {
            color: #FCC113;
            display: block;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .chat-required-box a {
            color: #FCC113;
            font-weight: 800;
            text-decoration: underline;
        }

        .dash-footer {
            text-align:center; padding:30px 6%; color:#6c757d;
            font-size:0.8rem; border-top:1px solid #222730; margin-top:40px;
        }

        @media (max-width:768px) {
            .dash-header { flex-direction:column; align-items:flex-start; }
            .dash-page-title h2 { font-size:1.5rem; }
            .dash-section { padding:20px; }
            .dash-table { font-size:0.8rem; }
            .dash-table thead th, .dash-table tbody td { padding:10px 8px; }
            .conv-reply-form { flex-direction:column; }
            .conv-bubble { max-width:90%; }
        }
    </style>
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

                                    <a href="admin_action.php?action=return&id=<?= (int)$rental['id'] ?>"
                                       class="dash-action-btn dash-action-approve" title="Mark Returned">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </a>
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

                                    <span class="dash-action-processed">Processed</span>

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
                        $withinWindow = $hoursSince <= 2;

                        // Can the customer open the modal?
                        $canSelfCancel = ($status === 'Pending' && $withinWindow);

                        // Can the customer click the button at all? (Pending or Approved)
                        $showButton = in_array($status, ['Pending', 'Approved'], true);

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
                            <td class="dash-money">₱<?= number_format((float)$rental['total_fee'], 2) ?></td>
                            <td>
                                <span class="dash-badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                            <td>
                                <?= $reason ? htmlspecialchars($reason) : '—' ?>
                            </td>
                            <td>
                                <?php if ($canSelfCancel): ?>

                                    <!-- Within 2h + Pending → opens the cancel modal -->
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

                                    <!-- After 2h or Approved → shows chat notice -->
                                    <button
                                        type="button"
                                        class="dash-action-btn dash-action-reject"
                                        title="Cannot self-cancel — chat with support"
                                        onclick="showChatRequired(
                                            '<?= htmlspecialchars($rental['vehicle_type'] ?? 'this vehicle', ENT_QUOTES) ?>'
                                        )"
                                    >
                                        <i class="fa-solid fa-ban"></i>
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
    &copy; 2026 RC Drive Car Rental Services. All rights reserved.
</footer>


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
     CHAT-REQUIRED MODAL (shown when self-cancel window has passed)
     ============================================================ -->
<div class="modal fade" id="chatRequiredModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-warning">

            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning">
                    <i class="fa-solid fa-circle-info"></i> Please Chat With Us Instead
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="chat-required-box mb-3">
                    <strong>Self-Cancellation Not Available</strong>
                    The 2-hour self-cancellation window for
                    <span id="chatVehicleName" style="color:#FCC113; font-weight:800;">this booking</span>
                    has passed, or the booking has already been approved by an admin.
                </div>

                <p style="color:#a0a5b1; font-size:0.9rem; margin-bottom:14px;">
                    If you still need to cancel, please reach out to our support team using the
                    <strong style="color:#FCC113;">"My Conversations with RC Drive"</strong>
                    section below this table. An admin will review your request and cancel the booking on your behalf.
                </p>

                <p style="color:#a0a5b1; font-size:0.85rem; margin-bottom:0;">
                    <i class="fa-solid fa-lightbulb" style="color:#FCC113;"></i>
                    Tip: Send a message like <em>"I need to cancel booking #your-booking-id due to [reason]"</em>
                    so our team can find it quickly.
                </p>

            </div>

            <div class="modal-footer border-secondary">
                <button type="button" class="dash-btn dash-btn-outline" data-bs-dismiss="modal">
                    Close
                </button>
                <button type="button" class="dash-btn dash-btn-yellow" id="scrollToChatBtn">
                    <i class="fa-solid fa-comments"></i> Go to Chat
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

    function showChatRequired(vehicleName) {
        var modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById('chatRequiredModal')
        );

        document.getElementById('chatVehicleName').textContent = vehicleName;

        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {

        var reasonSel = document.getElementById('cancelReason');
        var otherWrap = document.getElementById('reasonOtherWrap');

        if (reasonSel) {
            reasonSel.addEventListener('change', function () {
                if (this.value === 'Other') {
                    otherWrap.style.display = 'block';
                } else {
                    otherWrap.style.display = 'none';
                }
            });
        }

        var scrollBtn = document.getElementById('scrollToChatBtn');
        if (scrollBtn) {
            scrollBtn.addEventListener('click', function () {
                var modalEl = document.getElementById('chatRequiredModal');
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