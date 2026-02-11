<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
$pdo = db();
$settings = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $token = bin2hex(random_bytes(16));
        $up = $pdo->prepare('UPDATE users SET session_token=? WHERE id=?');
        $up->execute([$token, $user['id']]);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['session_token'] = $token;
        header('Location: /dashboard.php');
        exit;
    }
    flash('danger', 'Kullanıcı adı veya şifre hatalı.');
    header('Location: /login.php');
    exit;
}

renderHeader('Giriş');
?>
<div class="min-h-[82vh] grid lg:grid-cols-2 gap-5 items-center">
  <section class="hidden lg:flex rounded-3xl bg-gradient-to-br from-slate-900 to-slate-700 text-white p-10 flex-col justify-between">
    <div><p class="uppercase text-xs tracking-[0.3em] text-slate-300">Premium Kurumsal Sistem</p><h1 class="text-4xl font-bold mt-3">Yangın Söndürücü Takip</h1></div>
    <p class="text-slate-200">Müşteri, etiket, QR doğrulama ve geçmiş takibi.</p>
  </section>
  <section class="bg-white rounded-3xl shadow p-8 border max-w-xl w-full mx-auto">
    <div class="flex items-center gap-3 mb-6">
      <?php if (!empty($settings['company_logo_url'])): ?>
      <img src="<?= esc($settings['company_logo_url']) ?>" class="h-14 w-14 object-contain rounded border p-1" alt="Logo">
      <?php else: ?><div class="h-14 w-14 rounded bg-slate-900 text-white grid place-items-center font-bold">AY</div><?php endif; ?>
      <div><div class="text-xs text-slate-500">Hoş geldiniz</div><div class="text-2xl font-bold"><?= esc($settings['company_name'] ?: 'Atex Yangın') ?></div></div>
    </div>
    <form method="post" class="space-y-3">
      <input name="username" class="w-full border rounded-xl px-3 py-2" placeholder="Kullanıcı adı" required>
      <input name="password" type="password" class="w-full border rounded-xl px-3 py-2" placeholder="Şifre" required>
      <button class="w-full rounded-xl bg-slate-900 text-white py-2.5">Giriş Yap</button>
    </form>
  </section>
</div>
<?php renderFooter(); ?>
