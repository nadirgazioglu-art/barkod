<?php
require_once __DIR__ . '/inc/layout.php';
$user = requireLogin();
$pdo = db();
$settings = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
$yscTypes = array_filter(array_map('trim', explode(',', $settings['ysc_types'] ?? '')));
$customers = $pdo->query('SELECT id,name FROM customers ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $quantity = max((int)($_POST['quantity'] ?? 1), 1);
    $baseSerial = trim($_POST['serial_no'] ?? '');
    $fillDate = $_POST['fill_date'] ?? null;
    $pressureDate = $_POST['pressure_test_date'] ?? null;
    $stmt = $pdo->prepare('INSERT INTO labels(customer_id, manufacture_date, serial_no, manufacturer, fill_date, pressure_test_date, next_test_date, next_fill_date, expiry_date, ysc_type, tse_no, qr_token, created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
    for ($i = 0; $i < $quantity; $i++) {
      $serial = $baseSerial;
      if ($quantity > 1) {
        $serial = ctype_digit($baseSerial) ? (string)((int)$baseSerial + $i) : $baseSerial . '-' . ($i + 1);
      }
      $stmt->execute([
        $customerId,
        $_POST['manufacture_date'] ?? null,
        $serial,
        $_POST['manufacturer'] ?? '',
        $fillDate,
        $pressureDate,
        addYears($pressureDate, 1),
        addYears($fillDate, 1),
        addYears($fillDate, 4),
        $_POST['ysc_type'] ?? '',
        $settings['tse_no'] ?? '',
        bin2hex(random_bytes(16)),
        $user['id'],
      ]);
    }
    flash('success', $quantity . ' adet etiket üretildi.');
    header('Location: /labels.php?customer_id=' . $customerId);
    exit;
}

$selectedCustomer = (int)($_GET['customer_id'] ?? 0);
$history = [];
if ($selectedCustomer > 0) {
  $h = $pdo->prepare('SELECT * FROM labels WHERE customer_id=? ORDER BY created_at DESC LIMIT 100');
  $h->execute([$selectedCustomer]);
  $history = $h->fetchAll();
}

renderHeader('Etiket Üretimi', $user);
?>
<h1 class="text-2xl font-bold mb-4">Etiket Üretimi</h1>
<div class="grid lg:grid-cols-3 gap-4">
<form method="post" class="lg:col-span-2 bg-white rounded-xl shadow p-4 grid md:grid-cols-2 gap-3">
<select name="customer_id" class="border rounded px-2 py-1 md:col-span-2" required><option value="">Müşteri seçin</option><?php foreach($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $selectedCustomer===(int)$c['id']?'selected':'' ?>><?= esc($c['name']) ?></option><?php endforeach; ?></select>
<input name="serial_no" placeholder="Seri no (başlangıç)" class="border rounded px-2 py-1" required>
<input name="quantity" type="number" min="1" value="1" class="border rounded px-2 py-1" required>
<input name="manufacturer" placeholder="Üretici firma" class="border rounded px-2 py-1" required>
<select name="ysc_type" class="border rounded px-2 py-1"><?php foreach($yscTypes as $y): ?><option><?= esc($y) ?></option><?php endforeach; ?></select>
<div class="flex gap-2"><input id="manufacture_date" name="manufacture_date" type="date" class="border rounded px-2 py-1 flex-1"><button type="button" onclick="today('manufacture_date')" class="border px-2 rounded">Bugün</button></div>
<div class="flex gap-2"><input id="fill_date" name="fill_date" type="date" class="border rounded px-2 py-1 flex-1"><button type="button" onclick="today('fill_date')" class="border px-2 rounded">Bugün</button></div>
<div class="flex gap-2 md:col-span-2"><input id="pressure_test_date" name="pressure_test_date" type="date" class="border rounded px-2 py-1 flex-1"><button type="button" onclick="today('pressure_test_date')" class="border px-2 rounded">Bugün</button></div>
<button class="md:col-span-2 bg-slate-900 text-white py-2 rounded">Etiket Üret</button>
</form>
<section class="bg-white rounded-xl shadow p-4"><h3 class="font-semibold">80mm x 50mm Önizleme</h3><div class="border mt-3 p-1" style="width:80mm;height:50mm;"><div class="border h-full p-2 text-[10px]"><div class="font-bold"><?= esc($settings['company_name'] ?? 'Firma Adı') ?></div><div>TSE: <?= esc($settings['tse_no'] ?? '-') ?></div><div class="mt-1">QR Kod burada basılacaktır.</div></div></div></section>
</div>
<section class="bg-white rounded-xl shadow p-4 mt-4"><h2 class="font-semibold">Seçili Müşteri Barkod Geçmişi</h2><table class="w-full text-sm mt-2"><tr><th>Seri</th><th>Dolum</th><th>Sonraki Dolum</th><th>Oluşturulma</th><th>QR</th></tr><?php foreach($history as $h): ?><tr><td><?= esc($h['serial_no']) ?></td><td><?= esc($h['fill_date']) ?></td><td><?= esc($h['next_fill_date']) ?></td><td><?= esc($h['created_at']) ?></td><td><a class="text-blue-700" target="_blank" href="/check.php?token=<?= esc($h['qr_token']) ?>">Kontrol</a></td></tr><?php endforeach; ?></table></section>
<script>function today(id){document.getElementById(id).value=(new Date()).toISOString().slice(0,10);}</script>
<?php renderFooter(); ?>
