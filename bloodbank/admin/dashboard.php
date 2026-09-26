<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Admin Dashboard';
$activePage = 'dashboard.php';

$totalDonors    = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
$totalUnits     = $pdo->query("SELECT COALESCE(SUM(available_units),0) FROM blood_stock WHERE expiry_date >= CURDATE()")->fetchColumn();
$totalHospitals = $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
$pendingReqs    = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'Pending'")->fetchColumn();

$stockByGroup = $pdo->query("
    SELECT bg.blood_type, COALESCE(SUM(bs.available_units),0) AS total
    FROM blood_groups bg
    LEFT JOIN blood_stock bs ON bs.group_id = bg.group_id AND bs.expiry_date >= CURDATE()
    GROUP BY bg.blood_type ORDER BY bg.blood_type
")->fetchAll();

$monthlyDonations = $pdo->query("
    SELECT DATE_FORMAT(donation_date, '%b %Y') AS ym, SUM(units) AS total
    FROM donations
    GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
    ORDER BY MIN(donation_date)
")->fetchAll();

$requestsByStatus = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM blood_requests GROUP BY status
")->fetchAll();

$expiringSoon = $pdo->query("
    SELECT bg.blood_type, bs.available_units, bs.expiry_date,
           DATEDIFF(bs.expiry_date, CURDATE()) AS days_left
    FROM blood_stock bs
    JOIN blood_groups bg ON bs.group_id = bg.group_id
    WHERE bs.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY bs.expiry_date ASC
")->fetchAll();

$emergencyReqs = $pdo->query("
    SELECT br.*, h.hospital_name, bg.blood_type
    FROM blood_requests br
    JOIN hospitals h ON br.hospital_id = h.hospital_id
    JOIN blood_groups bg ON br.group_id = bg.group_id
    WHERE br.priority = 'Emergency' AND br.status = 'Pending'
    ORDER BY br.request_date DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">Admin Dashboard</h3>

    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$totalDonors ?></div><div class="label">Total Donors</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$totalUnits ?></div><div class="label">Blood Units</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$totalHospitals ?></div><div class="label">Hospitals</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$pendingReqs ?></div><div class="label">Pending Requests</div></div></div>
    </div>

    <?php if ($emergencyReqs): ?>
      <div class="bb-card p-3 mb-4 border-danger">
        <h5 class="text-danger mb-3"><i class="fa-solid fa-truck-medical"></i> Emergency Requests Awaiting Review</h5>
        <?php foreach ($emergencyReqs as $r): ?>
          <div class="d-flex justify-content-between align-items-center p-2 emergency-row rounded mb-2">
            <div>
              <span class="badge badge-emergency">EMERGENCY</span>
              <strong><?= e($r['hospital_name']) ?></strong> needs <strong><?= (int)$r['units_required'] ?> unit(s)</strong> of <strong><?= e($r['blood_type']) ?></strong>
              <div class="small text-muted">Requested <?= e(date('d M, h:i A', strtotime($r['request_date']))) ?></div>
            </div>
            <a href="blood_requests.php" class="btn btn-sm btn-bb">Review</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="bb-card p-3">
          <h6 class="mb-3">Blood Stock by Group</h6>
          <canvas id="stockChart" height="200"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="bb-card p-3">
          <h6 class="mb-3">Blood Requests by Status</h6>
          <canvas id="requestChart" height="200"></canvas>
        </div>
      </div>
      <div class="col-12">
        <div class="bb-card p-3">
          <h6 class="mb-3">Monthly Donations (Units)</h6>
          <canvas id="donationChart" height="90"></canvas>
        </div>
      </div>
    </div>

    <div class="bb-card p-3">
      <h6 class="mb-3"><i class="fa-solid fa-hourglass-half text-warning"></i> Blood Units Expiring Soon (next 7 days)</h6>
      <?php if (empty($expiringSoon)): ?>
        <p class="text-muted mb-0">No units expiring in the next 7 days.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Blood Group</th><th>Units</th><th>Expiry Date</th><th>Days Remaining</th></tr></thead>
            <tbody>
              <?php foreach ($expiringSoon as $row): ?>
                <tr>
                  <td><span class="badge bg-secondary"><?= e($row['blood_type']) ?></span></td>
                  <td><?= (int)$row['available_units'] ?></td>
                  <td><?= e(date('d-m-Y', strtotime($row['expiry_date']))) ?></td>
                  <td><span class="badge badge-low"><?= (int)$row['days_left'] ?> days</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('stockChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($stockByGroup, 'blood_type')) ?>,
    datasets: [{ label: 'Units', data: <?= json_encode(array_map('intval', array_column($stockByGroup, 'total'))) ?>, backgroundColor: '#c0263a' }]
  },
  options: { plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('requestChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($requestsByStatus, 'status')) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($requestsByStatus, 'cnt'))) ?>,
      backgroundColor: ['#6b7785','#1f9d55','#c0263a','#2563eb','#d98c0e'] }]
  }
});

new Chart(document.getElementById('donationChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($monthlyDonations, 'ym')) ?>,
    datasets: [{ label: 'Units Donated', data: <?= json_encode(array_map('intval', array_column($monthlyDonations, 'total'))) ?>,
      borderColor: '#10233d', backgroundColor: 'rgba(16,35,61,0.1)', fill: true, tension: 0.3 }]
  }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
