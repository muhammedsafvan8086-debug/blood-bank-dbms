<?php
// Expects optional $pageTitle to be set before include.
$pageTitle = $pageTitle ?? 'Blood Bank Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | Blood Bank</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="/bloodbank/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bb-navbar sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/bloodbank/index.php">
      <i class="fa-solid fa-droplet"></i> BloodBank<span class="text-danger">+</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/bloodbank/index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/bloodbank/blood_availability.php">Blood Availability</a></li>
        <?php if (!is_logged_in()): ?>
          <li class="nav-item"><a class="nav-link" href="/bloodbank/register_donor.php">Become a Donor</a></li>
          <li class="nav-item"><a class="nav-link" href="/bloodbank/register_hospital.php">Register Hospital</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php if (is_logged_in()): ?>
          <li class="nav-item">
            <a class="nav-link" href="/bloodbank/<?= e(current_role()) ?>/dashboard.php">
              <i class="fa-solid fa-gauge"></i> <?= e($_SESSION['name']) ?> (<?= e(ucfirst(current_role())) ?>)
            </a>
          </li>
          <li class="nav-item"><a class="nav-link" href="/bloodbank/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/bloodbank/login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="container mt-3">
    <div class="alert alert-<?= e($_SESSION['flash']['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($_SESSION['flash']['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
