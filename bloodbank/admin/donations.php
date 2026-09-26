<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Donation Management';
$activePage = 'donations.php';
$errors = [];

$donors = $pdo->query("SELECT donor_id, name FROM donors ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verify_csrf();
    $donorId  = $_POST['donor_id'] ?? '';
    $date     = $_POST['donation_date'] ?? '';
    $units    = $_POST['units'] ?? '';
    $location = trim($_POST['collection_location'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');

    if (!$donorId) $errors[] = 'Please select a donor.';
    if (!$date || strtotime($date) > time()) $errors[] = 'Donation date cannot be an invalid future date.';
    if (!ctype_digit((string)$units) || (int)$units <= 0) $errors[] = 'Units must be a number greater than zero.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // 1. Create donation record
            $stmt = $pdo->prepare("INSERT INTO donations (donor_id, donation_date, units, collection_location, notes) VALUES (?,?,?,?,?)");
            $stmt->execute([$donorId, $date, $units, $location, $notes]);

            // 2. Get donor's blood group
            $stmt = $pdo->prepare("SELECT group_id, name FROM donors WHERE donor_id = ?");
            $stmt->execute([$donorId]);
            $donor = $stmt->fetch();

            // 3. Increase corresponding blood stock (collected today, expires in 42 days - standard whole-blood shelf life)
            $expiry = date('Y-m-d', strtotime($date . ' +42 days'));
            $stmt = $pdo->prepare("INSERT INTO blood_stock (group_id, available_units, collection_date, expiry_date) VALUES (?,?,?,?)");
            $stmt->execute([$donor['group_id'], $units, $date, $expiry]);

            // 4. Update donor's last donation date (only if this is the most recent)
            $stmt = $pdo->prepare("UPDATE donors SET last_donation_date = ? WHERE donor_id = ? AND (last_donation_date IS NULL OR last_donation_date < ?)");
            $stmt->execute([$date, $donorId, $date]);

            // 5. Audit entry
            log_action($pdo, 'Record Donation', "{$donor['name']} donated $units unit(s), stock updated");

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Donation recorded and stock updated.'];
            header('Location: donations.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to record donation. Please try again.';
        }
    }
}

$donorFilter = $_GET['donor_id'] ?? '';
$sql = "SELECT do.*, d.name, bg.blood_type
        FROM donations do
        JOIN donors d ON do.donor_id = d.donor_id
        JOIN blood_groups bg ON d.group_id = bg.group_id
        WHERE 1=1";
$params = [];
if ($donorFilter !== '') { $sql .= " AND do.donor_id = ?"; $params[] = $donorFilter; }
$sql .= " ORDER BY do.donation_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

$filterName = null;
if ($donorFilter !== '') {
    $stmt = $pdo->prepare("SELECT name FROM donors WHERE donor_id = ?");
    $stmt->execute([$donorFilter]);
    $filterName = $stmt->fetchColumn();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="mb-0">Donation Management<?= $filterName ? ' — ' . e($filterName) : '' ?></h3>
      <div>
        <?php if ($filterName): ?><a href="donations.php" class="btn btn-outline-secondary">Clear Filter</a><?php endif; ?>
        <button class="btn btn-bb" data-bs-toggle="modal" data-bs-target="#donationModal"><i class="fa-solid fa-plus"></i> Record Donation</button>
      </div>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>ID</th><th>Donor</th><th>Blood Group</th><th>Units</th><th>Date</th><th>Location</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($donations as $d): ?>
              <tr>
                <td>#<?= (int)$d['donation_id'] ?></td>
                <td><?= e($d['name']) ?></td>
                <td><span class="badge bg-secondary"><?= e($d['blood_type']) ?></span></td>
                <td><?= (int)$d['units'] ?></td>
                <td><?= e(date('d-m-Y', strtotime($d['donation_date']))) ?></td>
                <td><?= e($d['collection_location']) ?></td>
                <td class="small text-muted"><?= e($d['notes']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($donations)): ?><tr><td colspan="7" class="text-center text-muted py-4">No donations recorded yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="donationModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="modal-header"><h5 class="modal-title">Record Donation</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Donor</label>
          <select required name="donor_id" class="form-select">
            <option value="">Select donor</option>
            <?php foreach ($donors as $d): ?><option value="<?= $d['donor_id'] ?>" <?= $donorFilter == $d['donor_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Donation Date</label><input required type="date" name="donation_date" class="form-control" max="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label">Units</label><input required type="number" min="1" value="1" name="units" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Collection Location</label><input name="collection_location" class="form-control" placeholder="e.g. Main Camp - Pune"></div>
        <div class="mb-2"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-bb">Save Donation</button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
