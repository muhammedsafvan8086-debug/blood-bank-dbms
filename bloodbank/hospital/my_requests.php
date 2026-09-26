<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('hospital');
$pageTitle = 'My Requests';
$activePage = 'my_requests.php';
$hospitalId = $_SESSION['linked_id'];

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT br.*, bg.blood_type FROM blood_requests br JOIN blood_groups bg ON br.group_id = bg.group_id WHERE br.hospital_id = ?";
$params = [$hospitalId];
if ($statusFilter !== '') { $sql .= " AND br.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY br.request_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$badgeMap = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected', 'Fulfilled' => 'badge-fulfilled', 'Cancelled' => 'badge-out'];

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="mb-0">My Requests</h3>
      <a href="new_request.php" class="btn btn-bb"><i class="fa-solid fa-plus"></i> New Request</a>
    </div>

    <form class="row g-2 mb-3" method="get">
      <div class="col-auto">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach (['Pending','Approved','Rejected','Fulfilled','Cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Request ID</th><th>Blood Group</th><th>Units</th><th>Priority</th><th>Requested</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
              <tr class="<?= $r['priority'] === 'Emergency' && $r['status'] === 'Pending' ? 'emergency-row' : '' ?>">
                <td>#<?= (int)$r['request_id'] ?></td>
                <td><span class="badge bg-secondary"><?= e($r['blood_type']) ?></span></td>
                <td><?= (int)$r['units_required'] ?></td>
                <td><span class="badge <?= $r['priority'] === 'Emergency' ? 'badge-emergency' : ($r['priority'] === 'Urgent' ? 'badge-low' : 'bg-secondary') ?>"><?= e($r['priority']) ?></span></td>
                <td class="small"><?= e(date('d M Y, h:i A', strtotime($r['request_date']))) ?></td>
                <td><span class="badge <?= $badgeMap[$r['status']] ?? 'bg-secondary' ?>"><?= e($r['status']) ?></span></td>
                <td class="small text-muted"><?= e($r['remarks']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="7" class="text-center text-muted py-4">No requests found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
