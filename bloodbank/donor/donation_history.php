<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('donor');
$pageTitle = 'Donation History';
$activePage = 'donation_history.php';
$donorId = $_SESSION['linked_id'];

$stmt = $pdo->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY donation_date DESC");
$stmt->execute([$donorId]);
$donations = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="bb-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="bb-content">
    <h3 class="mb-4">My Donation History</h3>
    <div class="bb-card p-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Donation ID</th><th>Date</th><th>Units</th><th>Location</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($donations as $d): ?>
              <tr>
                <td>#<?= (int)$d['donation_id'] ?></td>
                <td><?= e(date('d-m-Y', strtotime($d['donation_date']))) ?></td>
                <td><?= (int)$d['units'] ?></td>
                <td><?= e($d['collection_location']) ?></td>
                <td class="small text-muted"><?= e($d['notes']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($donations)): ?><tr><td colspan="5" class="text-center text-muted py-4">You haven't donated yet. Every drop counts &mdash; consider scheduling your first donation!</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
