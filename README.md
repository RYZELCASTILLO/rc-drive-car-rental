# RC Drive - Car Rental System

A PHP/MySQL car rental management system with admin and customer roles.

## Features
- Customer registration, login, and password hashing (bcrypt)
- Browse fleet, filter by type (Sedan / SUV / Sports)
- Book a vehicle with driver's license and payment method
- Real-time availability per brand (20 Sedans / 20 SUVs / 2 Sports)
- Server-side overbooking protection
- 7-day maximum rental limit
- Admin dashboard: approve / reject, statistics, per-brand stock
- Two-way inquiry conversation system
- Secure PDO queries with prepared statements

## Requirements
- PHP 8+
- MySQL / MariaDB
- Apache (XAMPP recommended)

## Setup
1. Clone this repo into `htdocs/`
2. Create database `rc_drive` and import the SQL schema
3. Update credentials in `config.php` if needed
4. Visit `http://localhost/rc_drive/`

## Default Admin
Username: `admin`
Password: `Admin@123` *(change this after first login)*