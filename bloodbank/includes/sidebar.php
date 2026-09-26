<?php
// Expects $activePage (string) to be set before include, and current_role().
$role = current_role();

$menus = [
    'admin' => [
        'dashboard.php'       => ['fa-gauge', 'Dashboard'],
        'donors.php'          => ['fa-users', 'Donors'],
        'hospitals.php'       => ['fa-hospital', 'Hospitals'],
        'blood_stock.php'     => ['fa-flask', 'Blood Stock'],
        'donations.php'       => ['fa-hand-holding-droplet', 'Donations'],
        'blood_requests.php'  => ['fa-triangle-exclamation', 'Blood Requests'],
        'reports.php'         => ['fa-chart-column', 'Reports'],
    ],
    'hospital' => [
        'dashboard.php'       => ['fa-gauge', 'Dashboard'],
        'new_request.php'     => ['fa-plus', 'New Request'],
        'my_requests.php'     => ['fa-list', 'My Requests'],
        'profile.php'         => ['fa-hospital', 'Hospital Profile'],
    ],
    'donor' => [
        'dashboard.php'       => ['fa-gauge', 'Dashboard'],
        'profile.php'         => ['fa-user', 'My Profile'],
        'donation_history.php'=> ['fa-clock-rotate-left', 'Donation History'],
    ],
];

$items = $menus[$role] ?? [];
?>
<div class="bb-sidebar">
  <ul class="nav flex-column">
    <?php foreach ($items as $link => [$icon, $label]): ?>
      <li class="nav-item">
        <a class="nav-link <?= ($activePage ?? '') === $link ? 'active' : '' ?>" href="/bloodbank/<?= e($role) ?>/<?= e($link) ?>">
          <i class="fa-solid <?= e($icon) ?>"></i> <span><?= e($label) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
