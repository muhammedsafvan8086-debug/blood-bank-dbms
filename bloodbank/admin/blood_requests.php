<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Blood Requests';
$activePage = 'blood_requests.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action    = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT br.*, h.hospital_name, bg.blood_type FROM blood_requests br
                            JOIN hospitals h ON br.hospital_id = h.hospital_id
                            JOIN blood_groups bg ON br.group_id = bg.group_id
                            WHERE br.request_id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        $errors[] = 'Request not found.';
    } elseif ($req['status'] !== 'Pending') {
        $errors[] = 'This request has already been processed.';
    } elseif ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(available_units),0) FROM blood_stock WHERE group_id = ? AND expiry_date >= CURDATE()");
        $stmt->execute([$req['group_id']]);
        $available = (int)$stmt->fetchColumn();

        if ($available < $req['units_required']) {
            $errors[] = "Insufficient stock: only $available unit(s) of {$req['blood_type']} available, {$req['units_required']} required.";
        } else {
            $pdo->beginTransaction();
            try {
                // Deduct from oldest-expiring stock first (FEFO)
                $remaining = (int)$req['units_required'];
                $stmt = $pdo->prepare("SELECT stock_id, available_units FROM blood_stock WHERE group_id = ? AND available_units > 0 AND expiry_date >= CURDATE() ORDER BY expiry_date ASC FOR UPDATE");
                $stmt->execute([$req['group_id']]);
                foreach ($stmt->fetchAll() as $lot) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, (int)$lot['available_units']);
                    $pdo->prepare("UPDATE blood_stock SET available_units = available_units - ? WHERE stock_id = ?")->execute([$deduct, $lot['stock_id']]);
                    $remaining -= $deduct;
                }
                $pdo->prepare("UPDATE blood_requests SET status = 'Approved' WHERE request_id = ?")->execute([$requestId]);
                log_action($pdo, 'Approve Request', "Approved request #$requestId: {$req['units_required']} unit(s) {$req['blood_type']} to {$req['hospital_name']}");
                notify($pdo, null, "Your blood request #$requestId has been approved.", null);
                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Request #$requestId approved and stock updated."];
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Failed to approve request. Please try again.';
            }
        }
    } elseif ($action === 'reject') {
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $pdo->prepare("UPDATE blood_requests SET status = 'Rejected', remarks = ? WHERE request_id = ?");
        $stmt->execute([$remarks ?: $req['remarks'], $requestId]);
        log_action($pdo, 'Reject Request', "Rejected request #$requestId" . ($remarks ? ": $remarks" : ''));
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Request #$requestId rejected."];
    } elseif ($action === 'fulfill') {
        $pdo->prepare("UPDATE blood_requests SET status = 'Fulfilled' WHERE request_id = ?")->execute([$requestId]);
        log_action($pdo, 'Fulfill Request', "Marked request #$requestId as fulfilled");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Request #$requestId marked fulfilled."];
    }

    if (empty($errors)) { header('Location: blood_requests.php'); exit; }
}

$statusFilter   = $_GET['status'] ?? '';
$hospitalFilter = $_GET['hospital_id'] ?? '';

$sql = "SELECT br.*, h.hospital_name, bg.blood_type
        FROM blood_requests br
        JOIN hospitals h ON br.hospital_id = h.hospital_id
        JOIN blood_groups bg ON br.group_id = bg.group_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') { $sql .= " AND br.status = ?"; $params[] = $statusFilter; }
if ($hospitalFilter !== '') { $sql .= " AND br.hospital_id = ?"; $params[] = $hospitalFilter; }
$sql .= " ORDER BY FIELD(br.priority,'Emergency','Urgent','Normal'), br.request_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$hospitals = $pdo->query("SELECT hospital_id, hospital_name FROM hospitals ORDER BY hospital_name")->fetchAll();

$badgeMap = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected', 'Fulfilled' => 'badge-fulfilled', 'Cancelled' => 'badge-out'];

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">Blood Requests</h3>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form class="row g-2 mb-3" method="get">
      <div class="col-auto">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach (['Pending','Approved','Rejected','Fulfilled','Cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <select name="hospital_id" class="form-select" onchange="this.form.submit()">
          <option value="">All Hospitals</option>
          <?php foreach ($hospitals as $h): ?>
            <option value="<?= $h['hospital_id'] ?>" <?= $hospitalFilter == $h['hospital_id'] ? 'selected' : '' ?>><?= e($h['hospital_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Hospital</th><th>Group</th><th>Units</th><th>Priority</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
              <tr class="<?= $r['priority'] === 'Emergency' && $r['status'] === 'Pending' ? 'emergency-row' : '' ?>">
                <td>#<?= (int)$r['request_id'] ?></td>
                <td><?= e($r['hospital_name']) ?></td>
                <td><span class="badge bg-secondary"><?= e($r['blood_type']) ?></span></td>
                <td><?= (int)$r['units_required'] ?></td>
                <td><span class="badge <?= $r['priority'] === 'Emergency' ? 'badge-emergency' : ($r['priority'] === 'Urgent' ? 'badge-low' : 'bg-secondary') ?>"><?= e($r['priority']) ?></span></td>
                <td class="small"><?= e(date('d M, h:i A', strtotime($r['request_date']))) ?></td>
                <td><span class="badge <?= $badgeMap[$r['status']] ?? 'bg-secondary' ?>"><?= e($r['status']) ?></span></td>
                <td>
                  <?php if ($r['status'] === 'Pending'): ?>
                    <form method="post" class="d-inline" data-confirm="Approve request #<?= $r['request_id'] ?>? This will deduct stock.">
                      <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                      <button class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i></button>
                    </form>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['request_id'] ?>"><i class="fa-solid fa-xmark"></i></button>
                    <div class="modal fade" id="rejectModal<?= $r['request_id'] ?>" tabindex="-1">
                      <div class="modal-dialog"><form method="post" class="modal-content">
                        <?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                        <div class="modal-header"><h5 class="modal-title">Reject Request #<?= $r['request_id'] ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><label class="form-label">Reason (optional)</label><textarea name="remarks" class="form-control" rows="2"></textarea></div>
                        <div class="modal-footer"><button class="btn btn-danger">Reject</button></div>
                      </form></div>
                    </div>
                  <?php elseif ($r['status'] === 'Approved'): ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?><input type="hidden" name="action" value="fulfill"><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                      <button class="btn btn-sm btn-outline-primary">Mark Fulfilled</button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted small"><?= e($r['remarks']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="8" class="text-center text-muted py-4">No requests found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
