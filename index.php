<?php
session_start();
require_once 'config.php';

/*
|--------------------------------------------------------------------------
| ADMIN REDIRECT
|--------------------------------------------------------------------------
| If a logged-in ADMIN visits the customer homepage, send them to their
| dashboard. Customers are NOT affected and can browse freely.
*/

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header("Location: dashboard.php");
    exit;
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


    <style>
        .search-static-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: #212529;
            letter-spacing: 0.5px;
        }

        .search-action-text {
            padding: 22px 30px;
            font-size: 0.95rem;
            font-weight: 900;
            color: #212529;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        .contact-box {
            display: flex !important;
            align-items: center;
            gap: 22px;
            text-align: left !important;
            padding: 26px 28px !important;
            min-height: 128px;
        }

        .contact-icon {
            flex: 0 0 58px;
            width: 58px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FCC113;
            font-size: 2rem;
        }

        .contact-info {
            min-width: 0;
            flex: 1;
        }

        .contact-info h4 {
            margin: 0 0 8px;
            color: #FCC113;
            font-size: 0.85rem;
            font-weight: 900;
            letter-spacing: 0.6px;
        }

        .contact-info p {
            margin: 0 0 5px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.35;
            word-break: normal;
        }

        .contact-info small {
            display: block;
            color: #aeb4bd;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .search-box {
                display: block;
            }

            .search-field,
            .search-action-text {
                border-right: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.15);
            }

            .cta-banner {
                display: block;
            }

            .contact-box {
                align-items: center;
            }

            .contact-info p {
                font-size: 0.95rem;
            }
        }
    </style>

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

            <span class="badge bg-info me-1">
                CUSTOMER
            </span>

            <a
                href="dashboard.php"
                class="btn btn-outline-warning btn-sm py-2 px-3 fw-bold text-decoration-none"
            >

                <i class="fa-solid fa-gauge me-1"></i>

                <?php
                echo htmlspecialchars(
                    $_SESSION['username']
                );
                ?>

            </a>


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

                <h3>42</h3>

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
| YELLOW DESIGN BANNER
|--------------------------------------------------------------------------
-->
<div class="search-bar-container">
    <div class="search-box">

        <div class="search-field">
            <label>PICK UP LOCATION</label>
            <div class="search-static-text">CITY, AIRPORT, ADDRESS</div>
        </div>

        <div class="search-field">
            <label>PICK UP DATE</label>
            <div class="search-static-text">DD / MM / YY</div>
        </div>

        <div class="search-field">
            <label>RETURN DATE</label>
            <div class="search-static-text">DD / MM / YY</div>
        </div>

        <div class="search-action-text">
            SEARCH
        </div>

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
                    onclick="openRentModal('Executive Sedan', 2500, 20)"
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
                    onclick="openRentModal('Family SUV', 3800, 20)"
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
                    onclick="openRentModal('Luxury Sports', 6500, 2)"
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
| LOGIN / REGISTER MODAL
|--------------------------------------------------------------------------
-->

