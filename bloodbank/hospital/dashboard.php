<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('hospital');
$pageTitle = 'Hospital Dashboard';
$activePage = 'dashboard.php';
$hospitalId = $_SESSION['linked_id'];

$stmt = $pdo->prepare("SELECT * FROM hospitals WHERE hospital_id = ?");
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE hospital_id = ? AND status = 'Pending'");
$stmt->execute([$hospitalId]);
$pending = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE hospital_id = ? AND status = 'Approved'");
$stmt->execute([$hospitalId]);
$approved = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE hospital_id = ?");
$stmt->execute([$hospitalId]);
$total = $stmt->fetchColumn();

$stock = $pdo->query("
    SELECT bg.blood_type, COALESCE(SUM(bs.available_units),0) AS total_units
    FROM blood_groups bg LEFT JOIN blood_stock bs ON bs.group_id = bg.group_id AND bs.expiry_date >= CURDATE()
    GROUP BY bg.blood_type ORDER BY bg.blood_type")->fetchAll();

$stmt = $pdo->prepare("SELECT br.*, bg.blood_type FROM blood_requests br JOIN blood_groups bg ON br.group_id = bg.group_id
                        WHERE br.hospital_id = ? ORDER BY br.request_date DESC LIMIT 5");
$stmt->execute([$hospitalId]);
$recent = $stmt->fetchAll();

function stock_status(int $units): array {
    if ($units <= 0) return ['Out', 'badge-out'];
    if ($units < 10) return ['Low', 'badge-low'];
    return ['OK', 'badge-available'];
}
$badgeMap = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected', 'Fulfilled' => 'badge-fulfilled', 'Cancelled' => 'badge-out'];

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-1">Welcome, <?= e($hospital['hospital_name']) ?></h3>
    <p class="text-muted mb-4">Hospital Dashboard</p>

    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$total ?></div><div class="label">Total Requests</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$pending ?></div><div class="label">Pending</div></div></div>
      <div class="col-6 col-md-3"><div class="bb-stat"><div class="num"><?= (int)$approved ?></div><div class="label">Approved</div></div></div>
      <div class="col-6 col-md-3"><a href="new_request.php" class="text-decoration-none"><div class="bb-stat"><div class="num"><i class="fa-solid fa-plus"></i></div><div class="label">New Request</div></div></a></div>
    </div>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="bb-card p-3">
          <h6 class="mb-3">Current Blood Availability</h6>
          <div class="row g-2">
            <?php foreach ($stock as $s): [$label, $class] = stock_status((int)$s['total_units']); ?>
              <div class="col-3 text-center">
                <div class="fw-bold"><?= e($s['blood_type']) ?></div>
                <div class="small"><?= (int)$s['total_units'] ?> u</div>
                <span class="badge <?= $class ?>"><?= $label ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="bb-card p-3">
          <h6 class="mb-3">Recent Requests</h6>
          <?php foreach ($recent as $r): ?>
            <div class="d-flex justify-content-between border-bottom py-2 small">
              <div><?= e($r['blood_type']) ?> &times; <?= (int)$r['units_required'] ?> — <?= e(date('d M', strtotime($r['request_date']))) ?></div>
              <span class="badge <?= $badgeMap[$r['status']] ?? 'bg-secondary' ?>"><?= e($r['status']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (empty($recent)): ?><p class="text-muted small mb-0">No requests yet.</p><?php endif; ?>
          <a href="my_requests.php" class="d-block mt-3 small">View all requests &rarr;</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
