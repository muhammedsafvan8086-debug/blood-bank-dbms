# 🩸 Blood Bank Management System (PHP + MySQL)

A full-stack, database-driven Blood Bank Management System built with **PHP (PDO/MySQL)**,
Bootstrap 5, and Chart.js. Covers donor management, blood stock, hospitals, donations,
blood requests with an approve/reject workflow, role-based dashboards, reports, and audit logs.

## Tech Stack
- **Backend:** PHP 8+ (plain PHP, PDO prepared statements — no framework required)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, Bootstrap 5 (CDN), Chart.js (CDN), vanilla JS

## Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- A local server: XAMPP / WAMP / MAMP / `php -S`

## Setup

1. **Place the project** in your web server's document root, e.g.:
   - XAMPP: `htdocs/bloodbank`
   - WAMP: `www/bloodbank`

2. **Create the database** — import the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   This creates the `blood_bank` database, all tables, and realistic sample data
   (donors, hospitals, blood stock at various levels, donations, and requests).

3. **Configure the DB connection** in `config/db.php` if your MySQL username/password
   differ from the defaults (`root` / empty password).

4. **Seed the demo login accounts** (required — passwords must be hashed by PHP):
   ```bash
   php database/seed_users.php
   ```
   Or visit `http://localhost/bloodbank/database/seed_users.php` in your browser once.

   This creates 3 demo accounts, all with password **`Password123`**:
   | Role     | Email                      |
   |----------|----------------------------|
   | Admin    | admin@bloodbank.local      |
   | Hospital | contact@citygeneral.in     |
   | Donor    | rahul.k@example.com        |

   For safety, delete or rename `database/seed_users.php` after seeding on a real deployment.

5. **Visit the site**: `http://localhost/bloodbank/`

## Project Structure
```
bloodbank/
├── config/db.php              # PDO connection
├── includes/                  # auth.php, header/footer/sidebar templates
├── database/schema.sql        # full schema + sample data
├── database/seed_users.php    # one-time demo login seeder
├── admin/                     # admin dashboard, donors, hospitals, stock,
│                               # donations, requests, reports
├── hospital/                  # hospital dashboard, new/my requests, profile
├── donor/                     # donor dashboard, profile, donation history
├── assets/css/style.css       # modern healthcare theme
├── assets/js/script.js
├── index.php                  # public home page
├── blood_availability.php     # public availability search
├── login.php / logout.php
├── register_donor.php
└── register_hospital.php
```

## What's implemented
- **Auth & roles**: Admin / Hospital / Donor, session-based, bcrypt-hashed passwords,
  CSRF-protected forms, role-guarded pages (403 on unauthorized access).
- **Donors**: register, list/search/filter, edit, delete, per-donor donation history.
- **Hospitals**: register, list/search, edit, delete, per-hospital request history, self-service profile.
- **Blood stock**: add/edit/remove, auto status (Available / Low Stock / Out of Stock),
  expiry tracking, "remove expired stock" action.
- **Donations**: recording a donation automatically increases the matching blood-stock
  lot, updates the donor's last-donation date, and writes an audit-log entry.
- **Blood requests**: hospital submits with priority (Normal/Urgent/Emergency);
  admin approve/reject/fulfill; approval deducts stock oldest-expiry-first (FEFO) and
  blocks approval if stock is insufficient.
- **Dashboards**: admin (stats, charts, expiring-soon list, emergency queue),
  hospital (availability + own requests), donor (profile + donation stats).
- **Reports**: donor, stock, donation (date-filterable), and request reports.
- **Notifications & audit log** tables, populated on key actions.
- **Validation & error handling** on every form (server-side), no raw DB errors shown to users.
- **Responsive UI**: sidebar collapses on mobile, cards stack, tables scroll horizontally.

## Notes for a DBMS course submission
- All SQL is parameterized (PDO prepared statements) — no SQL injection.
- Schema follows 1NF/2NF/3NF as described in the accompanying PRD (blood group,
  donor, hospital, stock, donation, and request are separate normalized entities).
- This is an academic/demo project. It does not replace real blood-bank safety,
  compatibility testing, or regulatory procedures.
