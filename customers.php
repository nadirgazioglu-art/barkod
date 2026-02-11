<?php
require_once __DIR__ . '/inc/layout.php';
$user = requireLogin();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $pdo->prepare('INSERT INTO customers(name,contact_name,phone,email,address,note) VALUES(?,?,?,?,?,?)');
  $stmt->execute([
    $_POST['name'] ?? '', $_POST['contact_name'] ?? '', $_POST['phone'] ?? '', $_POST['email'] ?? '', $_POST['address'] ?? '', $_POST['note'] ?? ''
  ]);
  flash('success', 'Müşteri eklendi.');
  header('Location: /customers.php');
  exit;
}
$rows = $pdo->query('SELECT * FROM customers ORDER BY id DESC')->fetchAll();
renderHeader('Müşteriler', $user);
?>
<div class="grid lg:grid-cols-3 gap-4">
<form method="post" class="bg-white p-4 rounded-xl shadow space-y-2">
  <h2 class="font-semibold">Yeni Müşteri</h2>
  <input name="name" class="w-full border rounded px-2 py-1" placeholder="Firma" required>
  <input name="contact_name" class="w-full border rounded px-2 py-1" placeholder="Yetkili">
  <input name="phone" class="w-full border rounded px-2 py-1" placeholder="Telefon">
  <input name="email" class="w-full border rounded px-2 py-1" placeholder="E-posta">
  <textarea name="address" class="w-full border rounded px-2 py-1" placeholder="Adres"></textarea>
  <textarea name="note" class="w-full border rounded px-2 py-1" placeholder="Not"></textarea>
  <button class="bg-slate-900 text-white px-4 py-2 rounded">Kaydet</button>
</form>
<section class="lg:col-span-2 bg-white p-4 rounded-xl shadow"><h2 class="font-semibold mb-2">Müşteri Listesi</h2>
<table class="w-full text-sm"><tr><th class="text-left">Firma</th><th>Telefon</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?= esc($r['name']) ?></td><td><?= esc($r['phone']) ?></td><td><a class="text-blue-600" href="/customer_detail.php?id=<?= (int)$r['id'] ?>">Detay</a></td></tr><?php endforeach; ?></table>
</section></div>
<?php renderFooter(); ?>