<div
    class="modal fade"
    id="loginModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered modal-lg"
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


                <?php else: ?>


                    <div class="text-center py-4">

                        <i
                            class="fa-solid fa-circle-check text-warning"
                            style="font-size:3rem;"
                        ></i>

                        <h5 class="mt-3">

                            You are logged in as

                            <span class="text-warning">

                                <?= htmlspecialchars($_SESSION['username']) ?>

                            </span>

                        </h5>

                        <p class="text-muted small">
                            Visit your dashboard to manage your bookings.
                        </p>

                        <a
                            href="dashboard.php"
                            class="btn btn-warning fw-bold mt-2"
                        >
                            <i class="fa-solid fa-gauge me-1"></i>
                            GO TO DASHBOARD
                        </a>

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


                    <input
                        type="hidden"
                        name="vehicle_type"
                        id="selectedCarName"
                    >


                    <input
                        type="hidden"
                        id="selectedCarRate"
                    >


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


                    <div class="mb-3">

                        <label class="form-label">
                            Driver's License Number
                        </label>


                        <input
                            type="text"
                            name="driver_license"
                            id="rentDriverLicense"
                            class="form-control bg-secondary text-white border-0"
                            placeholder="e.g. N01-23-456789"
                            required
                        >

                    </div>


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


                    <div
                        class="p-3 bg-secondary rounded mb-3"
                    >

                        <div
                            class="d-flex justify-content-between align-items-center mb-1"
                        >

                            <span>
                                Duration:
                                <small class="text-muted" style="font-size:0.75rem;">
                                    (max <?= MAX_RENTAL_DAYS ?> days)
                                </small>
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
| TESTIMONIALS
|--------------------------------------------------------------------------
-->
<section class="testimonial-section">
    <span class="sub-heading-center">
        — TESTIMONIAL —
    </span>

    <h2>
        WHAT CLIENTS <span>SAY</span>
    </h2>

    <div class="testimonial-grid">

        <div class="testimonial-card">
            <div class="stars">★★★★★</div>

            <p>
                “RC Drive made my Dumaguete business trip
                seamless. The car was spotless and pickup was
                instant. Will definitely use again.”
            </p>

            <div class="client-info">
                <div class="avatar">M</div>

                <div>
                    <h4>Marcos Santos</h4>
                    <small>Business Traveler</small>
                </div>
            </div>
        </div>

        <div class="testimonial-card">
            <div class="stars">★★★★★</div>

            <p>
                “Rented an SUV for our Negros Oriental road
                trip, spacious, clean, and the staff were
                incredibly helpful. Best rental experience.”
            </p>

            <div class="client-info">
                <div class="avatar">A</div>

                <div>
                    <h4>Ana Reyes</h4>
                    <small>Family Trip</small>
                </div>
            </div>
        </div>

        <div class="testimonial-card">
            <div class="stars">★★★★★</div>

            <p>
                “Competitive pricing and a great fleet. I always
                find exactly what I need for my road trips
                around Negros with RC Drive.”
            </p>

            <div class="client-info">
                <div class="avatar">D</div>

                <div>
                    <h4>David Lim</h4>
                    <small>Weekend Adventurer</small>
                </div>
            </div>
        </div>

    </div>
</section>


<!--
|--------------------------------------------------------------------------
| CTA BANNER
|--------------------------------------------------------------------------
-->
<section class="cta-banner">

    <div>
        <h2>READY TO HIT THE ROAD?</h2>

        <p>
            Book now and get your first day on weekend rentals.
            Limited offer.
        </p>
    </div>


</section>


<!--
|--------------------------------------------------------------------------
| CONTACT
|--------------------------------------------------------------------------
-->
<section
    id="contact"
    class="contact-section"
>

    <span class="sub-heading-center">
        — GET IN TOUCH —
    </span>

    <h2 class="mt-3">
        WE'RE HERE TO <span>HELP</span>
    </h2>

    <div class="contact-cards mt-5">

        <div class="contact-box">
            <div class="contact-icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>

            <div class="contact-info">
                <h4>OUR LOCATION</h4>
                <p>Dumaguete City, Negros Oriental</p>
                <small>Philippines</small>
            </div>
        </div>

        <div class="contact-box">
            <div class="contact-icon">
                <i class="fa-solid fa-phone"></i>
            </div>

            <div class="contact-info">
                <h4>PHONE NUMBER</h4>
                <p>+63 930 222 9696</p>
                <small>Mon - Sun, 7:00 AM - 9:00 PM</small>
            </div>
        </div>

        <div class="contact-box">
            <div class="contact-icon">
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="contact-info">
                <h4>EMAIL ADDRESS</h4>
                <p>support@rcdrive.com</p>
                <small>We reply within 2 hours</small>
            </div>
        </div>

    </div>

    <button
        type="button"
        class="btn-yellow mt-4"
        data-bs-toggle="modal"
        data-bs-target="#inquiryModal"
    >
        SEND INQUIRY
    </button>

</section>


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

            |

            <a href="admin_login.php">
                Admin Portal
            </a>

        </div>

    </div>


</footer>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script src="script.js"></script>


</body>

</html>