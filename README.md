
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

### Modüller ve Yetkiler

#### 1. Kullanıcı Yönetimi (`users`)
- `view` - Kullanıcıları görüntüleme
- `create` - Yeni kullanıcı ekleme
- `edit` - Kullanıcı düzenleme
- `delete` - Kullanıcı pasifleştirme
- `reset_password` - Şifre sıfırlama
- `assign_role` - Rol atama

#### 2. Apartman Yönetimi (`apartments`)
- `view` - Apartman bilgilerini görüntüleme
- `create` - Apartman ekleme
- `edit` - Apartman düzenleme
- `delete` - Apartman pasifleştirme
- `manage_units` - Daire yönetimi
- `view_residents` - Sakin bilgileri

#### 3. Aidat ve Borç Yönetimi (`dues`)
- `view` - Aidat bilgilerini görüntüleme
- `create` - Aidat belirleme
- `edit` - Aidat düzenleme
- `delete` - Aidat pasifleştirme
- `payment_create` - Ödeme kaydı oluşturma
- `payment_edit` - Ödeme düzenleme
- `debt_view` - Borç durumu görüntüleme

#### 4. Gelir-Gider Takibi (`finance`)
- `income_view` - Gelir görüntüleme
- `income_create` - Gelir ekleme
- `income_edit` - Gelir düzenleme
- `expense_view` - Gider görüntüleme
- `expense_create` - Gider ekleme
- `expense_edit` - Gider düzenleme
- `reports_view` - Raporları görüntüleme
- `reports_export` - Rapor dışa aktarma

#### 5. Yetki Grubu Yönetimi (`roles`)
- `view` - Rolleri görüntüleme
- `create` - Rol oluşturma
- `edit` - Rol düzenleme
- `delete` - Rol pasifleştirme
- `assign_permissions` - Yetki atama

##  Güvenlik Özellikleri

- **JWT Token** tabanlı kimlik doğrulama
- **CORS** koruması
- **SQL Injection** koruması (Prepared Statements)
- **XSS** koruması
- **Rate Limiting** API istekleri için
- **Şifre hashleme** (PASSWORD_DEFAULT)
- **Session** yönetimi

##  API Kullanımı

### Kimlik Doğrulama
```javascript
// Giriş yapma
const loginResponse = await fetch('/api/auth/login.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        username: 'admin',
        password: 'admin123'
    })
});

const data = await loginResponse.json();
localStorage.setItem('token', data.token);
```

### API İstekleri
```javascript
// Yetki gerektiren istek
const response = await fetch('/api/users/kullanicilar.php', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('token'),
        'Content-Type': 'application/json'
    }
});
```

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