<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('hospital');
$pageTitle = 'Hospital Profile';
$activePage = 'profile.php';
$errors = [];
$hospitalId = $_SESSION['linked_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact_no'] ?? '');

    if ($contact !== '' && !preg_match('/^[0-9]{10}$/', $contact)) $errors[] = 'Contact number must be a valid 10-digit number.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE hospitals SET address = ?, contact_no = ? WHERE hospital_id = ?");
        $stmt->execute([$address, $contact, $hospitalId]);
        log_action($pdo, 'Update Hospital Profile', "Hospital #$hospitalId updated its own profile");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated.'];
        header('Location: profile.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM hospitals WHERE hospital_id = ?");
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">Hospital Profile</h3>
    <div class="bb-card p-4" style="max-width:520px;">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Hospital Name</label><input class="form-control" value="<?= e($hospital['hospital_name']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($hospital['email']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($hospital['address']) ?></textarea></div>
        <div class="mb-3"><label class="form-label">Contact Number</label><input name="contact_no" class="form-control" value="<?= e($hospital['contact_no']) ?>"></div>
        <div class="mb-3"><label class="form-label">Status</label><input class="form-control" value="<?= e($hospital['status']) ?>" disabled></div>
        <button class="btn btn-bb w-100">Save Changes</button>
      </form>
      <p class="small text-muted mt-3 mb-0">To change your hospital name, email, or status, please contact the administrator.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
