<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('donor');
$pageTitle = 'My Profile';
$activePage = 'profile.php';
$errors = [];
$donorId = $_SESSION['linked_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone number must be a valid 10-digit number.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE donors SET phone = ?, address = ? WHERE donor_id = ?");
        $stmt->execute([$phone, $address, $donorId]);
        log_action($pdo, 'Update Donor Profile', "Donor #$donorId updated their own profile");
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated.'];
        header('Location: profile.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT d.*, bg.blood_type FROM donors d JOIN blood_groups bg ON d.group_id = bg.group_id WHERE d.donor_id = ?");
$stmt->execute([$donorId]);
$donor = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">My Profile</h3>
    <div class="bb-card p-4" style="max-width:520px;">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" value="<?= e($donor['name']) ?>" disabled></div>
        <div class="row">
          <div class="col-6 mb-3"><label class="form-label">Age</label><input class="form-control" value="<?= (int)$donor['age'] ?>" disabled></div>
          <div class="col-6 mb-3"><label class="form-label">Blood Group</label><input class="form-control" value="<?= e($donor['blood_type']) ?>" disabled></div>
        </div>
        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($donor['email']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Phone Number</label><input name="phone" class="form-control" value="<?= e($donor['phone']) ?>"></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($donor['address']) ?></textarea></div>
        <button class="btn btn-bb w-100">Save Changes</button>
      </form>
      <p class="small text-muted mt-3 mb-0">To change your name, age, or blood group, please contact the administrator.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
