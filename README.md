# Atex Yangın Barkod Takip

Flask + SQLite tabanlı yangın söndürücü etiket/barkod takip uygulaması.

## Kurulum (lokal)

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python app.py
```

## Hostixo dosya yapısı (tek klasör: `/barkod`)

Uygulama, domain kökü **zaten `/barkod` klasörüne bağlıymış gibi** çalışacak şekilde ayarlandı.

Beklenen davranış:

- `https://barkod.atexyangin.com.tr` → direkt login
- `https://barkod.atexyangin.com.tr/login` → login
- `https://barkod.atexyangin.com.tr/dashboard` → dashboard
- `https://barkod.atexyangin.com.tr/check/<token>` → QR doğrulama

Ek uyumluluk dosyaları da eklendi:

- `index.php` → `/login` yönlendirir
- `login.php` ve `login.html` → `/login` yönlendirir
- `dashboard.php` ve `dashboard.html` → `/dashboard` yönlendirir

> Not: Eski `/barkod/...` prefix zorunluluğu kaldırıldı. Gerekirse tekrar `BASE_PATH` env ile farklı prefix verilebilir.

## İlk giriş

- Süper kullanıcı: `super` / `1234`
- Normal kullanıcı: `operator` / `1234`

## Önemli özellikler

- Tek kullanıcı/tek aktif oturum mantığı.
- Müşteri ekleme, düzenleme.
- Etiket üretimi, çoklu seri no artırımı.
- Dolum +1 yıl, son kullanma +4 yıl otomatik hesap.
- QR doğrulama sayfası (`/check/<token>`) + log (IP, tarih, user-agent).
- Firma ayarları (TSE no, YSC tipleri, dashboard limitleri).
- Login ekranı firma adı + firma logosunu firma ayarlarından okur.
- Test verilerini sıfırlama (`Dashboard > Test Verilerini Sıfırla`).

## Gerçek sürüme geçiş için sıfırlama

Süper kullanıcı ile giriş yapıp dashboard üzerindeki **Test Verilerini Sıfırla** butonunu kullanın.
Bu işlem müşteri/etiket/doğrulama kayıtlarını temizler, kullanıcıları ve firma ayarlarını korur.
