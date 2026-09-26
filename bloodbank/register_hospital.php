<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Hospital Registration';
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = $_POST;

    $name     = trim($_POST['hospital_name'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $contact  = trim($_POST['contact_no'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Hospital name cannot be empty.';
    if (!preg_match('/^[0-9]{10}$/', $contact)) $errors[] = 'Contact number must be a valid 10-digit number.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email must be a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetchColumn() > 0) $errors[] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO hospitals (hospital_name, address, contact_no, email, status) VALUES (?,?,?,?,'Active')");
            $stmt->execute([$name, $address, $contact, $email]);
            $hospitalId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, linked_id) VALUES (?,?,?,'hospital',?)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $hospitalId]);

            log_action($pdo, 'Hospital Registration', "New hospital registered: $name ($email)");
            $pdo->commit();

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Hospital registered successfully! You can now log in.'];
            header('Location: /bloodbank/login.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width:560px;">
  <div class="bb-card p-4">
    <h3 class="mb-1"><i class="fa-solid fa-hospital text-danger"></i> Hospital Registration</h3>
    <p class="text-muted small mb-4">Register your hospital to request blood units.</p>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Hospital Name</label>
        <input type="text" name="hospital_name" class="form-control" required value="<?= e($old['hospital_name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= e($old['address'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Contact Number</label>
        <input type="text" name="contact_no" class="form-control" required value="<?= e($old['contact_no'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required value="<?= e($old['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-bb w-100">Register Hospital</button>
    </form>
    <p class="text-center mt-3 mb-0 small">Already registered? <a href="/bloodbank/login.php">Login here</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
