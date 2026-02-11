# Atex Yangın Barkod Takip

Flask + SQLite tabanlı yangın söndürücü etiket/barkod takip uygulaması.

## Kurulum (lokal)

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python app.py
```

## Hostixo / `/barkod` klasöründe yayınlama

Bu uygulama `BASE_PATH=/barkod` ile gelir. Bu yüzden aşağıdaki gibi çalışır:

- Login: `https://barkod.atexyangin.com.tr/barkod/login`
- Dashboard: `https://barkod.atexyangin.com.tr/barkod/dashboard`
- QR kontrol: `https://barkod.atexyangin.com.tr/barkod/check/<token>`

Eğer domain kökünden çalıştıracaksanız:

```bash
export BASE_PATH=/
```

Passenger / WSGI için giriş dosyası: `wsgi.py` (`application = app`).

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
