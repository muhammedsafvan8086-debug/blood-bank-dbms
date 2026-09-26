<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Donor Management';
$activePage = 'donors.php';
$errors = [];

$groups = $pdo->query("SELECT * FROM blood_groups ORDER BY blood_type")->fetchAll();

// ---------- Handle Add / Edit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $donorId  = $_POST['donor_id'] ?? null;
        $name     = trim($_POST['name'] ?? '');
        $age      = $_POST['age'] ?? '';
        $gender   = $_POST['gender'] ?? '';
        $groupId  = $_POST['group_id'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $lastDon  = $_POST['last_donation_date'] ?: null;

        if ($name === '') $errors[] = 'Name cannot be empty.';
        if (!ctype_digit((string)$age) || (int)$age <= 0) $errors[] = 'Age must be a valid number.';
        if (!$groupId) $errors[] = 'Blood group must be selected.';
        if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone number must be a valid 10-digit number.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email must be a valid email address.';
        if ($lastDon && strtotime($lastDon) > time()) $errors[] = 'Donation date cannot be in the future.';

        if (empty($errors)) {
            if ($donorId) {
                $stmt = $pdo->prepare("UPDATE donors SET name=?, age=?, gender=?, group_id=?, phone=?, email=?, address=?, last_donation_date=? WHERE donor_id=?");
                $stmt->execute([$name, $age, $gender, $groupId, $phone, $email, $address, $lastDon, $donorId]);
                log_action($pdo, 'Update Donor', "Updated donor #$donorId ($name)");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Donor updated successfully.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO donors (name, age, gender, group_id, phone, email, address, last_donation_date) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $age, $gender, $groupId, $phone, $email, $address, $lastDon]);
                log_action($pdo, 'Add Donor', "Added new donor: $name");
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Donor added successfully.'];
            }
            header('Location: donors.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $donorId = (int)($_POST['donor_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT name FROM donors WHERE donor_id = ?");
        $stmt->execute([$donorId]);
        $name = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM users WHERE role='donor' AND linked_id = ?")->execute([$donorId]);
        $pdo->prepare("DELETE FROM donors WHERE donor_id = ?")->execute([$donorId]);
        log_action($pdo, 'Delete Donor', "Deleted donor #$donorId ($name)");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Donor deleted.'];
        header('Location: donors.php');
        exit;
    }
}

// ---------- Search ----------
$search = trim($_GET['q'] ?? '');
$groupFilter = $_GET['group_id'] ?? '';

$sql = "SELECT d.*, bg.blood_type FROM donors d JOIN blood_groups bg ON d.group_id = bg.group_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (d.name LIKE ? OR d.phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($groupFilter !== '') {
    $sql .= " AND d.group_id = ?";
    $params[] = $groupFilter;
}
$sql .= " ORDER BY d.donor_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donors = $stmt->fetchAll();

// ---------- Edit target ----------
$editDonor = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM donors WHERE donor_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editDonor = $stmt->fetch();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="mb-0">Donor Management</h3>
      <button class="btn btn-bb" data-bs-toggle="modal" data-bs-target="#donorModal" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Donor</button>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form class="row g-2 mb-3" method="get">
      <div class="col-auto"><input type="text" name="q" class="form-control" placeholder="Search name or phone" value="<?= e($search) ?>"></div>
      <div class="col-auto">
        <select name="group_id" class="form-select">
          <option value="">All Blood Groups</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= $g['group_id'] ?>" <?= $groupFilter == $g['group_id'] ? 'selected' : '' ?>><?= e($g['blood_type']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto"><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button></div>
    </form>

    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Name</th><th>Group</th><th>Age</th><th>Phone</th><th>Last Donation</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($donors as $d): ?>
              <tr>
                <td>#<?= (int)$d['donor_id'] ?></td>
                <td><?= e($d['name']) ?></td>
                <td><span class="badge bg-secondary"><?= e($d['blood_type']) ?></span></td>
                <td><?= (int)$d['age'] ?></td>
                <td><?= e($d['phone']) ?></td>
                <td><?= $d['last_donation_date'] ? e(date('d-m-Y', strtotime($d['last_donation_date']))) : '<span class="text-muted">Never</span>' ?></td>
                <td>
                  <a href="donations.php?donor_id=<?= $d['donor_id'] ?>" class="btn btn-sm btn-outline-primary" title="Donation History"><i class="fa-solid fa-clock-rotate-left"></i></a>
                  <button class="btn btn-sm btn-outline-secondary" title="Edit"
                    onclick='openEditModal(<?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                  <form method="post" class="d-inline" data-confirm="Delete donor <?= e($d['name']) ?>? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($donors)): ?><tr><td colspan="7" class="text-center text-muted py-4">No donors found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="donorModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="donor_id" id="f_donor_id">
      <div class="modal-header"><h5 class="modal-title" id="donorModalTitle">Add Donor</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Name</label><input required name="name" id="f_name" class="form-control"></div>
        <div class="row">
          <div class="col-6 mb-2"><label class="form-label">Age</label><input required type="number" name="age" id="f_age" class="form-control"></div>
          <div class="col-6 mb-2"><label class="form-label">Gender</label>
            <select name="gender" id="f_gender" class="form-select"><option>Male</option><option>Female</option><option>Other</option></select>
          </div>
        </div>
        <div class="mb-2"><label class="form-label">Blood Group</label>
          <select required name="group_id" id="f_group_id" class="form-select">
            <?php foreach ($groups as $g): ?><option value="<?= $g['group_id'] ?>"><?= e($g['blood_type']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Phone</label><input name="phone" id="f_phone" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" id="f_email" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Address</label><textarea name="address" id="f_address" class="form-control" rows="2"></textarea></div>
        <div class="mb-2"><label class="form-label">Last Donation Date</label><input type="date" name="last_donation_date" id="f_last_donation_date" class="form-control"></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-bb">Save Donor</button></div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('donorModalTitle').innerText = 'Add Donor';
  ['f_donor_id','f_name','f_age','f_phone','f_email','f_address','f_last_donation_date'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f_gender').value = 'Male';
  document.getElementById('f_group_id').selectedIndex = 0;
}
function openEditModal(d) {
  document.getElementById('donorModalTitle').innerText = 'Edit Donor';
  document.getElementById('f_donor_id').value = d.donor_id;
  document.getElementById('f_name').value = d.name;
  document.getElementById('f_age').value = d.age;
  document.getElementById('f_gender').value = d.gender;
  document.getElementById('f_group_id').value = d.group_id;
  document.getElementById('f_phone').value = d.phone;
  document.getElementById('f_email').value = d.email;
  document.getElementById('f_address').value = d.address;
  document.getElementById('f_last_donation_date').value = d.last_donation_date ?? '';
  new bootstrap.Modal(document.getElementById('donorModal')).show();
}
<?php if ($editDonor): ?>
document.addEventListener('DOMContentLoaded', () => openEditModal(<?= json_encode($editDonor) ?>));
<?php endif; ?>
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
