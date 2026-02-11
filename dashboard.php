<?php
require_once __DIR__ . '/inc/layout.php';
$user = requireLogin();
$pdo = db();
$settings = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
$limit = max((int)($settings['dashboard_recent_limit'] ?? 10), 1);
$months = max((int)($settings['alert_months'] ?? 3), 1);

$counts = [
  'customers' => (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
  'labels' => (int)$pdo->query('SELECT COUNT(*) FROM labels')->fetchColumn(),
  'active_labels' => (int)$pdo->query('SELECT COUNT(*) FROM labels WHERE is_active=1')->fetchColumn(),
];

$recent = $pdo->prepare("SELECT vl.verified_at, vl.ip_address, l.serial_no, c.name customer_name
FROM verification_logs vl JOIN labels l ON l.id=vl.label_id JOIN customers c ON c.id=l.customer_id
ORDER BY vl.verified_at DESC LIMIT ?");
$recent->bindValue(1, $limit, PDO::PARAM_INT);
$recent->execute();
$recentRows = $recent->fetchAll();

$up = $pdo->prepare("SELECT c.name customer_name, l.serial_no, l.next_fill_date, l.next_test_date, l.expiry_date
FROM labels l JOIN customers c ON c.id=l.customer_id
WHERE l.is_active=1 AND (
  date(l.next_fill_date) <= date('now', '+' || ? || ' months') OR
  date(l.next_test_date) <= date('now', '+' || ? || ' months') OR
  date(l.expiry_date) <= date('now', '+' || ? || ' months')
) ORDER BY l.next_fill_date LIMIT 50");
$up->execute([$months, $months, $months]);
$upcoming = $up->fetchAll();

renderHeader('Dashboard', $user);
?>
<h1 class="text-3xl font-bold mb-4">Yönetim Paneli</h1>
<div class="grid md:grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-xl p-4 shadow">Toplam Müşteri: <b><?= $counts['customers'] ?></b></div>
  <div class="bg-white rounded-xl p-4 shadow">Toplam Etiket: <b><?= $counts['labels'] ?></b></div>
  <div class="bg-white rounded-xl p-4 shadow">Aktif Etiket: <b><?= $counts['active_labels'] ?></b></div>
</div>
<div class="grid lg:grid-cols-2 gap-4">
  <section class="bg-white rounded-xl p-4 shadow"><h2 class="font-semibold mb-2">Yaklaşan Tarihler (<?= (int)$settings['alert_months'] ?> ay)</h2><table class="w-full text-sm"><tr><th class="text-left">Müşteri</th><th>Seri</th><th>Dolum</th><th>Test</th><th>SKT</th></tr><?php foreach($upcoming as $r): ?><tr><td><?= esc($r['customer_name']) ?></td><td><?= esc($r['serial_no']) ?></td><td><?= esc($r['next_fill_date']) ?></td><td><?= esc($r['next_test_date']) ?></td><td><?= esc($r['expiry_date']) ?></td></tr><?php endforeach; ?></table></section>
  <section class="bg-white rounded-xl p-4 shadow"><h2 class="font-semibold mb-2">Son Doğrulatmalar</h2><table class="w-full text-sm"><tr><th class="text-left">Tarih</th><th>Müşteri</th><th>Seri</th><th>IP</th></tr><?php foreach($recentRows as $r): ?><tr><td><?= esc($r['verified_at']) ?></td><td><?= esc($r['customer_name']) ?></td><td><?= esc($r['serial_no']) ?></td><td><?= esc($r['ip_address']) ?></td></tr><?php endforeach; ?></table></section>
</div>
<form method="post" action="/reset_demo.php" class="mt-6"><button class="bg-red-600 text-white px-4 py-2 rounded">Test Verilerini Sıfırla</button></form>
<?php renderFooter(); ?>
