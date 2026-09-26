<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Reports';
$activePage = 'reports.php';

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-3 months'));
$to   = $_GET['to'] ?? date('Y-m-d');

// Donor report
$donorsByGroup = $pdo->query("
    SELECT bg.blood_type, COUNT(d.donor_id) AS cnt
    FROM blood_groups bg LEFT JOIN donors d ON bg.group_id = d.group_id
    GROUP BY bg.blood_type ORDER BY bg.blood_type")->fetchAll();

$donorsByGender = $pdo->query("SELECT COALESCE(gender,'Unspecified') AS gender, COUNT(*) AS cnt FROM donors GROUP BY gender")->fetchAll();
$totalDonors = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();

$recentDonors = $pdo->query("SELECT name, phone FROM donors ORDER BY donor_id DESC LIMIT 5")->fetchAll();

// Stock report
$totalUnits = $pdo->query("SELECT COALESCE(SUM(available_units),0) FROM blood_stock WHERE expiry_date >= CURDATE()")->fetchColumn();
$lowStock = $pdo->query("
    SELECT bg.blood_type, SUM(bs.available_units) AS total
    FROM blood_stock bs JOIN blood_groups bg ON bs.group_id = bg.group_id
    WHERE bs.expiry_date >= CURDATE()
    GROUP BY bg.blood_type HAVING SUM(bs.available_units) < 10")->fetchAll();
$expiredCount = $pdo->query("SELECT COUNT(*) FROM blood_stock WHERE expiry_date < CURDATE()")->fetchColumn();

// Donation report (date filtered)
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(units),0) AS units FROM donations WHERE donation_date BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$donationSummary = $stmt->fetch();

$stmt = $pdo->prepare("SELECT donation_date, COUNT(*) AS cnt, SUM(units) AS units FROM donations WHERE donation_date BETWEEN ? AND ? GROUP BY donation_date ORDER BY donation_date DESC LIMIT 10");
$stmt->execute([$from, $to]);
$dailyDonations = $stmt->fetchAll();

// Request report
$requestStats = $pdo->query("SELECT status, COUNT(*) AS cnt FROM blood_requests GROUP BY status")->fetchAll();
$emergencyCount = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE priority = 'Emergency'")->fetchColumn();
$totalRequests = $pdo->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">Reports &amp; Analytics</h3>

    <form class="row g-2 mb-4" method="get">
      <div class="col-auto"><label class="form-label small mb-0">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
      <div class="col-auto"><label class="form-label small mb-0">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
      <div class="col-auto d-flex align-items-end"><button class="btn btn-bb">Apply</button></div>
    </form>

    <div class="row g-4">
      <!-- Donor Report -->
      <div class="col-md-6">
        <div class="bb-card p-3 h-100">
          <h5><i class="fa-solid fa-users text-danger"></i> Donor Report</h5>
          <p class="mb-1">Total Donors: <strong><?= (int)$totalDonors ?></strong></p>
          <table class="table table-sm mb-2">
            <thead><tr><th>Blood Group</th><th>Donors</th></tr></thead>
            <tbody><?php foreach ($donorsByGroup as $r): ?><tr><td><?= e($r['blood_type']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody>
          </table>
          <p class="small mb-1 fw-semibold">By Gender:</p>
          <ul class="small">
            <?php foreach ($donorsByGender as $g): ?><li><?= e($g['gender']) ?>: <?= (int)$g['cnt'] ?></li><?php endforeach; ?>
          </ul>
          <p class="small mb-1 fw-semibold">Recent Donors:</p>
          <ul class="small mb-0">
            <?php foreach ($recentDonors as $d): ?><li><?= e($d['name']) ?> — <?= e($d['phone']) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>

      <!-- Stock Report -->
      <div class="col-md-6">
        <div class="bb-card p-3 h-100">
          <h5><i class="fa-solid fa-flask text-danger"></i> Stock Report</h5>
          <p class="mb-1">Total Available Units: <strong><?= (int)$totalUnits ?></strong></p>
          <p class="mb-2">Expired Stock Records: <strong class="text-danger"><?= (int)$expiredCount ?></strong></p>
          <p class="small fw-semibold mb-1">Low-Stock Groups (&lt; 10 units):</p>
          <?php if (empty($lowStock)): ?><p class="small text-muted">None &mdash; all groups healthy.</p>
          <?php else: ?>
            <ul class="small mb-0">
              <?php foreach ($lowStock as $l): ?><li><?= e($l['blood_type']) ?>: <?= (int)$l['total'] ?> units <span class="badge badge-low ms-1">Low</span></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <!-- Donation Report -->
      <div class="col-md-6">
        <div class="bb-card p-3 h-100">
          <h5><i class="fa-solid fa-hand-holding-droplet text-danger"></i> Donation Report</h5>
          <p class="mb-1">Donations in range: <strong><?= (int)$donationSummary['cnt'] ?></strong></p>
          <p class="mb-2">Total Units Collected: <strong><?= (int)$donationSummary['units'] ?></strong></p>
          <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>Donations</th><th>Units</th></tr></thead>
            <tbody>
              <?php foreach ($dailyDonations as $d): ?>
                <tr><td><?= e(date('d-m-Y', strtotime($d['donation_date']))) ?></td><td><?= (int)$d['cnt'] ?></td><td><?= (int)$d['units'] ?></td></tr>
              <?php endforeach; ?>
              <?php if (empty($dailyDonations)): ?><tr><td colspan="3" class="text-muted small">No donations in this range.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Request Report -->
      <div class="col-md-6">
        <div class="bb-card p-3 h-100">
          <h5><i class="fa-solid fa-triangle-exclamation text-danger"></i> Request Report</h5>
          <p class="mb-1">Total Requests: <strong><?= (int)$totalRequests ?></strong></p>
          <p class="mb-2">Emergency Requests: <strong class="text-danger"><?= (int)$emergencyCount ?></strong></p>
          <table class="table table-sm mb-0">
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody><?php foreach ($requestStats as $r): ?><tr><td><?= e($r['status']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
