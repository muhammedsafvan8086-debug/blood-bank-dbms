<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Login';
$errors = [];

if (is_logged_in()) {
    header('Location: /bloodbank/' . current_role() . '/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid login credentials.';
        } elseif ($user['status'] !== 'Active') {
            $errors[] = 'This account has been deactivated. Please contact the administrator.';
        } else {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['linked_id'] = $user['linked_id'];
            log_action($pdo, 'Login', $user['role'] . ' logged in: ' . $user['email']);
            header('Location: /bloodbank/' . $user['role'] . '/dashboard.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width:440px;">
  <div class="bb-card p-4">
    <h3 class="text-center mb-1"><i class="fa-solid fa-droplet text-danger"></i> Sign In</h3>
    <p class="text-center text-muted small mb-4">Admin, Hospital, and Donor accounts all log in here.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-bb w-100">Login</button>
    </form>

    <hr>
    <p class="small text-muted mb-1">Demo accounts (password: <code>Password123</code>):</p>
    <ul class="small text-muted mb-0">
      <li>Admin &mdash; admin@bloodbank.local</li>
      <li>Hospital &mdash; contact@citygeneral.in</li>
      <li>Donor &mdash; rahul.k@example.com</li>
    </ul>
    <p class="text-center mt-3 mb-0 small">
      New donor? <a href="/bloodbank/register_donor.php">Register here</a> &middot;
      New hospital? <a href="/bloodbank/register_hospital.php">Register here</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
