<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Donor Registration';
$errors = [];
$old = [];

$groups = $pdo->query("SELECT * FROM blood_groups ORDER BY blood_type")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = $_POST;

    $name     = trim($_POST['name'] ?? '');
    $age      = $_POST['age'] ?? '';
    $gender   = $_POST['gender'] ?? '';
    $groupId  = $_POST['group_id'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Name cannot be empty.';
    if (!ctype_digit((string)$age) || (int)$age < 18 || (int)$age > 65) $errors[] = 'Age must be a valid number between 18 and 65.';
    if (!$groupId) $errors[] = 'Blood group must be selected.';
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone number must be a valid 10-digit number.';
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
            $stmt = $pdo->prepare("INSERT INTO donors (name, age, gender, group_id, phone, email, address) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name, $age, $gender, $groupId, $phone, $email, $address]);
            $donorId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, linked_id) VALUES (?,?,?,'donor',?)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $donorId]);

            log_action($pdo, 'Donor Registration', "New donor registered: $name ($email)");
            $pdo->commit();

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registration successful! You can now log in.'];
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
    <h3 class="mb-1"><i class="fa-solid fa-heart text-danger"></i> Donor Registration</h3>
    <p class="text-muted small mb-4">Join our donor network and help save lives.</p>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" required value="<?= e($old['name'] ?? '') ?>">
        </div>
        <div class="col-6">
          <label class="form-label">Age</label>
          <input type="number" name="age" min="18" max="65" class="form-control" required value="<?= e($old['age'] ?? '') ?>">
        </div>
        <div class="col-6">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Blood Group</label>
          <select name="group_id" class="form-select" required>
            <option value="">Select</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= $g['group_id'] ?>" <?= (($old['group_id'] ?? '') == $g['group_id']) ? 'selected' : '' ?>><?= e($g['blood_type']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" required value="<?= e($old['phone'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= e($old['email'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= e($old['address'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
      </div>
      <button type="submit" class="btn btn-bb w-100 mt-4">Register</button>
    </form>
    <p class="text-center mt-3 mb-0 small">Already registered? <a href="/bloodbank/login.php">Login here</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
