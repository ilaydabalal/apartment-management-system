username: admin
password: admin123

Apartment Management System
A web-based software system developed in PHP using an API-first architecture, where residents can manage their dues payments and income-expense requests, suggestions, and complaint statuses.

Installation Steps
1. Database Setup
mysql -u root -p < database/schema.sql

2. Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'apartment_management');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

3. Default User
When the system runs for the first time, a default admin user is automatically created:

Username: admin

Password: admin123

Role: Super Admin

System Features
Status-Based Deletion
No records are physically deleted from the system. Instead:

status = 1: Active record

status = 0: Inactive (deleted) record

UPDATE users SET status = 0 WHERE id = ?;

SELECT * FROM users WHERE status = 1;

Permission System
The dynamic permission system operates on a module and action basis:

{
"role_id": 1,
"role_name": "Site Manager",
"permissions": {
"users": ["view", "create", "edit"],
"apartments": ["view", "create", "edit", "manage_units"],
"dues": ["view", "create", "edit", "payment_create"],
"finance": ["income_view", "expense_view", "reports_view"]
}
}

Frontend Usage
index.html - Login page

dashboard.html - Main control panel

Module pages are fully API-driven

Dynamic content loading via JavaScript

Responsive design



kullanici_adi: admin
sifre: admin123

# Apartman Yönetim Sistemi

PHP tabanlı, API-first mimaride geliştirilmiş site sakinlerinin aidat ödemelerini ve gelir-gider istek öneri şikayet durumlarını yönetebileceği web yazılım sistemi.

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
