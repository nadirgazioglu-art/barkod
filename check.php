<?php
require_once __DIR__ . '/inc/db.php';
$pdo = db();
$token = trim($_GET['token'] ?? '');
$label = null;
$settings = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
if ($token !== '') {
  $stmt = $pdo->prepare('SELECT l.*, c.name customer_name, c.address customer_address, c.phone customer_phone FROM labels l JOIN customers c ON c.id=l.customer_id WHERE l.qr_token=? LIMIT 1');
  $stmt->execute([$token]);
  $label = $stmt->fetch();
  if ($label) {
    $ins = $pdo->prepare('INSERT INTO verification_logs(label_id, ip_address, user_agent) VALUES(?,?,?)');
    $ins->execute([(int)$label['id'], $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 240)]);
  }
}
?>
<!doctype html>
<html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Etiket Doğrulama</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-100 min-h-screen p-4"><div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-6">
  <div class="flex justify-center mb-5">
    <?php if (!empty($settings['company_logo_url'])): ?>
      <img src="<?= esc($settings['company_logo_url']) ?>" class="h-20 object-contain" alt="Firma Logo">
    <?php else: ?>
      <div class="h-20 w-20 rounded-xl bg-slate-900 text-white grid place-items-center font-bold text-2xl">AY</div>
    <?php endif; ?>
  </div>
<?php if ($label): ?>
  <h1 class="text-2xl font-bold text-emerald-700 text-center">Etiket Doğrulandı</h1>
  <p class="text-sm text-slate-500 mb-4 text-center">Bu etiket sistemde kayıtlıdır.</p>
  <div class="grid md:grid-cols-2 gap-2 text-sm">
    <div><b>Müşteri:</b> <?= esc($label['customer_name']) ?></div><div><b>Seri No:</b> <?= esc($label['serial_no']) ?></div>
    <div><b>İmalat:</b> <?= esc($label['manufacture_date']) ?></div><div><b>Üretici:</b> <?= esc($label['manufacturer']) ?></div>
    <div><b>Dolum:</b> <?= esc($label['fill_date']) ?></div><div><b>Basınç Test:</b> <?= esc($label['pressure_test_date']) ?></div>
    <div><b>Sonraki Test:</b> <?= esc($label['next_test_date']) ?></div><div><b>Gelecek Dolum:</b> <?= esc($label['next_fill_date']) ?></div>
    <div><b>Son Kullanma:</b> <?= esc($label['expiry_date']) ?></div><div><b>TSE:</b> <?= esc($label['tse_no']) ?></div>
  </div>
<?php else: ?>
  <h1 class="text-2xl font-bold text-red-700 text-center">Etiket Bulunamadı</h1>
  <p class="text-slate-600 text-center">QR veya token bilgisi geçersiz.</p>
<?php endif; ?>
</div></body></html>
