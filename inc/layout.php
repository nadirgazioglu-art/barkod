<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function renderHeader(string $title, ?array $user = null): void
{
    $settings = db()->query('SELECT * FROM company_settings WHERE id=1')->fetch();
    $flashes = flashes();
    ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<?php if ($user): ?>
<nav class="bg-slate-900 text-white shadow">
  <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <div class="h-9 w-9 rounded bg-white/20 grid place-items-center font-bold">AY</div>
      <div>
        <div class="text-xs text-slate-300">Kurumsal Takip</div>
        <div class="font-semibold"><?= esc($settings['company_name'] ?? 'Atex Yangın') ?></div>
      </div>
    </div>
    <div class="flex gap-2 text-sm flex-wrap">
      <a class="px-3 py-1 rounded bg-white/10" href="/dashboard.php">Dashboard</a>
      <a class="px-3 py-1 rounded bg-white/10" href="/customers.php">Müşteriler</a>
      <a class="px-3 py-1 rounded bg-white/10" href="/labels.php">Barkod Üret</a>
      <a class="px-3 py-1 rounded bg-white/10" href="/settings.php">Firma Ayarları</a>
      <a class="px-3 py-1 rounded bg-red-600" href="/logout.php">Çıkış</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="max-w-7xl mx-auto p-4">
<?php foreach ($flashes as $f): ?>
  <div class="mb-3 rounded p-3 text-sm <?= $f['type']==='success'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800' ?>"><?= esc($f['message']) ?></div>
<?php endforeach; ?>
<?php
}

function renderFooter(): void
{
    echo "</main></body></html>";
}
