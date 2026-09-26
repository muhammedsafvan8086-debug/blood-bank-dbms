<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Blood Availability';

$search = trim($_GET['blood_type'] ?? '');

$sql = "
    SELECT bg.blood_type,
           COALESCE(SUM(bs.available_units),0) AS total_units,
           MIN(bs.expiry_date) AS nearest_expiry
    FROM blood_groups bg
    LEFT JOIN blood_stock bs ON bs.group_id = bg.group_id AND bs.expiry_date >= CURDATE()
    " . ($search !== '' ? "WHERE bg.blood_type = :bt" : "") . "
    GROUP BY bg.blood_type
    ORDER BY bg.blood_type
";
$stmt = $pdo->prepare($sql);
if ($search !== '') $stmt->bindValue(':bt', $search);
$stmt->execute();
$rows = $stmt->fetchAll();

function stock_status(int $units): array {
    if ($units <= 0) return ['Out of Stock', 'badge-out'];
    if ($units < 10) return ['Low Stock', 'badge-low'];
    return ['Available', 'badge-available'];
}

$allTypes = $pdo->query("SELECT blood_type FROM blood_groups ORDER BY blood_type")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
  <h2 class="mb-1"><i class="fa-solid fa-droplet text-danger"></i> Blood Availability</h2>
  <p class="text-muted">Live, database-driven stock levels across all registered blood groups.</p>

  <form class="row g-2 mb-4" method="get">
    <div class="col-auto">
      <select name="blood_type" class="form-select">
        <option value="">All Blood Groups</option>
        <?php foreach ($allTypes as $t): ?>
          <option value="<?= e($t) ?>" <?= $search === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-bb" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    </div>
    <?php if ($search !== ''): ?>
      <div class="col-auto"><a href="/bloodbank/blood_availability.php" class="btn btn-outline-secondary">Reset</a></div>
    <?php endif; ?>
  </form>

  <div class="row g-3">
    <?php foreach ($rows as $row): [$label, $class] = stock_status((int)$row['total_units']); ?>
      <div class="col-6 col-md-3">
        <div class="bb-card p-4 text-center">
          <div class="fs-2 fw-bold text-danger"><?= e($row['blood_type']) ?></div>
          <div class="fs-5 fw-semibold my-1"><?= (int)$row['total_units'] ?> Units</div>
          <span class="badge <?= $class ?> mb-2"><?= $label ?></span>
          <?php if ($row['nearest_expiry']): ?>
            <div class="small text-muted">Nearest expiry: <?= e(date('d-m-Y', strtotime($row['nearest_expiry']))) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <div class="col-12"><div class="alert alert-warning">No matching blood group found.</div></div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
