<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('hospital');
$pageTitle = 'New Blood Request';
$activePage = 'new_request.php';
$errors = [];
$hospitalId = $_SESSION['linked_id'];

$groups = $pdo->query("SELECT * FROM blood_groups ORDER BY blood_type")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $groupId  = $_POST['group_id'] ?? '';
    $units    = $_POST['units_required'] ?? '';
    $priority = $_POST['priority'] ?? 'Normal';
    $remarks  = trim($_POST['remarks'] ?? '');

    if (!$groupId) $errors[] = 'Please select a blood group.';
    if (!ctype_digit((string)$units) || (int)$units <= 0) $errors[] = 'Units required must be a number greater than zero.';
    if (!in_array($priority, ['Normal','Urgent','Emergency'], true)) $errors[] = 'Invalid priority selected.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO blood_requests (hospital_id, group_id, units_required, priority, status, remarks) VALUES (?,?,?,?,'Pending',?)");
        $stmt->execute([$hospitalId, $groupId, $units, $priority, $remarks]);
        $requestId = $pdo->lastInsertId();
        log_action($pdo, 'Create Request', "Hospital submitted request #$requestId ($priority)");
        notify($pdo, 'admin', ($priority === 'Emergency' ? '🚨 New emergency' : 'New') . " blood request received (#$requestId).");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Request #$requestId submitted successfully."];
        header('Location: my_requests.php');
        exit;
    }
}

// Show live availability for the selected group to help hospital staff decide
$stock = $pdo->query("
    SELECT bg.group_id, bg.blood_type, COALESCE(SUM(bs.available_units),0) AS total
    FROM blood_groups bg LEFT JOIN blood_stock bs ON bs.group_id = bg.group_id AND bs.expiry_date >= CURDATE()
    GROUP BY bg.group_id, bg.blood_type ORDER BY bg.blood_type")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">New Blood Request</h3>

    <div class="row g-4">
      <div class="col-md-7">
        <div class="bb-card p-4">
          <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
          <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Blood Group</label>
              <select required name="group_id" class="form-select">
                <option value="">Select</option>
                <?php foreach ($groups as $g): ?><option value="<?= $g['group_id'] ?>"><?= e($g['blood_type']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Units Required</label>
              <input required type="number" min="1" name="units_required" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Priority</label>
              <select required name="priority" class="form-select">
                <option value="Normal">Normal</option>
                <option value="Urgent">Urgent</option>
                <option value="Emergency">Emergency</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Reason for request"></textarea>
            </div>
            <button type="submit" class="btn btn-bb w-100">Submit Request</button>
          </form>
        </div>
      </div>
      <div class="col-md-5">
        <div class="bb-card p-3">
          <h6 class="mb-3">Current Availability</h6>
          <?php foreach ($stock as $s): ?>
            <div class="d-flex justify-content-between border-bottom py-2">
              <span><?= e($s['blood_type']) ?></span>
              <span class="fw-semibold"><?= (int)$s['total'] ?> units</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
