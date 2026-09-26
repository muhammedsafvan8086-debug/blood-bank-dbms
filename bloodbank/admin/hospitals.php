<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Hospital Management';
$activePage = 'hospitals.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = $_POST['hospital_id'] ?? null;
        $name    = trim($_POST['hospital_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact = trim($_POST['contact_no'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $status  = $_POST['status'] ?? 'Active';

        if ($name === '') $errors[] = 'Hospital name cannot be empty.';
        if ($contact !== '' && !preg_match('/^[0-9]{10}$/', $contact)) $errors[] = 'Contact number must be a valid 10-digit number.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email must be a valid email address.';

        if (empty($errors)) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE hospitals SET hospital_name=?, address=?, contact_no=?, email=?, status=? WHERE hospital_id=?");
                $stmt->execute([$name, $address, $contact, $email, $status, $id]);
                log_action($pdo, 'Update Hospital', "Updated hospital #$id ($name)");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Hospital updated.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO hospitals (hospital_name, address, contact_no, email, status) VALUES (?,?,?,?,?)");
                $stmt->execute([$name, $address, $contact, $email, $status]);
                log_action($pdo, 'Add Hospital', "Registered hospital #{$pdo->lastInsertId()} ($name)");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Hospital added.'];
            }
            header('Location: hospitals.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT hospital_name FROM hospitals WHERE hospital_id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM users WHERE role='hospital' AND linked_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM hospitals WHERE hospital_id = ?")->execute([$id]);
        log_action($pdo, 'Delete Hospital', "Deleted hospital #$id ($name)");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Hospital deleted.'];
        header('Location: hospitals.php');
        exit;
    }
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM hospitals WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (hospital_name LIKE ? OR address LIKE ? OR contact_no LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY hospital_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hospitals = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="mb-0">Hospital Management</h3>
      <button class="btn btn-bb" data-bs-toggle="modal" data-bs-target="#hospModal" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Hospital</button>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form class="row g-2 mb-3" method="get">
      <div class="col-auto"><input type="text" name="q" class="form-control" placeholder="Search name, location, contact" value="<?= e($search) ?>" style="min-width:280px;"></div>
      <div class="col-auto"><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button></div>
    </form>

    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Name</th><th>Address</th><th>Contact</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($hospitals as $h): ?>
              <tr>
                <td>#<?= (int)$h['hospital_id'] ?></td>
                <td><?= e($h['hospital_name']) ?></td>
                <td><?= e($h['address']) ?></td>
                <td><?= e($h['contact_no']) ?></td>
                <td><?= e($h['email']) ?></td>
                <td><span class="badge <?= $h['status'] === 'Active' ? 'badge-available' : 'badge-out' ?>"><?= e($h['status']) ?></span></td>
                <td>
                  <a href="blood_requests.php?hospital_id=<?= $h['hospital_id'] ?>" class="btn btn-sm btn-outline-primary" title="Request History"><i class="fa-solid fa-list"></i></a>
                  <button class="btn btn-sm btn-outline-secondary" title="Edit" onclick='openEditModal(<?= json_encode($h, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                  <form method="post" class="d-inline" data-confirm="Delete hospital <?= e($h['hospital_name']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($hospitals)): ?><tr><td colspan="7" class="text-center text-muted py-4">No hospitals found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="hospModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="hospital_id" id="f_id">
      <div class="modal-header"><h5 class="modal-title" id="hospModalTitle">Add Hospital</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Hospital Name</label><input required name="hospital_name" id="f_name" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Address</label><textarea name="address" id="f_address" class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label class="form-label">Contact Number</label><input name="contact_no" id="f_contact" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" id="f_email" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Status</label>
          <select name="status" id="f_status" class="form-select"><option>Active</option><option>Inactive</option></select>
        </div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-bb">Save Hospital</button></div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('hospModalTitle').innerText = 'Add Hospital';
  ['f_id','f_name','f_address','f_contact','f_email'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f_status').value = 'Active';
}
function openEditModal(h) {
  document.getElementById('hospModalTitle').innerText = 'Edit Hospital';
  document.getElementById('f_id').value = h.hospital_id;
  document.getElementById('f_name').value = h.hospital_name;
  document.getElementById('f_address').value = h.address;
  document.getElementById('f_contact').value = h.contact_no;
  document.getElementById('f_email').value = h.email;
  document.getElementById('f_status').value = h.status;
  new bootstrap.Modal(document.getElementById('hospModal')).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
