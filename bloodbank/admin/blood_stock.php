<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Blood Stock Management';
$activePage = 'blood_stock.php';
$errors = [];
$LOW_STOCK_THRESHOLD = 10;

$groups = $pdo->query("SELECT * FROM blood_groups ORDER BY blood_type")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $stockId  = $_POST['stock_id'] ?? null;
        $groupId  = $_POST['group_id'] ?? '';
        $units    = $_POST['available_units'] ?? '';
        $collDate = $_POST['collection_date'] ?? '';
        $expDate  = $_POST['expiry_date'] ?? '';

        if (!$groupId) $errors[] = 'Please select a blood group.';
        if (!ctype_digit((string)$units) || (int)$units < 0) $errors[] = 'Units must be a number greater than or equal to zero.';
        if (!$collDate) $errors[] = 'Collection date is required.';
        if (!$expDate || ($collDate && strtotime($expDate) <= strtotime($collDate))) $errors[] = 'Expiry date must be after the collection date.';

        if (empty($errors)) {
            if ($stockId) {
                $stmt = $pdo->prepare("UPDATE blood_stock SET group_id=?, available_units=?, collection_date=?, expiry_date=? WHERE stock_id=?");
                $stmt->execute([$groupId, $units, $collDate, $expDate, $stockId]);
                log_action($pdo, 'Update Stock', "Updated stock #$stockId");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stock record updated.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO blood_stock (group_id, available_units, collection_date, expiry_date) VALUES (?,?,?,?)");
                $stmt->execute([$groupId, $units, $collDate, $expDate]);
                log_action($pdo, 'Add Stock', "Added $units units to group #$groupId");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stock added.'];
            }
            header('Location: blood_stock.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $stockId = (int)($_POST['stock_id'] ?? 0);
        $pdo->prepare("DELETE FROM blood_stock WHERE stock_id = ?")->execute([$stockId]);
        log_action($pdo, 'Remove Stock', "Deleted stock record #$stockId");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stock record removed.'];
        header('Location: blood_stock.php');
        exit;
    }

    if ($action === 'remove_expired') {
        $count = $pdo->query("SELECT COUNT(*) FROM blood_stock WHERE expiry_date < CURDATE()")->fetchColumn();
        $pdo->exec("DELETE FROM blood_stock WHERE expiry_date < CURDATE()");
        log_action($pdo, 'Remove Expired Stock', "Removed $count expired stock record(s)");
        $_SESSION['flash'] = ['type' => 'success', 'message' => "$count expired stock record(s) removed."];
        header('Location: blood_stock.php');
        exit;
    }
}

$groupFilter = $_GET['group_id'] ?? '';
$expiryFilter = $_GET['expiry'] ?? ''; // 'soon' | 'expired'

$sql = "SELECT bs.*, bg.blood_type FROM blood_stock bs JOIN blood_groups bg ON bs.group_id = bg.group_id WHERE 1=1";
$params = [];
if ($groupFilter !== '') { $sql .= " AND bs.group_id = ?"; $params[] = $groupFilter; }
if ($expiryFilter === 'soon') { $sql .= " AND bs.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"; }
if ($expiryFilter === 'expired') { $sql .= " AND bs.expiry_date < CURDATE()"; }
$sql .= " ORDER BY bs.expiry_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stock = $stmt->fetchAll();

function stock_badge(int $units, string $expiry, int $threshold): array {
    if (strtotime($expiry) < time()) return ['Expired', 'badge-out'];
    if ($units <= 0) return ['Out of Stock', 'badge-out'];
    if ($units < $threshold) return ['Low Stock', 'badge-low'];
    return ['Available', 'badge-available'];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <h3 class="mb-0">Blood Stock Management</h3>
      <div>
        <form method="post" class="d-inline" data-confirm="Remove all expired stock records?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remove_expired">
          <button class="btn btn-outline-danger"><i class="fa-solid fa-broom"></i> Remove Expired</button>
        </form>
        <button class="btn btn-bb" data-bs-toggle="modal" data-bs-target="#stockModal" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Stock</button>
      </div>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form class="row g-2 mb-3" method="get">
      <div class="col-auto">
        <select name="group_id" class="form-select" onchange="this.form.submit()">
          <option value="">All Blood Groups</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= $g['group_id'] ?>" <?= $groupFilter == $g['group_id'] ? 'selected' : '' ?>><?= e($g['blood_type']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <select name="expiry" class="form-select" onchange="this.form.submit()">
          <option value="">All Expiry</option>
          <option value="soon" <?= $expiryFilter === 'soon' ? 'selected' : '' ?>>Expiring within 7 days</option>
          <option value="expired" <?= $expiryFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
        </select>
      </div>
    </form>

    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Group</th><th>Units</th><th>Collected</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($stock as $s): [$label, $class] = stock_badge((int)$s['available_units'], $s['expiry_date'], $LOW_STOCK_THRESHOLD); ?>
              <tr>
                <td>#<?= (int)$s['stock_id'] ?></td>
                <td><span class="badge bg-secondary"><?= e($s['blood_type']) ?></span></td>
                <td><?= (int)$s['available_units'] ?></td>
                <td><?= e(date('d-m-Y', strtotime($s['collection_date']))) ?></td>
                <td><?= e(date('d-m-Y', strtotime($s['expiry_date']))) ?></td>
                <td><span class="badge <?= $class ?>"><?= $label ?></span></td>
                <td>
                  <button class="btn btn-sm btn-outline-secondary" title="Edit" onclick='openEditModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                  <form method="post" class="d-inline" data-confirm="Remove this stock record?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="stock_id" value="<?= $s['stock_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($stock)): ?><tr><td colspan="7" class="text-center text-muted py-4">No stock records found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="stock_id" id="f_id">
      <div class="modal-header"><h5 class="modal-title" id="stockModalTitle">Add Stock</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Blood Group</label>
          <select required name="group_id" id="f_group_id" class="form-select">
            <?php foreach ($groups as $g): ?><option value="<?= $g['group_id'] ?>"><?= e($g['blood_type']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Available Units</label><input required type="number" min="0" name="available_units" id="f_units" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Collection Date</label><input required type="date" name="collection_date" id="f_coll" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Expiry Date</label><input required type="date" name="expiry_date" id="f_exp" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-bb">Save</button></div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('stockModalTitle').innerText = 'Add Stock';
  ['f_id','f_units','f_coll','f_exp'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f_group_id').selectedIndex = 0;
}
function openEditModal(s) {
  document.getElementById('stockModalTitle').innerText = 'Edit Stock';
  document.getElementById('f_id').value = s.stock_id;
  document.getElementById('f_group_id').value = s.group_id;
  document.getElementById('f_units').value = s.available_units;
  document.getElementById('f_coll').value = s.collection_date;
  document.getElementById('f_exp').value = s.expiry_date;
  new bootstrap.Modal(document.getElementById('stockModal')).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
