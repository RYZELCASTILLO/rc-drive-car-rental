# RC Drive - Car Rental System

A complete PHP/MySQL car rental management system with separate admin and customer
portals. Built with vanilla PHP, PDO, and vanilla JavaScript.

---

## Features

### Customer Portal
- Register, login, and secure session handling
- Browse the fleet with category filter (Sedan / SUV / Sports)
- Book a vehicle with pick-up and return dates
- Provide driver's license and choose a payment method
- Live price calculator inside the booking modal
- View personal dashboard with rental history and status
- Send inquiries and chat with admin in real time
- Delete account with full data cascade

### Admin Portal (separate login)
- Dedicated admin sign-in page at `admin_login.php`
- Approve or reject customer bookings
- Live statistics — available cars, cars rented, total income, pending approvals
- Per-brand vehicle availability monitor
- Reply to client inquiries through a chat-style conversation thread
- Full bookings table with customer, license, payment, and status

### Business Rules
- Real-time availability per brand — 20 Sedans, 20 SUVs, 2 Sports
- **Server-side overbooking protection** — the system rejects a booking if all
  units of that vehicle are already approved for the requested dates
- **7-day maximum rental period** — enforced both client-side (JavaScript warning)
  and server-side (`rent_function.php`)
- **Availability decreases only when an admin approves a booking** — pending
  bookings do not affect stock
- Rejecting a pending booking leaves availability untouched

### Security
- Passwords hashed with bcrypt (`password_hash` / `password_verify`)
- 100% PDO prepared statements — zero SQL injection risk
- Session regeneration on login (`session_regenerate_id(true)`)
- Role-based access on every protected page
- Output escaped with `htmlspecialchars` on every echo
- Inputs validated and sanitized server-side

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8+ |
| Database | MySQL / MariaDB |
| Frontend | HTML5, CSS3, Bootstrap 5, Font Awesome |
| JavaScript | Vanilla JS |
| Fonts | Montserrat, Segoe UI |
| Server | Apache (XAMPP recommended) |

---

## Project Structure

```
rc_drive/
├── index.php                    Homepage, fleet, login/register modal, rent modal
├── dashboard.php                Full-page dashboard (admin + customer views)
├── admin_login.php              Dedicated admin login page
├── admin_login_function.php     Admin login handler
├── login_function.php           Customer login handler
├── register_function.php        Registration handler
├── logout.php                   Session termination
├── rent_function.php            Booking submission + overbooking protection
├── admin_action.php             Approve / reject bookings
├── vehicle_availability.php     JSON endpoint for unavailable dates
├── inquiry_function.php         Contact form handler
├── inquiry_reply.php            Conversation reply handler
├── delete_account.php           Account deletion
├── config.php                   DB connection, constants, stock helpers
├── validation.php               Input validation helpers
├── script.js                    Client-side behavior and validation
├── style.css                    Global stylesheet
└── README.md
```

---

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB 10.3+
- Apache (XAMPP, WAMP, MAMP, or Laragon)
- Modern web browser (Chrome, Firefox, Edge)

---

## Setup Instructions

### 1. Clone or Download the Repository

Place the project folder inside your local server's web root:

- XAMPP: `C:\xampp\htdocs\rc_drive`
- WAMP:  `C:\wamp64\www\rc_drive`
- MAMP:  `/Applications/MAMP/htdocs/rc_drive`

### 2. Create the Database

Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database
named exactly:

```
rc_drive
```

Then run the SQL below in the SQL tab to create the tables:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_name VARCHAR(100) NOT NULL,
    vehicle_brand VARCHAR(100) DEFAULT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    total_units INT DEFAULT 0,
    available_units INT DEFAULT 0,
    availability VARCHAR(20) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    driver_license VARCHAR(50) DEFAULT NULL,
    vehicle_id INT NOT NULL,
    vehicle_type VARCHAR(100) NOT NULL,
    rental_date DATE NOT NULL,
    return_date DATE NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    total_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicle_dates (vehicle_id, rental_date, return_date, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE TABLE inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE inquiry_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    sender_role ENUM('customer', 'admin') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inquiry (inquiry_id),
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
);

