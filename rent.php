<?php
session_start();
require_once 'config.php';

// Require login to rent
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?status=error&message=' . urlencode('Please log in to book a vehicle.'));
    exit;
}

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Vehicle - RC Drive</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h2 class="card-title text-center mb-3">RC Drive Booking</h2>
                        <p class="text-center text-muted">
                            Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>! | 
                            <a href="logout.php" class="text-danger text-decoration-none">Logout</a>
                        </p>

                        <?php if ($status === 'success'): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Booking confirmed successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php elseif ($status === 'error'): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="rent_function.php">
                            <div class="mb-3">
                                <label for="vehicle_type" class="form-label">Select Vehicle</label>
                                <select name="vehicle_type" id="vehicle_type" class="form-select" required>
                                    <option value="" selected disabled>Choose a vehicle...</option>
                                    <option value="Sedan">Sedan</option>
                                    <option value="SUV">SUV</option>
                                    <option value="Sports Car">Sports Car</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="rental_date" class="form-label">Rental Date (Pick-up)</label>
                                <input type="date" name="rental_date" id="rental_date" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="return_date" class="form-label">Return Date</label>
                                <input type="date" name="return_date" id="return_date" class="form-control" required>
                            </div>

                            <button type="submit" name="book_rent" class="btn btn-primary w-100 py-2">Confirm Booking</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="index.php" class="text-secondary text-decoration-none">← Back to Homepage</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>