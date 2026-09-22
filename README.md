
kullanici_adi: admin
sifre: admin123

# Apartman Yönetim Sistemi

PHP tabanlı, API-first mimaride geliştirilmiş site sakinlerinin aidat ödemelerini ve gelir-gider istek öneri şikayet durumlarını yönetebileceği web yazılım sistemi.

##  Proje Yapısı

```
apartment-management/
├── README.md
├── config/
│   ├── database.php          # Veritabanı bağlantı ayarları
│   └── config.php            # Genel sistem ayarları
├── api/                      # Backend API endpoint'leri
│   ├── auth/
│   │   ├── login.php         # Kullanıcı girişi
│   │   ├── logout.php        # Çıkış işlemi
│   │   └── check-auth.php    # Oturum kontrolü
│   ├── users/
│   │   ├── kullanicilar.php  # Kullanıcı CRUD işlemleri
│   │   ├── permissions.php   # Yetki kontrolü
│   │   └── roles.php         # Rol yönetimi
│   ├── apartments/
│   │   ├── apartmanlar.php   # Apartman CRUD
│   │   └── daireler.php      # Daire yönetimi
│   ├── dues/
│   │   ├── aidatlar.php      # Aidat işlemleri
│   │   ├── odemeler.php      # Ödeme kayıtları
│   │   └── borclar.php       # Borç durumu
│   ├── finance/
│   │   ├── gelirler.php      # Gelir kayıtları
│   │   ├── giderler.php      # Gider kayıtları
│   │   └── raporlar.php      # Mali raporlar
│   └── core/
│       ├── database.php      # Veritabanı sınıfı
│       ├── auth.php          # Kimlik doğrulama
│       ├── yetki.php         # Yetki kontrolü
│       └── response.php      # API yanıt formatı
├── frontend/                 # Frontend dosyaları
│   ├── index.html           # Ana sayfa / Giriş
│   ├── dashboard.html       # Ana panel
│   ├── users.html           # Kullanıcı yönetimi
│   ├── apartments.html      # Apartman yönetimi
│   ├── dues.html            # Aidat yönetimi
│   ├── finance.html         # Gelir-gider
│   ├── roles.html           # Yetki grupları
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css    # Ana stil dosyası
│   │   └── js/
│   │       ├── app.js       # Ana JavaScript
│   │       ├── api.js       # API iletişim fonksiyonları
│   │       └── auth.js      # Kimlik doğrulama
│   └── components/          # Yeniden kullanılabilir bileşenler
├── database/
│   └── schema.sql           # Veritabanı yapısı
└── docs/
    ├── api-endpoints.md     # API dökümantasyonu
    └── permissions.json     # Yetki sistemi şeması
```

##  Kurulum Adımları

### 1. Veritabanı Kurulumu
```sql
mysql -u root -p < database/schema.sql
```

### 2. Yapılandırma
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'apartment_management');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 4. İlk Kullanıcı
Sistem ilk çalıştırıldığında varsayılan admin kullanıcısı oluşturulur:
- **Kullanıcı:** admin
- **Şifre:** admin123
- **Rol:** Süper Admin

##  Sistem Özellikleri
##  Status Tabanlı Silme

Sistemde hiçbir kayıt fiziksel olarak silinmez. Bunun yerine:
- **status = 1**: Aktif kayıt
- **status = 0**: Pasif (silinmiş) kayıt

```sql
UPDATE users SET status = 0 WHERE id = ?;

SELECT * FROM users WHERE status = 1;
```

##  Yetki Sistemi

Dinamik yetki sistemi modül ve işlem bazlı çalışır:

```json
{
    "role_id": 1,
    "role_name": "Site Yöneticisi",
    "permissions": {
        "users": ["view", "create", "edit"],
        "apartments": ["view", "create", "edit", "manage_units"],
        "dues": ["view", "create", "edit", "payment_create"],
        "finance": ["income_view", "expense_view", "reports_view"]
    }
}
```

##  Frontend Kullanımı

1. **index.html** - Giriş sayfası
2. **dashboard.html** - Ana kontrol paneli
3. Modül sayfaları tamamen API tabanlı
4. JavaScript ile dinamik içerik yükleme
5. Responsive tasarım
