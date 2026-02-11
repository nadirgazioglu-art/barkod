<?php
require_once __DIR__ . '/inc/layout.php';
$user = requireLogin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
  $stmt = $pdo->prepare('UPDATE company_settings SET company_name=?, company_logo_url=?, company_address=?, company_phone=?, tse_no=?, ysc_types=?, dashboard_recent_limit=?, alert_months=? WHERE id=1');
  $stmt->execute([
    $_POST['company_name'] ?? '', $_POST['company_logo_url'] ?? '', $_POST['company_address'] ?? '', $_POST['company_phone'] ?? '', $_POST['tse_no'] ?? '',
    $_POST['ysc_types'] ?? '', max((int)($_POST['dashboard_recent_limit'] ?? 10),1), max((int)($_POST['alert_months'] ?? 3),1)
  ]);
  flash('success','Firma ayarları güncellendi.');
  header('Location: /settings.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
  requireSuper($user);
  $stmt = $pdo->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)');
  $stmt->execute([trim($_POST['username'] ?? ''), password_hash($_POST['password'] ?? '1234', PASSWORD_DEFAULT), $_POST['role'] === 'super' ? 'super' : 'normal']);
  flash('success','Kullanıcı eklendi.');
  header('Location: /settings.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
  $uid = (int)($_POST['user_id'] ?? 0);
  $target = $pdo->prepare('SELECT * FROM users WHERE id=?'); $target->execute([$uid]); $targetUser = $target->fetch();
  if ($targetUser) {
    if ($user['role'] === 'super' || $uid === (int)$user['id']) {
      if (!($user['role'] !== 'super' && $targetUser['role'] === 'super')) {
        $up = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $up->execute([password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT), $uid]);
        flash('success', 'Şifre güncellendi.');
      }
    }
  }
  header('Location: /settings.php'); exit;
}

$settings = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
$users = $pdo->query('SELECT id,username,role FROM users ORDER BY id')->fetchAll();
renderHeader('Firma Ayarları', $user);
?>
<div class="grid lg:grid-cols-2 gap-4">
<form method="post" class="bg-white rounded-xl shadow p-4 space-y-2"><input type="hidden" name="action" value="save_settings">
<h2 class="font-semibold">Firma Ayarları</h2>
<input name="company_name" value="<?= esc($settings['company_name']) ?>" class="w-full border rounded px-2 py-1" placeholder="Firma adı">
<input name="company_logo_url" value="<?= esc($settings['company_logo_url']) ?>" class="w-full border rounded px-2 py-1" placeholder="Logo URL">
<textarea name="company_address" class="w-full border rounded px-2 py-1" placeholder="Adres"><?= esc($settings['company_address']) ?></textarea>
<input name="company_phone" value="<?= esc($settings['company_phone']) ?>" class="w-full border rounded px-2 py-1" placeholder="Telefon">
<input name="tse_no" value="<?= esc($settings['tse_no']) ?>" class="w-full border rounded px-2 py-1" placeholder="TSE no">
<input name="ysc_types" value="<?= esc($settings['ysc_types']) ?>" class="w-full border rounded px-2 py-1" placeholder="YSC tipleri">
<input type="number" name="dashboard_recent_limit" value="<?= (int)$settings['dashboard_recent_limit'] ?>" class="w-full border rounded px-2 py-1">
<input type="number" name="alert_months" value="<?= (int)$settings['alert_months'] ?>" class="w-full border rounded px-2 py-1">
<button class="bg-slate-900 text-white px-4 py-2 rounded">Kaydet</button>
</form>
<section class="bg-white rounded-xl shadow p-4"><h2 class="font-semibold">Kullanıcılar</h2>
<table class="w-full text-sm mb-3"><tr><th>Kullanıcı</th><th>Rol</th><th>İşlem</th></tr><?php foreach($users as $u): ?><tr><td><?= esc($u['username']) ?></td><td><?= esc($u['role']) ?></td><td><form method="post" class="flex gap-1"><input type="hidden" name="action" value="change_password"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="password" name="password" placeholder="Yeni şifre" class="border rounded px-2 py-1 text-xs"><button class="text-blue-700 text-xs">Şifre Değiştir</button></form></td></tr><?php endforeach; ?></table>
<?php if ($user['role']==='super'): ?><form method="post" class="space-y-2 border-t pt-3"><input type="hidden" name="action" value="create_user"><h3 class="font-medium">Yeni Kullanıcı Ekle</h3><input name="username" class="w-full border rounded px-2 py-1" placeholder="Kullanıcı adı" required><input name="password" class="w-full border rounded px-2 py-1" placeholder="Şifre" required><select name="role" class="w-full border rounded px-2 py-1"><option value="normal">Normal</option><option value="super">Süper</option></select><button class="bg-emerald-600 text-white px-4 py-2 rounded">Kullanıcı Ekle</button></form><?php endif; ?>
</section></div>
<?php renderFooter(); ?>
