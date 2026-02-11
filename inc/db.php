<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../barkod.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('super', 'normal')),
        session_token TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS company_settings (
        id INTEGER PRIMARY KEY CHECK(id=1),
        company_name TEXT,
        company_logo_url TEXT,
        company_address TEXT,
        company_phone TEXT,
        tse_no TEXT,
        ysc_types TEXT DEFAULT 'Kuru Kimyevi Toz,CO2,Köpük',
        dashboard_recent_limit INTEGER DEFAULT 10,
        alert_months INTEGER DEFAULT 3
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact_name TEXT,
        phone TEXT,
        email TEXT,
        address TEXT,
        note TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS labels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        manufacture_date TEXT,
        serial_no TEXT NOT NULL,
        manufacturer TEXT,
        fill_date TEXT,
        pressure_test_date TEXT,
        next_test_date TEXT,
        next_fill_date TEXT,
        expiry_date TEXT,
        ysc_type TEXT,
        tse_no TEXT,
        qr_token TEXT UNIQUE NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_by INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS verification_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label_id INTEGER NOT NULL,
        verified_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT,
        user_agent TEXT,
        FOREIGN KEY(label_id) REFERENCES labels(id) ON DELETE CASCADE
    )");

    $pdo->exec("INSERT OR IGNORE INTO company_settings(id, company_name) VALUES (1, 'Atex Yangın')");

    $super = $pdo->query("SELECT id FROM users WHERE role='super' LIMIT 1")->fetch();
    if (!$super) {
        $stmt = $pdo->prepare("INSERT INTO users(username, password_hash, role) VALUES (?, ?, 'super')");
        $stmt->execute(['super', password_hash('1234', PASSWORD_DEFAULT)]);
    }

    $normal = $pdo->query("SELECT id FROM users WHERE role='normal' LIMIT 1")->fetch();
    if (!$normal) {
        $stmt = $pdo->prepare("INSERT INTO users(username, password_hash, role) VALUES (?, ?, 'normal')");
        $stmt->execute(['operator', password_hash('1234', PASSWORD_DEFAULT)]);
    }

    return $pdo;
}

function addYears(?string $date, int $years): ?string
{
    if (!$date) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt) {
        return null;
    }
    $dt->modify('+' . $years . ' years');
    return $dt->format('Y-m-d');
}

function appBaseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $https . '://' . $host;
}

function esc(string|null $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}
