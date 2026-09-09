<?php
session_start();
require_once 'config.php';

/*
|--------------------------------------------------------------------------
| DATABASE DATA
|--------------------------------------------------------------------------
*/

$userRentals = [];
$adminRentals = [];
$inquiries = [];

$totalFleet = 500;
$totalRented = 0;
$availableCars = 500;
$totalIncome = 0;
$pendingApprovals = 0;

$dbError = '';

/*
|--------------------------------------------------------------------------
| FETCH DATABASE INFORMATION
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    try {

        $pdo = getConnection();

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        if (($_SESSION['role'] ?? '') === 'admin') {

            // Get all rental records
            $stmt = $pdo->query("
                SELECT 
                    r.*,
                    COALESCE(u.username, r.customer_name) AS username,
                    v.vehicle_name,
                    v.price_per_day
                FROM rentals r
                LEFT JOIN users u 
                    ON r.user_id = u.id
                LEFT JOIN vehicles v
                    ON r.vehicle_id = v.id
                ORDER BY r.created_at DESC
            ");

            $adminRentals = $stmt->fetchAll(PDO::FETCH_ASSOC);


            // Calculate dashboard statistics
            foreach ($adminRentals as $rental) {

                if (($rental['status'] ?? '') === 'Approved') {

                    $totalRented++;

                    $totalIncome +=
                        (float)($rental['total_fee'] ?? 0);

                } elseif (($rental['status'] ?? '') === 'Pending') {

                    $pendingApprovals++;
                }
            }


            $availableCars =
                max(0, $totalFleet - $totalRented);


            /*
            |--------------------------------------------------------------------------
            | INQUIRIES
            |--------------------------------------------------------------------------
            */

            try {

                $inquiryStmt = $pdo->query("
                    SELECT *
                    FROM inquiries
                    ORDER BY created_at DESC
                ");

                $inquiries =
                    $inquiryStmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {

                $inquiries = [];
            }


        /*
        |--------------------------------------------------------------------------
        | CUSTOMER
        |--------------------------------------------------------------------------
        */

        } else {

            $userId =
                $_SESSION['user_id'] ?? 0;

            $username =
                $_SESSION['username'] ?? '';


            $stmt = $pdo->prepare("
                SELECT 
                    r.id,
                    r.user_id,
                    r.customer_name,
                    r.phone_number,
                    r.vehicle_id,
                    r.vehicle_type,
                    r.rental_date,
                    r.return_date,
                    r.total_fee,
                    r.status,
                    r.created_at,
                    v.vehicle_name,
                    v.price_per_day
                FROM rentals r
                LEFT JOIN vehicles v
                    ON r.vehicle_id = v.id
                WHERE r.user_id = :user_id
                ORDER BY r.created_at DESC
            ");


            $stmt->execute([
                ':user_id' => $userId
            ]);


            $userRentals =
                $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {

        $dbError =
            "Database connection error. Please check your database configuration.";
    }
}


/*
|--------------------------------------------------------------------------
| SESSION USER DATA FOR JAVASCRIPT
|--------------------------------------------------------------------------
*/

$activeUser = null;

if (isset($_SESSION['user_id'])) {

    $activeUser = [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? 'customer'
    ];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RC Drive - Car Rental Services</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <!-- Google Font -->

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap"
        rel="stylesheet"
    >


    <!-- Your CSS -->

    <link
        rel="stylesheet"
        href="style.css"
    >


    <!--
    |--------------------------------------------------------------------------
    | PASS PHP USER DATA TO JAVASCRIPT
    |--------------------------------------------------------------------------
    -->

    <script>

        window.RCDriveUser =
            <?php echo json_encode($activeUser); ?>;

    </script>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
-->

<header class="navbar-header">

    <div class="logo">

        <img
            src="images/Asset 1.png"
            alt="RC Drive Logo"
        >

    </div>


    <nav>

        <ul class="nav-links">

            <li>
                <a
                    href="#home"
                    class="active"
                >
                    Home
                </a>
            </li>

            <li>
                <a href="#fleet">
                    Our Fleet
                </a>
            </li>

            <li>
                <a href="#services">
                    Services
                </a>
            </li>

            <li>
                <a href="#about">
                    About
                </a>
            </li>

            <li>
                <a href="#contact">
                    Contact
                </a>
            </li>

        </ul>

    </nav>


    <div class="nav-actions d-flex gap-2 align-items-center">


       
        <?php if (isset($_SESSION['username'])): ?>

            <span class="badge <?= ($_SESSION['role'] ?? '') === 'admin' ? 'bg-danger' : 'bg-info' ?> me-1">
                <?= ($_SESSION['role'] ?? '') === 'admin' ? 'ADMIN' : 'CUSTOMER' ?>
            </span>

            <button
            <button
                class="btn btn-outline-warning btn-sm py-2 px-3 fw-bold"
                data-bs-toggle="modal"
                data-bs-target="#loginModal"
            >

                <i class="fa-solid fa-user me-1"></i>

                <?php
                echo htmlspecialchars(
                    $_SESSION['username']
                );
                ?>

            </button>


            <a
                href="logout.php"
                class="btn btn-outline-danger btn-sm text-decoration-none py-2 px-3 fw-bold"
            >
                Logout
            </a>


        <?php else: ?>

            <button
                class="btn-pill-yellow border-0 py-2 px-3 fs-6"
                id="navAuthBtn"
                data-bs-toggle="modal"
                data-bs-target="#loginModal"
            >

                <i class="fa-solid fa-user me-1"></i>

                Login / Register

            </button>

        <?php endif; ?>


        <a
            href="#fleet"
            class="btn-pill-yellow text-decoration-none py-2 px-3 fs-6"
        >
            Book Now
        </a>

    </div>

</header>


<!--
|--------------------------------------------------------------------------
| DATABASE ERROR
|--------------------------------------------------------------------------
-->

<?php if ($dbError): ?>

    <div class="container mt-3">

        <div class="alert alert-danger">

            <i class="fa-solid fa-circle-exclamation me-2"></i>

            <?php echo htmlspecialchars($dbError); ?>

        </div>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| SUCCESS / ERROR MESSAGE
|--------------------------------------------------------------------------
-->

<?php if (isset($_GET['message'])): ?>

    <div class="container mt-3">

        <div
            class="alert alert-<?php
                echo ($_GET['status'] ?? '') === 'success'
                    ? 'success'
                    : 'danger';
            ?> alert-dismissible fade show"
            role="alert"
        >

            <i class="fa-solid
                <?php
                echo ($_GET['status'] ?? '') === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation';
                ?>
                me-2">
            </i>

            <?php
            echo htmlspecialchars(
                $_GET['message']
            );
            ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            >
            </button>

        </div>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
-->

<section
    id="home"
    class="hero"
>

    <div class="hero-overlay"></div>


    <div class="hero-content">

        <div class="sub-heading-container">

            <span class="yellow-line"></span>

            <span class="sub-heading">
                Premium Car Rental
            </span>

        </div>


        <h1>

            DRIVE YOUR

            <br>

            <span class="yellow-text">
                FREEDOM
            </span>

        </h1>


        <p>
            Experience the road on your terms.
            RC Drive delivers premium vehicles
            with flexible plans — from quick city
            runs to extended road trips across
            Negros Oriental.
        </p>


        <div class="hero-btns">

            <a
                href="#fleet"
                class="btn-pill-yellow"
            >
                BOOK A CAR NOW
            </a>


            <a
                href="#fleet"
                class="btn-pill-outline"
            >
                VIEW FLEET
            </a>

        </div>


        <div class="stats">

            <div class="stat-item">

                <h3>500+</h3>

                <p>VEHICLES</p>

            </div>


            <div class="stat-divider"></div>


            <div class="stat-item">

                <h3>15K+</h3>

                <p>HAPPY CLIENTS</p>

            </div>


            <div class="stat-divider"></div>


            <div class="stat-item">

                <h3>24/7</h3>

                <p>SUPPORT</p>

            </div>

        </div>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
-->

<div class="search-bar-container">

    <div class="search-box">

        <div class="search-field">

            <label>
                PICK UP LOCATION
            </label>

            <input
                type="text"
                id="homePickupLocation"
                placeholder="CITY, AIRPORT, ADDRESS"
            >

        </div>


        <div class="search-field">

            <label>
                PICK UP DATE
            </label>

            <input
                type="date"
                id="homePickupDate"
            >

        </div>


        <div class="search-field">

            <label>
                RETURN DATE
            </label>

            <input
                type="date"
                id="homeReturnDate"
            >

        </div>


        <button
            type="button"
            class="search-btn"
            onclick="searchFleet()"
        >
            SEARCH —
        </button>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| FLEET
|--------------------------------------------------------------------------
-->

<section
    id="fleet"
    class="fleet-section"
>

    <span class="sub-heading-center">
        — Our Fleet —
    </span>


    <h2>
        Choose Your
        <span>Perfect Ride</span>
    </h2>


    <!-- FILTER -->

    <div class="fleet-filter">

        <button
            type="button"
            class="filter-btn active"
            data-filter="all"
        >
            ALL
        </button>


        <button
            type="button"
            class="filter-btn"
            data-filter="sedan"
        >
            SEDAN
        </button>


        <button
            type="button"
            class="filter-btn"
            data-filter="suv"
        >
            SUV
        </button>


        <button
            type="button"
            class="filter-btn"
            data-filter="sports"
        >
            SPORTS
        </button>

    </div>


    <!-- VEHICLES -->

    <div class="fleet-grid">


        <!-- SEDAN -->

        <div class="car-card sedan">

            <div class="card-img">

                <span class="badge-tag yellow-bg">
                    POPULAR
                </span>

                <span class="price-tag">
                    2500
                </span>

                <img
                    src="images/sedan.jpg"
                    alt="Executive Sedan"
                >

            </div>


            <div class="card-details">

                <h3>
                    Executive Sedan
                </h3>


                <p>
                    Comfortable and fuel-efficient.
                    Perfect for business and city drives.
                </p>


                <div class="specs">

                    <span>
                        <i class="fa-solid fa-user-group"></i>
                        5 Seats
                    </span>

                    <span>
                        <i class="fa-solid fa-gear"></i>
                        Auto
                    </span>

                    <span>
                        <i class="fa-solid fa-gas-pump"></i>
                        Petrol
                    </span>

                </div>


                <button
                    type="button"
                    class="btn-outline-card"
                    onclick="openRentModal('Executive Sedan', 2500)"
                >
                    Rent Vehicle
                </button>

            </div>

        </div>


        <!-- SUV -->

        <div class="car-card suv">

            <div class="card-img">

                <span class="price-tag">
                    3800
                </span>

                <img
                    src="images/suv.jpg"
                    alt="Family SUV"
                >

            </div>


            <div class="card-details">

                <h3>
                    Family SUV
                </h3>


                <p>
                    Spacious and powerful.
                    Ideal for long road trips
                    and family vacations.
                </p>


                <div class="specs">

                    <span>
                        <i class="fa-solid fa-user-group"></i>
                        7 Seats
                    </span>

                    <span>
                        <i class="fa-solid fa-gear"></i>
                        Auto
                    </span>

                    <span>
                        <i class="fa-solid fa-gas-pump"></i>
                        Petrol
                    </span>

                </div>


                <button
                    type="button"
                    class="btn-outline-card"
                    onclick="openRentModal('Family SUV', 3800)"
                >
                    Rent Vehicle
                </button>

            </div>

        </div>


        <!-- SPORTS -->

        <div class="car-card sports">

            <div class="card-img">

                <span class="badge-tag outline-bg">
                    PREMIUM
                </span>

                <span class="price-tag">
                    6500
                </span>

                <img
                    src="images/sports.jpg"
                    alt="Luxury Sports"
                >

            </div>


            <div class="card-details">

                <h3>
                    Luxury Sports
                </h3>


                <p>
                    Premium luxury performance
                    vehicle for a high-end
                    driving experience.
                </p>


                <div class="specs">

                    <span>
                        <i class="fa-solid fa-user-group"></i>
                        2 Seats
                    </span>

                    <span>
                        <i class="fa-solid fa-gear"></i>
                        Auto
                    </span>

                    <span>
                        <i class="fa-solid fa-gas-pump"></i>
                        Petrol
                    </span>

                </div>


                <button
                    type="button"
                    class="btn-outline-card"
                    onclick="openRentModal('Luxury Sports', 6500)"
                >
                    Rent Vehicle
                </button>

            </div>

        </div>


    </div>

</section>


<!--
|--------------------------------------------------------------------------
| HOW IT WORKS
|--------------------------------------------------------------------------
-->

<section class="how-it-works">

    <span class="sub-heading-center">
        — SIMPLE PROCESS —
    </span>


    <h2>
        HOW IT <span>WORKS</span>
    </h2>


    <div class="process-grid">


        <div class="process-card">

            <div class="step-num">
                01
            </div>

            <i class="fa-solid fa-user-plus icon"></i>

            <h3>
                Log In / Sign Up
            </h3>

            <p>
                Create or access your customer
                account to easily track and manage
                your bookings.
            </p>

        </div>


        <div class="process-card">

            <div class="step-num">
                02
            </div>

            <i class="fa-solid fa-car icon"></i>

            <h3>
                Choose Your Car
            </h3>

            <p>
                Browse our fleet and select the
                vehicle that fits your needs
                and budget.
            </p>

        </div>


        <div class="process-card">

            <div class="step-num">
                03
            </div>

            <i class="fa-regular fa-calendar-days icon"></i>

            <h3>
                Select Dates
            </h3>

            <p>
                Pick your pick-up and return
                dates using our rental form.
            </p>

        </div>


        <div class="process-card">

            <div class="step-num">
                04
            </div>

            <i class="fa-solid fa-key icon"></i>

            <h3>
                Drive Away
            </h3>

            <p>
                Wait for admin approval and
                check your booking status
                inside your account.
            </p>

        </div>


    </div>

</section>


<!--
|--------------------------------------------------------------------------
| SERVICES
|--------------------------------------------------------------------------
-->

<section
    id="services"
    class="why-us-section"
>

    <div class="why-us-left">

        <span class="sub-heading">
            — WHY RC DRIVE
        </span>


        <h2>
            THE SMARTEST
            <span>WAY TO RENT</span>
        </h2>


        <p>
            RC Drive combines a premium fleet,
            competitive prices, and effortless
            booking into one seamless experience.
            Proudly serving Dumaguete City
            and Negros Oriental.
        </p>


        <div class="reach-us-box">

            <h4>
                REACH US DIRECTLY
            </h4>


            <p>
                <i class="fa-solid fa-location-dot"></i>
                Dumaguete City, Negros Oriental
            </p>


            <p>
                <i class="fa-solid fa-phone"></i>
                +63 930 222 9696
            </p>


            <p>
                <i class="fa-solid fa-envelope"></i>
                support@rcdrive.com
            </p>

        </div>


        <button
            type="button"
            class="btn-yellow mt-4"
            data-bs-toggle="modal"
            data-bs-target="#inquiryModal"
        >
            CONTACT US —
        </button>

    </div>


    <div class="why-us-right">


        <div class="feature-card">

            <i class="fa-solid fa-car"></i>

            <div>

                <h4>
                    Wide Selection
                </h4>

                <p>
                    Vehicles for every occasion.
                </p>

            </div>

        </div>


        <div class="feature-card">

            <i class="fa-solid fa-award"></i>

            <div>

                <h4>
                    Best Price Guarantee
                </h4>

                <p>
                    Competitive rental prices.
                </p>

            </div>

        </div>


        <div class="feature-card">

            <i class="fa-solid fa-wrench"></i>

            <div>

                <h4>
                    Well-Maintained Fleet
                </h4>

                <p>
                    Safety checks before rentals.
                </p>

            </div>

        </div>


        <div class="feature-card">

            <i class="fa-solid fa-location-pin"></i>

            <div>

                <h4>
                    Dumaguete Based
                </h4>

                <p>
                    Serving Dumaguete City
                    and Negros Oriental.
                </p>

            </div>

        </div>


        <div class="feature-card">

            <i class="fa-solid fa-bolt"></i>

            <div>

                <h4>
                    Easy Booking
                </h4>

                <p>
                    Submit your booking online.
                </p>

            </div>

        </div>


        <div class="feature-card">

            <i class="fa-solid fa-shield-halved"></i>

            <div>

                <h4>
                    Secure Accounts
                </h4>

                <p>
                    Customer accounts and
                    booking records.
                </p>

            </div>

        </div>


    </div>

</section>


<!--
|--------------------------------------------------------------------------
| ABOUT
|--------------------------------------------------------------------------
-->

<section
    id="about"
    class="py-5"
>

    <div class="container py-5">

        <div class="text-center">

            <span class="sub-heading-center">
                — ABOUT RC DRIVE —
            </span>


            <h2 class="mt-3">
                DRIVE WITH
                <span>CONFIDENCE</span>
            </h2>


            <p class="mx-auto mt-3" style="max-width:800px;">

                RC Drive is a car rental service
                focused on providing convenient,
                reliable, and affordable vehicle
                rentals for customers in Dumaguete
                City and Negros Oriental.

            </p>

        </div>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| LOGIN / REGISTER / DASHBOARD MODAL
|--------------------------------------------------------------------------
-->

<div
    class="modal fade"
    id="loginModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered modal-xl"
    >

        <div
            class="modal-content bg-dark text-white border-warning"
        >


            <div class="modal-header border-secondary">

                <h5
                    class="modal-title text-warning"
                >

                    <i class="fa-solid fa-lock"></i>

                    Account & Access Portal

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                >
                </button>

            </div>


            <div class="modal-body p-4">


                <!-- LOGGED OUT -->

                <?php if (!isset($_SESSION['user_id'])): ?>


                    <ul
                        class="nav nav-tabs border-secondary mb-3"
                        id="authTab"
                        role="tablist"
                    >

                        <li
                            class="nav-item"
                            role="presentation"
                        >

                            <button
                                class="nav-link active text-warning"
                                data-bs-toggle="tab"
                                data-bs-target="#login-tab-pane"
                                type="button"
                            >
                                Login
                            </button>

                        </li>


                        <li
                            class="nav-item"
                            role="presentation"
                        >

                            <button
                                class="nav-link text-warning"
                                data-bs-toggle="tab"
                                data-bs-target="#register-tab-pane"
                                type="button"
                            >
                                Register
                            </button>

                        </li>

                    </ul>


                    <div
                        class="tab-content"
                        id="authTabContent"
                    >


                        <!-- LOGIN -->

                        <div
                            class="tab-pane fade show active"
                            id="login-tab-pane"
                        >

                            <form
                                action="login_function.php"
                                method="POST"
                            >

                                <div class="mb-3">

                                    <label class="form-label">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        name="username"
                                        class="form-control bg-secondary text-white border-0"
                                        placeholder="Enter username"
                                        required
                                    >

                                </div>


                                <div class="mb-3">

                                    <label class="form-label">
                                        Password
                                    </label>

                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control bg-secondary text-white border-0"
                                        placeholder="Enter password"
                                        required
                                    >

                                </div>


                                <button
                                    type="submit"
                                    name="login"
                                    class="btn btn-warning w-100 fw-bold py-2"
                                >
                                    LOG IN / ACCESS ACCOUNT
                                </button>

                            </form>

                        </div>


                        <!-- REGISTER -->

                        <div
                            class="tab-pane fade"
                            id="register-tab-pane"
                        >

                            <form
                                action="register_function.php"
                                method="POST"
                            >

                                <div class="mb-3">

                                    <label class="form-label">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        name="username"
                                        class="form-control bg-secondary text-white border-0"
                                        placeholder="Create username"
                                        required
                                    >

                                </div>


                                <div class="mb-3">

                                    <label class="form-label">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        class="form-control bg-secondary text-white border-0"
                                        placeholder="e.g. name@domain.com"
                                        required
                                    >

                                </div>


                                <div class="mb-3">

                                    <label class="form-label">
                                        Password
                                    </label>

                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control bg-secondary text-white border-0"
                                        placeholder="Create password"
                                        required
                                    >

                                </div>


                                <button
                                    type="submit"
                                    name="register"
                                    class="btn btn-warning w-100 fw-bold py-2"
                                >
                                    REGISTER ACCOUNT
                                </button>

                            </form>

                        </div>


                    </div>


                <!-- ADMIN -->

                <?php elseif (($_SESSION['role'] ?? '') === 'admin'): ?>


                    <div id="adminDashboard">


                        <div
                            class="d-flex justify-content-between align-items-center mb-4"
                        >

                            <h4 class="text-warning">

                                <i class="fa-solid fa-chart-line"></i>

                                Administrator Dashboard

                            </h4>


                            <a
                                href="logout.php"
                                class="btn btn-sm btn-outline-danger"
                            >
                                Logout Admin
                            </a>

                        </div>


                        <!-- METRICS -->

                        <div class="row g-3 mb-4">


                            <div class="col-md-3">

                                <div
                                    class="p-3 bg-secondary rounded text-center border border-warning"
                                >

                                    <small>
                                        AVAILABLE CARS
                                    </small>

                                    <h2 class="text-warning">
                                        <?= $availableCars ?>
                                    </h2>

                                </div>

                            </div>


                            <div class="col-md-3">

                                <div
                                    class="p-3 bg-secondary rounded text-center border border-info"
                                >

                                    <small>
                                        CARS RENTED
                                    </small>

                                    <h2 class="text-info">
                                        <?= $totalRented ?>
                                    </h2>

                                </div>

                            </div>


                            <div class="col-md-3">

                                <div
                                    class="p-3 bg-secondary rounded text-center border border-success"
                                >

                                    <small>
                                        TOTAL INCOME
                                    </small>

                                    <h2 class="text-success">
                                        ₱<?= number_format($totalIncome, 2) ?>
                                    </h2>

                                </div>

                            </div>


                            <div class="col-md-3">

                                <div
                                    class="p-3 bg-secondary rounded text-center border border-danger"
                                >

                                    <small>
                                        PENDING
                                    </small>

                                    <h2 class="text-danger">
                                        <?= $pendingApprovals ?>
                                    </h2>

                                </div>

                            </div>


                        </div>


                        <!-- RENTAL TABLE -->

                        <h5
                            class="text-warning border-bottom border-secondary pb-2 mb-3"
                        >

                            <i class="fa-solid fa-car-side"></i>

                            Customer Rentals & Approvals

                        </h5>


                        <div class="table-responsive mb-4">

                            <table
                                class="table table-dark table-striped align-middle"
                            >

                                <thead>

                                    <tr>

                                        <th>Customer</th>

                                        <th>Phone</th>

                                        <th>Vehicle</th>

                                        <th>Pickup</th>

                                        <th>Return</th>

                                        <th>Total</th>

                                        <th>Status</th>

                                        <th>Action</th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php if (empty($adminRentals)): ?>

                                        <tr>

                                            <td
                                                colspan="8"
                                                class="text-center text-muted"
                                            >
                                                No rental bookings registered yet.
                                            </td>

                                        </tr>


                                    <?php else: ?>


                                        <?php foreach ($adminRentals as $rental): ?>

                                            <tr>


                                                <td class="fw-bold text-warning">

                                                    <?= htmlspecialchars(
                                                        $rental['username'] ?? $rental['customer_name'] ?? 'Customer'
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $rental['phone_number'] ?? 'N/A'
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $rental['vehicle_type'] ?? 'N/A'
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $rental['rental_date'] ?? ''
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $rental['return_date'] ?? ''
                                                    ) ?>

                                                </td>


                                                <td class="text-success fw-bold">

                                                    ₱<?= number_format(
                                                        (float)($rental['total_fee'] ?? 0),
                                                        2
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?php

                                                    $status =
                                                        $rental['status'] ?? 'Pending';

                                                    if ($status === 'Approved') {

                                                        $badge =
                                                            'bg-success';

                                                    } elseif ($status === 'Rejected') {

                                                        $badge =
                                                            'bg-danger';

                                                    } else {

                                                        $badge =
                                                            'bg-warning text-dark';
                                                    }

                                                    ?>


                                                    <span
                                                        class="badge <?= $badge ?>"
                                                    >
                                                        <?= htmlspecialchars($status) ?>
                                                    </span>

                                                </td>


                                                <td>


                                                    <?php if ($status === 'Pending'): ?>

                                                        <a
                                                            href="admin_action.php?action=approve&id=<?= (int)$rental['id'] ?>"
                                                            class="btn btn-sm btn-success me-1"
                                                        >
                                                            <i class="fa-solid fa-check"></i>
                                                        </a>


                                                        <a
                                                            href="admin_action.php?action=reject&id=<?= (int)$rental['id'] ?>"
                                                            class="btn btn-sm btn-danger"
                                                        >
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </a>


                                                    <?php else: ?>

                                                        <span class="text-muted">
                                                            Processed
                                                        </span>

                                                    <?php endif; ?>


                                                </td>


                                            </tr>

                                        <?php endforeach; ?>


                                    <?php endif; ?>


                                </tbody>

                            </table>

                        </div>


                        <!-- INQUIRIES -->

                        <h5
                            class="text-warning border-bottom border-secondary pb-2 mb-3"
                        >

                            <i class="fa-solid fa-envelope"></i>

                            Client Inquiries

                        </h5>


                        <div class="table-responsive">

                            <table
                                class="table table-dark table-hover"
                            >

                                <thead>

                                    <tr>

                                        <th>Name</th>

                                        <th>Email</th>

                                        <th>Message</th>

                                        <th>Date</th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php if (empty($inquiries)): ?>

                                        <tr>

                                            <td
                                                colspan="4"
                                                class="text-center text-muted"
                                            >
                                                No inquiries yet.
                                            </td>

                                        </tr>


                                    <?php else: ?>


                                        <?php foreach ($inquiries as $inq): ?>

                                            <tr>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $inq['name'] ?? 'Client'
                                                    ) ?>

                                                </td>


                                                <td class="text-warning">

                                                    <?= htmlspecialchars(
                                                        $inq['email'] ?? 'N/A'
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $inq['message'] ?? ''
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $inq['created_at'] ?? ''
                                                    ) ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>


                                    <?php endif; ?>


                                </tbody>

                            </table>

                        </div>


                    </div>


                <!-- CUSTOMER -->

                <?php else: ?>


                    <div id="customerDashboard">


                        <div
                            class="d-flex justify-content-between align-items-center mb-3"
                        >

                            <h4>

                                Welcome,

                                <span class="text-warning">

                                    <?= htmlspecialchars(
                                        $_SESSION['username']
                                    ) ?>

                                </span>

                            </h4>


                            <div>

                                <a
                                    href="delete_account.php"
                                    class="btn btn-sm btn-danger me-1"
                                    onclick="return confirm('Are you sure you want to permanently delete your account?');"
                                >
                                    Delete Account
                                </a>


                                <a
                                    href="logout.php"
                                    class="btn btn-sm btn-outline-danger"
                                >
                                    Logout
                                </a>

                            </div>

                        </div>


                        <h6>
                            Your Rental History & Reservations
                        </h6>


                        <div class="table-responsive mt-3">

                            <table
                                class="table table-dark table-hover align-middle"
                            >

                                <thead>

                                    <tr>

                                        <th>Vehicle</th>

                                        <th>Pickup</th>

                                        <th>Return</th>

                                        <th>Total</th>

                                        <th>Status</th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php if (empty($userRentals)): ?>

                                        <tr>

                                            <td
                                                colspan="5"
                                                class="text-center text-muted"
                                            >

                                                You have no booking requests yet.
                                                Pick a car from the fleet section
                                                to start!

                                            </td>

                                        </tr>


                                    <?php else: ?>


                                        <?php foreach ($userRentals as $rental): ?>

                                            <tr>

                                                <td class="fw-bold">

                                                    <span class="text-warning">
                                                        <?= htmlspecialchars(
                                                            $rental['vehicle_name']
                                                                ?? $rental['vehicle_type']
                                                                ?? 'Unknown Vehicle'
                                                        ) ?>
                                                    </span>

                                                    <?php if (!empty($rental['vehicle_id'])): ?>
                                                        <div class="small text-muted">
                                                            Vehicle ID: <?= (int)$rental['vehicle_id'] ?>
                                                        </div>
                                                    <?php endif; ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $rental['rental_date']
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $rental['return_date']
                                                    ) ?>

                                                </td>


                                                <td class="text-warning fw-bold">

                                                    ₱<?= number_format(
                                                        (float)$rental['total_fee'],
                                                        2
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?php

                                                    $status =
                                                        $rental['status'] ?? 'Pending';

                                                    $badge =
                                                        $status === 'Approved'
                                                            ? 'bg-success'
                                                            : (
                                                                $status === 'Rejected'
                                                                    ? 'bg-danger'
                                                                    : 'bg-warning text-dark'
                                                            );

                                                    ?>


                                                    <span
                                                        class="badge <?= $badge ?>"
                                                    >

                                                        <?= htmlspecialchars($status) ?>

                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>


                                    <?php endif; ?>


                                </tbody>

                            </table>

                        </div>

                    </div>


                <?php endif; ?>


            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| RENT VEHICLE MODAL
|--------------------------------------------------------------------------
-->

<div
    class="modal fade"
    id="rentModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered"
    >

        <div
            class="modal-content bg-dark text-white border-warning"
        >


            <div class="modal-header border-secondary">

                <h5
                    class="modal-title text-warning"
                    id="modalVehicleTitle"
                >
                    Rent Vehicle
                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                >
                </button>

            </div>


            <div class="modal-body">


                <p
                    id="modalVehicleRate"
                    class="fw-bold fs-5 text-light"
                >
                </p>


                <form
                    id="rentForm"
                    action="rent_function.php"
                    method="POST"
                >


                    <!-- VEHICLE -->

                    <input
                        type="hidden"
                        name="vehicle_type"
                        id="selectedCarName"
                    >


                    <!-- RATE IS USED ONLY BY JAVASCRIPT -->

                    <input
                        type="hidden"
                        id="selectedCarRate"
                    >


                    <!-- CUSTOMER -->

                    <div class="mb-3">

                        <label class="form-label">
                            Customer Account Name
                        </label>


                        <input
                            type="text"
                            id="rentCustomerName"
                            class="form-control bg-secondary text-white border-0"
                            value="<?= htmlspecialchars(
                                $_SESSION['username'] ?? ''
                            ) ?>"
                            readonly
                            required
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="mb-3">

                        <label class="form-label">
                            Phone Number
                        </label>


                        <input
                            type="tel"
                            name="phone_number"
                            id="rentPhone"
                            class="form-control bg-secondary text-white border-0"
                            placeholder="e.g. 09302229696"
                            pattern="[0-9]{10,11}"
                            required
                        >

                    </div>


                    <!-- DATES -->

                    <div class="row">


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Pick-up Date
                            </label>


                            <input
                                type="date"
                                name="rental_date"
                                id="rentStartDate"
                                class="form-control bg-secondary text-white border-0"
                                required
                            >

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Return Date
                            </label>


                            <input
                                type="date"
                                name="return_date"
                                id="rentEndDate"
                                class="form-control bg-secondary text-white border-0"
                                required
                            >

                        </div>


                    </div>

                    <!-- PAYMENT METHOD -->

                    <div class="mb-3">

                        <label class="form-label">
                            Payment Method
                        </label>

                        <select
                            name="payment_method"
                            id="rentPaymentMethod"
                            class="form-select bg-secondary text-white border-0"
                            required
                        >
                            <option value="" selected disabled>Choose payment method...</option>
                            <option value="GCash">GCash</option>
                            <option value="PayMaya">PayMaya</option>
                            <option value="Credit/Debit Card">Credit / Debit Card</option>
                            <option value="Cash on Pickup">Cash upon getting the car</option>
                        </select>

                    </div>
                    <!-- TOTAL -->

                    <div
                        class="p-3 bg-secondary rounded mb-3"
                    >

                        <div
                            class="d-flex justify-content-between align-items-center mb-1"
                        >

                            <span>
                                Duration:
                            </span>


                            <strong
                                id="calculatedDays"
                                class="text-white"
                            >
                                1 Day
                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between align-items-center"
                        >

                            <span>
                                Estimated Total Price:
                            </span>


                            <strong
                                id="calculatedTotal"
                                class="text-warning fs-5"
                            >
                                ₱0.00
                            </strong>

                        </div>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        name="book_rent"
                        class="btn btn-warning w-100 fw-bold py-2"
                    >

                        CONFIRM BOOKING

                    </button>


                </form>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| INQUIRY MODAL
|--------------------------------------------------------------------------
-->

<div
    class="modal fade"
    id="inquiryModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-dialog-centered">

        <div
            class="modal-content bg-dark text-white border-warning"
        >

            <div class="modal-header">

                <h5 class="modal-title text-warning">
                    Contact RC Drive
                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                >
                </button>

            </div>


            <div class="modal-body">

                <form
                    action="inquiry_function.php"
                    method="POST"
                >


                    <div class="mb-3">

                        <label class="form-label">
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control bg-secondary text-white border-0"
                            value="<?= htmlspecialchars(
                                $_SESSION['username'] ?? ''
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control bg-secondary text-white border-0"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Message
                        </label>

                        <textarea
                            name="message"
                            class="form-control bg-secondary text-white border-0"
                            rows="5"
                            required
                        ></textarea>

                    </div>


                    <button
                        type="submit"
                        name="send_inquiry"
                        class="btn btn-warning w-100 fw-bold"
                    >
                        SEND MESSAGE
                    </button>


                </form>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
-->

<footer
    class="site-footer"
    id="contact"
>


    <div class="footer-top">


        <div class="footer-brand">


            <div class="footer-logo">

                <img
                    src="images/Asset 1.png"
                    alt="RC Drive Logo"
                >

            </div>


            <p>

                Providing premium, reliable,
                and convenient vehicle rentals
                across Dumaguete City and
                Negros Oriental.

            </p>


            <div class="social-links">

                <a href="#">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>

                <a href="#">
                    <i class="fa-brands fa-instagram"></i>
                </a>

                <a href="#">
                    <i class="fa-brands fa-twitter"></i>
                </a>

                <a href="#">
                    <i class="fa-brands fa-linkedin-in"></i>
                </a>

            </div>


        </div>


        <div class="footer-nav-columns">


            <div class="footer-col">

                <h4>
                    QUICK LINKS
                </h4>


                <ul>

                    <li>
                        <a href="#home">
                            Home
                        </a>
                    </li>

                    <li>
                        <a href="#fleet">
                            Our Fleet
                        </a>
                    </li>

                    <li>
                        <a href="#services">
                            Services
                        </a>
                    </li>

                    <li>
                        <a href="#about">
                            About Us
                        </a>
                    </li>

                    <li>
                        <a href="#contact">
                            Contact
                        </a>
                    </li>

                </ul>

            </div>


            <div class="footer-col">

                <h4>
                    VEHICLES
                </h4>


                <ul>

                    <li>
                        <a href="#fleet">
                            Executive Sedans
                        </a>
                    </li>

                    <li>
                        <a href="#fleet">
                            Family SUVs
                        </a>
                    </li>

                    <li>
                        <a href="#fleet">
                            Luxury Sports Cars
                        </a>
                    </li>

                </ul>

            </div>


            <div class="footer-col">

                <h4>
                    CONTACT US
                </h4>


                <ul>

                    <li>
                        <i class="fa-solid fa-location-dot me-2"></i>
                        Dumaguete City, Negros Oriental
                    </li>

                    <li>
                        <i class="fa-solid fa-phone me-2"></i>
                        +63 930 222 9696
                    </li>

                    <li>
                        <i class="fa-solid fa-envelope me-2"></i>
                        support@rcdrive.com
                    </li>

                    <li>
                        <i class="fa-solid fa-clock me-2"></i>
                        24/7 Roadside & Support
                    </li>

                </ul>

            </div>


            <div class="footer-col newsletter-col">

                <h4>
                    NEWSLETTER
                </h4>


                <p>
                    Subscribe to get special discounts
                    and seasonal vehicle rental deals.
                </p>


                <form
                    class="newsletter-form"
                    onsubmit="event.preventDefault(); alert('Subscribed successfully!');"
                >

                    <input
                        type="email"
                        placeholder="Enter your email"
                        required
                    >


                    <button
                        type="submit"
                        class="btn-yellow-sm"
                    >
                        Join
                    </button>

                </form>

            </div>


        </div>


    </div>


    <div class="footer-bottom">

        <p>
            &copy; 2026 RC Drive Car Rental Services.
            All rights reserved.
        </p>


        <div class="footer-legal">

            <a href="#">
                Privacy Policy
            </a>

            |

            <a href="#">
                Terms of Service
            </a>

        </div>

    </div>


</footer>


<!--
|--------------------------------------------------------------------------
| JAVASCRIPT
|--------------------------------------------------------------------------
| Bootstrap MUST load first.
|--------------------------------------------------------------------------
-->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script src="script.js"></script>


</body>

</html>