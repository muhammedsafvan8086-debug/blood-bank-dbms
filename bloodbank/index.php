<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Home';

// Live stats from the database (never hard-coded)
$totalDonors    = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
$totalUnits     = $pdo->query("SELECT COALESCE(SUM(available_units),0) FROM blood_stock WHERE expiry_date >= CURDATE()")->fetchColumn();
$totalHospitals = $pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'Active'")->fetchColumn();
$totalDonations = $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();

// Quick availability snapshot
$stock = $pdo->query("
    SELECT bg.blood_type, COALESCE(SUM(bs.available_units),0) AS total_units
    FROM blood_groups bg
    LEFT JOIN blood_stock bs ON bs.group_id = bg.group_id AND bs.expiry_date >= CURDATE()
    GROUP BY bg.blood_type
    ORDER BY bg.blood_type
")->fetchAll();

function stock_status(int $units): array {
    if ($units <= 0) return ['Out of Stock', 'badge-out'];
    if ($units < 10) return ['Low Stock', 'badge-low'];
    return ['Available', 'badge-available'];
}

include __DIR__ . '/includes/header.php';
?>

<section class="bb-hero">
  <div class="container text-center">
    <h1>Every Drop Counts. Every Life Matters.</h1>
    <p class="lead mx-auto" style="max-width:640px;">Manage blood donations, monitor blood availability, and respond to emergency blood requests efficiently.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
      <a href="/bloodbank/blood_availability.php" class="btn btn-light btn-lg"><i class="fa-solid fa-magnifying-glass"></i> Check Blood Availability</a>
      <a href="/bloodbank/register_donor.php" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-heart"></i> Register as Donor</a>
      <a href="/bloodbank/login.php" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-hospital"></i> Hospital Login</a>
    </div>
  </div>
</section>

<div class="container my-5">
  <div class="row g-4">
    <div class="col-6 col-md-3">
      <div class="bb-stat"><div class="num"><?= number_format((int)$totalDonors) ?>+</div><div class="label">Registered Donors</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="bb-stat"><div class="num"><?= number_format((int)$totalUnits) ?></div><div class="label">Available Units</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="bb-stat"><div class="num"><?= number_format((int)$totalHospitals) ?></div><div class="label">Partner Hospitals</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="bb-stat"><div class="num"><?= number_format((int)$totalDonations) ?></div><div class="label">Successful Donations</div></div>
    </div>
  </div>
</div>

<div class="container my-5">
  <h4 class="mb-3">Blood Availability Snapshot</h4>
  <div class="row g-3">
    <?php foreach ($stock as $row): [$label, $class] = stock_status((int)$row['total_units']); ?>
      <div class="col-6 col-md-3">
        <div class="bb-card p-3 text-center">
          <div class="fs-3 fw-bold text-danger"><?= e($row['blood_type']) ?></div>
          <div class="fs-5 fw-semibold"><?= (int)$row['total_units'] ?> Units</div>
          <span class="badge <?= $class ?>"><?= $label ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-4">
    <a href="/bloodbank/blood_availability.php" class="btn btn-bb">View Full Availability &amp; Search</a>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
