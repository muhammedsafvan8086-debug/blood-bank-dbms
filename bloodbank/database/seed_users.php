<?php
/**
 * One-time seeder: creates the 3 demo login accounts with a properly
 * bcrypt-hashed password. Run this ONCE from the browser or CLI after
 * importing schema.sql:
 *
 *      php database/seed_users.php
 *   or visit http://localhost/bloodbank/database/seed_users.php
 *
 * All demo accounts use the password: Password123
 * Delete this file (or block it) after seeding on a real deployment.
 */

require_once __DIR__ . '/../config/db.php';

$password = password_hash('Password123', PASSWORD_DEFAULT);

$accounts = [
    ['System Admin',           'admin@bloodbank.local',   'admin',    null],
    ['City General Hospital',  'contact@citygeneral.in',  'hospital', 1],
    ['Rahul Kumar',            'rahul.k@example.com',     'donor',    1],
];

$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, password, role, linked_id, status)
     VALUES (?, ?, ?, ?, ?, 'Active')
     ON DUPLICATE KEY UPDATE password = VALUES(password)"
);

foreach ($accounts as [$name, $email, $role, $linkedId]) {
    $stmt->execute([$name, $email, $password, $role, $linkedId]);
    echo "Seeded: $email ($role)\n";
}

echo "\nDone. Demo login credentials (all roles): password = Password123\n";
echo "  Admin    -> admin@bloodbank.local\n";
echo "  Hospital -> contact@citygeneral.in\n";
echo "  Donor    -> rahul.k@example.com\n";
