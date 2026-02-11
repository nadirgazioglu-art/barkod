# Atex Yangın Barkod Takip (PHP + SQLite)

Bu sürüm Hostixo uyumluluğu için **tamamen PHP + SQLite** olarak düzenlendi.

## Çalışma mantığı (tek klasör: `/barkod`)

Domain kökü `/barkod` klasörüne bağlıysa:

- `https://barkod.atexyangin.com.tr` -> `index.php` -> `login.php`
- `https://barkod.atexyangin.com.tr/dashboard.php` -> dashboard
- `https://barkod.atexyangin.com.tr/check.php?token=...` -> doğrulama sayfası

## İlk giriş

- Süper kullanıcı: `super` / `1234`
- Normal kullanıcı: `operator` / `1234`

> Veritabanı dosyası otomatik oluşur: `barkod.db`

## Sayfalar

- `login.php`: Giriş ekranı (firma ayarındaki ad + logo görünür)
- `dashboard.php`: Genel özet, yaklaşan tarihler, son doğrulatmalar
- `customers.php`: Müşteri kayıt/liste
- `customer_detail.php`: Müşteri düzenleme + etiket geçmişi
- `labels.php`: Etiket üretimi, seri artırma, QR token üretimi
- `settings.php`: Firma ayarları + kullanıcı yönetimi
- `check.php`: QR/Token doğrulama (logo ortada)

## Test verisini sıfırlama

Dashboard üzerindeki **Test Verilerini Sıfırla** butonu:
- `verification_logs`, `labels`, `customers` kayıtlarını temizler
- kullanıcıları ve firma ayarlarını korur
