<?php
require_once __DIR__ . '/inc/layout.php';
$user = requireLogin();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM customers WHERE id=?');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) { header('Location: /customers.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $up = $pdo->prepare('UPDATE customers SET name=?, contact_name=?, phone=?, email=?, address=?, note=? WHERE id=?');
  $up->execute([$_POST['name'] ?? '', $_POST['contact_name'] ?? '', $_POST['phone'] ?? '', $_POST['email'] ?? '', $_POST['address'] ?? '', $_POST['note'] ?? '', $id]);
  flash('success','Müşteri güncellendi.');
  header('Location: /customer_detail.php?id=' . $id); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
  $q = $pdo->prepare('UPDATE labels SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=? AND customer_id=?');
  $q->execute([(int)$_POST['label_id'], $id]);
  header('Location: /customer_detail.php?id=' . $id); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $q = $pdo->prepare('DELETE FROM labels WHERE id=? AND customer_id=?');
  $q->execute([(int)$_POST['label_id'], $id]);
  header('Location: /customer_detail.php?id=' . $id); exit;
}
$labels = $pdo->prepare("SELECT l.*, (SELECT COUNT(*) FROM verification_logs vl WHERE vl.label_id=l.id) verify_count FROM labels l WHERE customer_id=? ORDER BY created_at DESC");
$labels->execute([$id]);
$labelRows = $labels->fetchAll();
renderHeader('Müşteri Detay', $user);
?>
<div class="grid lg:grid-cols-3 gap-4">
<form method="post" class="bg-white p-4 rounded-xl shadow space-y-2"><input type="hidden" name="action" value="update">
<input name="name" value="<?= esc($customer['name']) ?>" class="w-full border rounded px-2 py-1" required>
<input name="contact_name" value="<?= esc($customer['contact_name']) ?>" class="w-full border rounded px-2 py-1">
<input name="phone" value="<?= esc($customer['phone']) ?>" class="w-full border rounded px-2 py-1">
<input name="email" value="<?= esc($customer['email']) ?>" class="w-full border rounded px-2 py-1">
<textarea name="address" class="w-full border rounded px-2 py-1"><?= esc($customer['address']) ?></textarea>
<textarea name="note" class="w-full border rounded px-2 py-1"><?= esc($customer['note']) ?></textarea>
<button class="bg-slate-900 text-white px-4 py-2 rounded">Güncelle</button></form>
<section class="lg:col-span-2 bg-white p-4 rounded-xl shadow"><h3 class="font-semibold mb-2">Etiket Geçmişi</h3>
<table class="w-full text-xs"><tr><th>Seri</th><th>Dolum</th><th>Sonraki Dolum</th><th>Doğrulatma</th><th>Durum</th><th>İşlem</th></tr>
<?php foreach($labelRows as $l): ?><tr><td><?= esc($l['serial_no']) ?></td><td><?= esc($l['fill_date']) ?></td><td><?= esc($l['next_fill_date']) ?></td><td><?= (int)$l['verify_count'] ?></td><td><?= ((int)$l['is_active']===1?'Aktif':'Pasif') ?></td><td class="space-x-1">
<form method="post" class="inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="label_id" value="<?= (int)$l['id'] ?>"><button class="text-blue-600">Pasif/Aktif</button></form>
<form method="post" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="label_id" value="<?= (int)$l['id'] ?>"><button class="text-red-600">Sil</button></form>
</td></tr><?php endforeach; ?></table></section></div>
<?php renderFooter(); ?>
