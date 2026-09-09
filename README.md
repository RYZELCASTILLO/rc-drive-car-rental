@"
# RC Drive - Car Rental System

A comprehensive car rental management system built with PHP, MySQL, and Bootstrap.

## Features

- **User Authentication**: Secure login and registration with password hashing
- **Admin Dashboard**: Manage rentals, approve/reject bookings, view inquiries
- **Vehicle Booking**: Browse fleet, select dates, calculate costs
- **Customer Portal**: View booking history and status
- **Inquiry System**: Send and manage customer inquiries
- **Responsive Design**: Mobile-friendly interface with dark theme

## Technologies Used

- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5.3
- JavaScript (Vanilla)
- HTML5 & CSS3
- PDO for database interactions

## Installation

1. Clone the repository
2. Import the database schema (rc_drive.sql)
3. Configure database connection in config.php
4. Start your XAMPP/WAMP/LAMP server
5. Access at http://localhost/demo

## Database Schema

- `users`: User accounts (id, username, email, password, role)
- `rentals`: Booking records (id, user_id, vehicle_id, dates, total_fee, status)
- `vehicles`: Car inventory (id, vehicle_name, price_per_day, availability)
- `inquiries`: Customer messages (id, name, email, message, created_at)

## Security Features

- Password hashing with `password_hash()`
- PDO prepared statements to prevent SQL injection
- Session-based authentication
- Input validation and sanitization

## Author

RYZEL CASTILLO

## Course

Web Development 1 - Midterm Project
"@ | Out-File -FilePath .\README.md -Encoding utf8