INSERT INTO vehicles (vehicle_name, vehicle_brand, price_per_day, total_units, available_units, availability) VALUES
('Executive Sedan', 'Sedan', 2500.00, 20, 20, 'available'),
('Family SUV', 'SUV', 3800.00, 20, 20, 'available'),
('Luxury Sports', 'Sports', 6500.00, 2, 2, 'available');
```

### 3. Configure Database Credentials

If your MySQL uses different credentials, edit `config.php`:

```php
$host = "localhost";
$dbname = "rc_drive";
$username = "root";
$password = "";
```

### 4. Administrator Account

An admin account is already set up in the demonstration database:

- **Username:** `rcdrive`
- **Email:**    `support@rcdrive.com`
- **Password:** `rYzeL2020@`

If you need to create a fresh admin account, register a normal account on
the homepage and then promote it in phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE username = 'NEW_USERNAME';
```

To generate a bcrypt hash for a new admin password, visit a temporary PHP file
containing:

```php
<?php echo password_hash('YourPasswordHere', PASSWORD_DEFAULT);
```

Then insert directly:

```sql
INSERT INTO users (username, email, password, role)
VALUES ('admin', 'admin@rcdrive.com', 'PASTE_HASH_HERE', 'admin');
```

### 5. Visit the Site

- Customer homepage: `http://localhost/rc_drive/index.php`
- Admin login:       `http://localhost/rc_drive/admin_login.php`

---

## Administrator Access

The system uses a separate admin login page, accessible only to accounts
with the `admin` role.

### Default Admin Account

- **Login URL:** `http://localhost/rc_drive/admin_login.php`
- **Username:**  `rcdrive`
- **Email:**     `support@rcdrive.com`
- **Password:**  `rYzeL2020@`

> ⚠️ **Security Note:** Change this password immediately after your first
> login in any production environment. The credentials above are provided
> for graders to access the admin panel during demonstration.

### Creating Additional Admin Accounts

Register a new account normally through the homepage, then in phpMyAdmin run:

```sql
UPDATE users SET role = 'admin' WHERE username = 'NEW_USERNAME';
```

That user can then sign in via `admin_login.php`.

---

## How the Booking Flow Works

1. Customer signs in and opens the rent modal on the homepage.
2. They select dates, enter phone number, driver's license, and payment method.
3. System validates everything client-side (dates, day limit, unavailable dates).
4. On submit, `rent_function.php` re-validates server-side and checks:
   - Vehicle is marked available
   - Total approved bookings < total units
   - No approved booking overlaps the requested dates
5. If all checks pass, the booking is saved with status `Pending`.
6. Customer sees their booking on their dashboard as **Pending**.
7. Admin signs in via `admin_login.php` and sees the pending booking.
8. Admin clicks **Approve** or **Reject**:
   - Approve → availability decreases by 1
   - Reject  → availability unchanged
9. Customer sees updated status on their dashboard.

---

## How the Inquiry Chat Works

1. Customer submits the contact form on the homepage.
2. Inquiry is saved and appears in both dashboards under "Conversations".
3. Either party can open the thread and send a reply.
4. Messages are stored in `inquiry_messages` and displayed as chat bubbles.
5. Admin replies appear on the right (yellow), customer replies on the left.

---

## Version Control

This project uses Git. Standard workflow:

```bash
git add .
git commit -m "Describe your change"
git push
```

The `.gitignore` excludes IDE folders, OS files, PHP logs, backup files, and
`vendor/` / `node_modules/` if present.

---

## License

This project is provided for educational use.

---

## Author

Ryzel Castillo
Web Development 1 — Midterm Project