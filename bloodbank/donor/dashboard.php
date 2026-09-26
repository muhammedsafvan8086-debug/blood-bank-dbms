<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('donor');
$pageTitle = 'Donor Dashboard';
$activePage = 'dashboard.php';
$donorId = $_SESSION['linked_id'];

$stmt = $pdo->prepare("SELECT d.*, bg.blood_type FROM donors d JOIN blood_groups bg ON d.group_id = bg.group_id WHERE d.donor_id = ?");
$stmt->execute([$donorId]);
$donor = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(units),0) AS units FROM donations WHERE donor_id = ?");
$stmt->execute([$donorId]);
$donationStats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY donation_date DESC LIMIT 3");
$stmt->execute([$donorId]);
$recentDonations = $stmt->fetchAll();

// Eligible to donate again after 90 days
$eligibleDate = $donor['last_donation_date'] ? date('d-m-Y', strtotime($donor['last_donation_date'] . ' +90 days')) : null;
$isEligible = !$donor['last_donation_date'] || strtotime($donor['last_donation_date'] . ' +90 days') <= time();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-1">Welcome, <?= e($donor['name']) ?></h3>
    <p class="text-muted mb-4">Donor Dashboard</p>

    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= e($donor['blood_type']) ?></div><div class="label">Blood Group</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$donationStats['cnt'] ?></div><div class="label">Total Donations</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$donationStats['units'] ?></div><div class="label">Units Donated</div></div></div>
      <div class="col-6 col-md-3">
        <div class="bb-stat">
          <div class="num"><?= $donor['last_donation_date'] ? e(date('d-m-Y', strtotime($donor['last_donation_date']))) : 'Never' ?></div>
          <div class="label">Last Donation</div>
        </div>
      </div>
    </div>

    <div class="bb-card p-3 mb-4">
      <?php if ($isEligible): ?>
        <div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check"></i> You are eligible to donate blood again. Thank you for being a lifesaver!</div>
      <?php else: ?>
        <div class="alert alert-warning mb-0"><i class="fa-solid fa-clock"></i> You'll be eligible to donate again on <strong><?= e($eligibleDate) ?></strong> (90-day recovery period).</div>
      <?php endif; ?>
    </div>

    <div class="bb-card p-3">
      <h6 class="mb-3">Recent Donations</h6>
      <?php foreach ($recentDonations as $d): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span><?= e(date('d-m-Y', strtotime($d['donation_date']))) ?> — <?= e($d['collection_location']) ?></span>
          <span class="fw-semibold"><?= (int)$d['units'] ?> unit(s)</span>
        </div>
      <?php endforeach; ?>
      <?php if (empty($recentDonations)): ?><p class="text-muted small mb-0">No donation history yet.</p><?php endif; ?>
      <a href="donation_history.php" class="d-block mt-3 small">View full history &rarr;</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
