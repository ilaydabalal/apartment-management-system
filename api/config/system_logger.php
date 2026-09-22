<?php

if (!isset($pdo) || $pdo === null) {
    try {
        $host = 'localhost';
        $dbname = 'apartment_management';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PDO bağlantı hatası: " . $e->getMessage());
        $pdo = null;
    }
}

function logActivity($userId, $action, $module, $recordId = null, $oldData = null, $newData = null, $description = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            strtoupper($action),
            $module,
            $recordId,
            $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logCrudOperation($pdo, $userId, $operation, $tableName, $recordId, $oldData = null, $newData = null, $description = null) {
    try {
        if (!$description) {
            $description = generateCrudDescription($operation, $tableName, $recordId, $oldData, $newData);
        }
        
        return logActivity($userId, $operation, $tableName, $recordId, $oldData, $newData, $description);
    } catch (Exception $e) {
        error_log("logCrudOperation hatası: " . $e->getMessage());
        return false;
    }
}

function generateCrudDescription($operation, $tableName, $recordId, $oldData = null, $newData = null) {
    $operation = strtoupper($operation);
    
    switch ($operation) {
        case 'INSERT':
            return generateInsertDescription($tableName, $recordId, $newData);
        case 'UPDATE':
            return generateUpdateDescription($tableName, $recordId, $oldData, $newData);
        case 'DELETE':
            return generateDeleteDescription($tableName, $recordId, $oldData);
        default:
            return ucfirst($operation) . " işlemi: " . ucfirst($tableName) . " (ID: $recordId)";
    }
}

function generateInsertDescription($tableName, $recordId, $newData) {
    switch ($tableName) {
        case 'users':
            $username = $newData['username'] ?? 'N/A';
            $fullName = $newData['full_name'] ?? 'N/A';
            return "Yeni kullanıcı eklendi: $fullName ($username)";
            
        case 'apartments':
            $name = $newData['name'] ?? 'N/A';
            return "Yeni apartman eklendi: $name";
            
        case 'apartment_units':
            $unitNumber = $newData['unit_number'] ?? 'N/A';
            return "Yeni daire eklendi: $unitNumber";
            
        case 'roles':
            $name = $newData['name'] ?? 'N/A';
            return "Yeni rol eklendi: $name";
            
        case 'incomes':
            $amount = $newData['amount'] ?? 'N/A';
            $desc = $newData['description'] ?? 'N/A';
            return "Yeni gelir eklendi: $amount TL - $desc";
            
        case 'expenses':
            $amount = $newData['amount'] ?? 'N/A';
            $desc = $newData['description'] ?? 'N/A';
            return "Yeni gider eklendi: $amount TL - $desc";
            
        case 'dues':
            $amount = $newData['amount'] ?? 'N/A';
            $year = $newData['period_year'] ?? 'N/A';
            $month = $newData['period_month'] ?? 'N/A';
            return "Yeni aidat eklendi: $amount TL ($year/$month)";
            
        case 'support_tickets':
            $title = $newData['title'] ?? 'N/A';
            return "Yeni destek talebi: $title";
            
        default:
            return ucfirst($tableName) . " tablosuna yeni kayıt eklendi (ID: $recordId)";
    }
}

function generateUpdateDescription($tableName, $recordId, $oldData, $newData) {
    switch ($tableName) {
        case 'users':
            $oldName = $oldData['full_name'] ?? 'N/A';
            $newName = $newData['full_name'] ?? 'N/A';
            return "Kullanıcı güncellendi: $oldName → $newName (ID: $recordId)";
            
        case 'apartments':
            $oldName = $oldData['name'] ?? 'N/A';
            $newName = $newData['name'] ?? 'N/A';
            return "Apartman güncellendi: $oldName → $newName (ID: $recordId)";
            
        case 'incomes':
            $oldAmount = $oldData['amount'] ?? 'N/A';
            $newAmount = $newData['amount'] ?? 'N/A';
            return "Gelir güncellendi: $oldAmount TL → $newAmount TL (ID: $recordId)";
            
        case 'expenses':
            $oldAmount = $oldData['amount'] ?? 'N/A';
            $newAmount = $newData['amount'] ?? 'N/A';
            return "Gider güncellendi: $oldAmount TL → $newAmount TL (ID: $recordId)";
            
        case 'dues':
            $oldAmount = $oldData['amount'] ?? 'N/A';
            $newAmount = $newData['amount'] ?? 'N/A';
            return "Aidat güncellendi: $oldAmount TL → $newAmount TL (ID: $recordId)";
            
        default:
            return ucfirst($tableName) . " kaydı güncellendi (ID: $recordId)";
    }
}

function generateDeleteDescription($tableName, $recordId, $oldData) {
    switch ($tableName) {
        case 'users':
            $name = $oldData['full_name'] ?? ($oldData['username'] ?? 'N/A');
            return "Kullanıcı silindi: $name (ID: $recordId)";
            
        case 'apartments':
            $name = $oldData['name'] ?? 'N/A';
            return "Apartman silindi: $name (ID: $recordId)";
            
        case 'apartment_units':
            $unitNumber = $oldData['unit_number'] ?? 'N/A';
            return "Daire silindi: $unitNumber (ID: $recordId)";
            
        case 'roles':
            $name = $oldData['name'] ?? 'N/A';
            return "Rol silindi: $name (ID: $recordId)";
            
        case 'incomes':
            $amount = $oldData['amount'] ?? 'N/A';
            $desc = $oldData['description'] ?? 'N/A';
            return "Gelir silindi: $amount TL - $desc (ID: $recordId)";
            
        case 'expenses':
            $amount = $oldData['amount'] ?? 'N/A';
            $desc = $oldData['description'] ?? 'N/A';
            return "Gider silindi: $amount TL - $desc (ID: $recordId)";
            
        case 'dues':
            $amount = $oldData['amount'] ?? 'N/A';
            $year = $oldData['period_year'] ?? 'N/A';
            $month = $oldData['period_month'] ?? 'N/A';
            return "Aidat silindi: $amount TL ($year/$month) (ID: $recordId)";
            
        default:
            return ucfirst($tableName) . " kaydı silindi (ID: $recordId)";
    }
}

function logUserLogin($pdo, $userId, $username, $userFullName = null, $userAgent = null, $ipAddress = null) {
    try {
        $fullName = $userFullName ?: $username;
        $description = "Kullanıcı başarıyla giriş yaptı: $fullName ($username)";
        
        $loginData = [
            'Kullanıcı Adı' => $username,
            'Ad Soyad' => $fullName,
            'IP Adresi' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor'),
            'Tarayıcı' => $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor'),
            'Giriş Durumu' => 'Başarılı',
            'Giriş Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'LOGIN',
            'auth',
            $userId,
            json_encode($loginData, JSON_UNESCAPED_UNICODE),
            $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? null),
            $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? null),
            $description
        ]);
    } catch (Exception $e) {
        error_log("logUserLogin hatası: " . $e->getMessage());
        return false;
    }
}

function logFailedLogin($pdo, $username, $reason = 'Hatalı şifre', $ipAddress = null, $userAgent = null) {
    try {
        $description = "Başarısız giriş denemesi: $username - $reason";
        
        $failedLoginData = [
            'Kullanıcı Adı' => $username,
            'IP Adresi' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor'),
            'Tarayıcı' => $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor'),
            'Giriş Durumu' => 'Başarısız',
            'Hata Nedeni' => $reason,
            'Deneme Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            null,
            'LOGIN_FAILED',
            'auth',
            null,
            json_encode($failedLoginData, JSON_UNESCAPED_UNICODE),
            $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? null),
            $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? null),
            $description
        ]);
    } catch (Exception $e) {
        error_log("logFailedLogin hatası: " . $e->getMessage());
        return false;
    }
}

function logUserLogout($pdo, $userId, $username, $userFullName = null, $sessionDuration = null) {
    try {
        $fullName = $userFullName ?: $username;
        $description = "Kullanıcı çıkış yaptı: $fullName ($username)";
        
        $logoutData = [
            'Kullanıcı Adı' => $username,
            'Ad Soyad' => $fullName,
            'IP Adresi' => $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor',
            'Tarayıcı' => $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor',
            'Çıkış Durumu' => 'Normal Çıkış',
            'Oturum Süresi' => $sessionDuration ? formatSessionDuration($sessionDuration) : 'Bilinmiyor',
            'Çıkış Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'LOGOUT',
            'auth',
            $userId,
            json_encode($logoutData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
    } catch (Exception $e) {
        error_log("logUserLogout hatası: " . $e->getMessage());
        return false;
    }
}

function formatSessionDuration($seconds) {
    if (!$seconds || $seconds < 0) {
        return 'Bilinmiyor';
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $remainingSeconds = $seconds % 60;
    
    $parts = [];
    
    if ($hours > 0) {
        $parts[] = $hours . ' saat';
    }
    
    if ($minutes > 0) {
        $parts[] = $minutes . ' dakika';
    }
    
    if ($remainingSeconds > 0 && $hours == 0) {
        $parts[] = $remainingSeconds . ' saniye';
    }
    
    return empty($parts) ? '0 saniye' : implode(' ', $parts);
}

function logUserInsert($pdo, $userId, $recordId) {
    try {
        $stmt = $pdo->prepare("
        SELECT u.*, r.name AS role_name 
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$recordId]);
    $newData = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $username = $newData['username'] ?? 'N/A';
        $fullName = $newData['full_name'] ?? 'N/A';
        $description = "Yeni kullanıcı eklendi: $fullName ($username) [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'INSERT',
            'users',
            $recordId,
            json_encode([
                'Kullanıcı Adı' => $newData['username'],
                'Ad Soyad' => $newData['full_name'],
                'E-posta' => $newData['email'],
                'Telefon' => $newData['phone'],
                'Rol' => $newData['role_name']  
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logUserUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $affectedUser = $newData['full_name'] ?? ($newData['username'] ?? 'Bilinmeyen');

        $fieldLabels = [
            'username'  => 'Kullanıcı Adı',
            'email'     => 'E-posta',
            'full_name' => 'Ad Soyad',
            'phone'     => 'Telefon',
            'role_id'   => 'Rol',
            'status'    => 'Durum'
        ];

        $changes = [];
        $oldChanges = [];
        $newChanges = [];

        $fieldsToCheck = array_keys($fieldLabels);

        foreach ($fieldsToCheck as $field) {
            if (array_key_exists($field, $oldData) && array_key_exists($field, $newData)) {
                $oldValue = $oldData[$field] ?? '';
                $newValue = $newData[$field] ?? '';

                if (strval($oldValue) !== strval($newValue)) {
                    if ($field == 'role_id') {
                        $oldRoleName = getRoleName($pdo, $oldData[$field]);
                        $newRoleName = getRoleName($pdo, $newData[$field]);
                        $oldChanges[$fieldLabels[$field]] = $oldRoleName ?: 'Yok';
                        $newChanges[$fieldLabels[$field]] = $newRoleName ?: 'Yok';
                        $changes[] = "{$fieldLabels[$field]}: '$oldRoleName' → '$newRoleName'";
                    } elseif ($field == 'status') {
                        $oldStatus = $oldData[$field] == 1 ? 'Aktif' : 'Pasif';
                        $newStatus = $newData[$field] == 1 ? 'Aktif' : 'Pasif';
                        $oldChanges[$fieldLabels[$field]] = $oldStatus;
                        $newChanges[$fieldLabels[$field]] = $newStatus;
                        $changes[] = "{$fieldLabels[$field]}: '$oldStatus' → '$newStatus'";
                    } else {
                        $oldVal = $oldValue ?: 'Yok';
                        $newVal = $newValue ?: 'Yok';
                        $oldChanges[$fieldLabels[$field]] = $oldVal;
                        $newChanges[$fieldLabels[$field]] = $newVal;
                        $changes[] = "{$fieldLabels[$field]}: '$oldVal' → '$newVal'";
                    }
                }
            }
        }

        if (array_key_exists('password', $oldData) && array_key_exists('password', $newData)) {
            if ($oldData['password'] !== $newData['password']) {
                $changes[] = "Şifre güncellendi";
                $oldChanges['Şifre'] = '***';
                $newChanges['Şifre'] = '***';
            }
        }

        if (empty($changes)) {
            $description = "Kullanıcı güncellendi: $affectedUser [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['Durum' => 'Değişiklik yok'];
            $newChanges = ['Durum' => 'Değişiklik yok'];
        } else {
            $description = "Kullanıcı güncellendi: $affectedUser [ID: $recordId] - " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'UPDATE',
            'users',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logUserDelete($pdo, $userId, $recordId, $oldData) {
    try {
        $name = $oldData['full_name'] ?? ($oldData['username'] ?? 'Bilinmeyen');
        $description = "Kullanıcı silindi: $name [ID: $recordId]";

        $roleName = null;
        if (!empty($oldData['role_id'])) {
            $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $roleStmt->execute([$oldData['role_id']]);
            $roleName = $roleStmt->fetchColumn();
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'DELETE',
            'users',
            $recordId,
            json_encode([
                'Kullanıcı Adı' => $oldData['username'] ?? null,
                'Ad Soyad'      => $oldData['full_name'] ?? null,
                'E-posta'       => $oldData['email'] ?? null,
                'Rol'           => $roleName ?? 'Bilinmeyen'
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logApartmentInsert($pdo, $userId, $recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name AS manager_name
            FROM apartments a
            LEFT JOIN users u ON a.manager_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $name = $newData['name'] ?? 'N/A';
        $description = "Yeni apartman eklendi: $name [ID: $recordId]";

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'INSERT',
            'apartments',
            $recordId,
            json_encode([
                'Apartman Adı'    => $newData['name'] ?? null,
                'Adres'           => $newData['address'] ?? null,
                'Toplam Daire'    => $newData['total_units'] ?? null,
                'Yönetici'        => $newData['manager_name'] ?? 'Bilinmiyor'
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logApartmentUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM apartments WHERE id = ?");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $affectedApartment = $newData['name'] ?? 'Bilinmeyen Apartman';

        $fieldLabels = [
            'name'        => 'Apartman Adı',
            'address'     => 'Adres',
            'total_units' => 'Daire Sayısı',
            'manager_id'  => 'Yönetici',
            'tax_number'  => 'Vergi No',
            'phone'       => 'Telefon',
            'email'       => 'E-posta',
            'status'      => 'Durum'
        ];

        $changes = [];
        $oldChanges = [];
        $newChanges = [];

        $fieldsToCheck = array_keys($fieldLabels);

        foreach ($fieldsToCheck as $field) {
            if (array_key_exists($field, $oldData) && array_key_exists($field, $newData)) {
                $oldValue = $oldData[$field] ?? '';
                $newValue = $newData[$field] ?? '';

                if (strval($oldValue) !== strval($newValue)) {
                    $oldChanges[$fieldLabels[$field]] = $oldValue ?: 'Yok';
                    $newChanges[$fieldLabels[$field]] = $newValue ?: 'Yok';

                    if ($field == 'manager_id') {
                        $oldManagerName = getManagerName($pdo, $oldData[$field]);
                        $newManagerName = getManagerName($pdo, $newData[$field]);
                        $changes[] = "{$fieldLabels[$field]}: '$oldManagerName' → '$newManagerName'";
                    } elseif ($field == 'status') {
                        $oldStatus = $oldData[$field] == 1 ? 'Aktif' : 'Pasif';
                        $newStatus = $newData[$field] == 1 ? 'Aktif' : 'Pasif';
                        $changes[] = "{$fieldLabels[$field]}: '$oldStatus' → '$newStatus'";
                    } else {
                        $oldVal = $oldValue ?: 'Yok';
                        $newVal = $newValue ?: 'Yok';
                        $changes[] = "{$fieldLabels[$field]}: '$oldVal' → '$newVal'";
                    }
                }
            }
        }

        if (empty($changes)) {
            $description = "Apartman güncellendi: $affectedApartment [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['Durum' => 'Değişiklik yok'];
            $newChanges = ['Durum' => 'Değişiklik yok'];
        } else {
            $description = "Apartman güncellendi: $affectedApartment [ID: $recordId] - " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'UPDATE',
            'apartments',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logApartmentDelete($pdo, $userId, $recordId) {
    try {
        // Önce eski veriyi tam al
        $stmt = $pdo->prepare("SELECT * FROM apartments WHERE id = ?");
        $stmt->execute([$recordId]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        $name = $oldData['name'] ?? 'Bilinmeyen Apartman';
        $description = "Apartman silindi: $name [ID: $recordId]";

        $managerName = 'Bilinmeyen';
        if (!empty($oldData['manager_id'])) {
            $fetchedManager = getManagerName($pdo, $oldData['manager_id']);
            if (!empty($fetchedManager)) {
                $managerName = $fetchedManager;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'DELETE',
            'apartments',
            $recordId,
            json_encode([
                'Apartman Adı' => $oldData['name'] ?? 'Bilinmeyen',
                'Adres'        => $oldData['address'] ?? 'Bilinmeyen',
                'Daire Sayısı' => $oldData['total_units'] ?? 'Bilinmeyen',
                'Yönetici'     => $managerName
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR']     ?? 'Bilinmeyen',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmeyen',
            $description
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function getManagerName($pdo, $managerId) {
    if (!$managerId) return 'Yönetici Atanmamış';
    
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$managerId]);
        $manager = $stmt->fetch(PDO::FETCH_ASSOC);
        return $manager ? $manager['full_name'] : "Yönetici #$managerId";
    } catch (Exception $e) {
        return "Yönetici #$managerId";
    }
}

function logRoleInsert($pdo, $userId, $recordId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newData) return false;
        
        $roleName = $newData['name'] ?? 'N/A';
        $description = "Yeni rol eklendi: $roleName [ID: $recordId]";
        
        $userFriendlyData = [];
        
        $userFriendlyData['rol_adi'] = $newData['name'] ?? 'Belirtilmemiş';
        $userFriendlyData['aciklama'] = $newData['description'] ?: 'Açıklama Yok';
        
        $permissions = json_decode($newData['permissions'] ?? '{}', true) ?: [];
        $permsList = [];
        foreach ($permissions as $module => $perms) {
            $moduleNameTr = getModuleNameTurkish($module);
            $permNamesTr = array_map('getPermissionNameTurkish', $perms);
            $permsList[] = "$moduleNameTr: " . implode(', ', $permNamesTr);
        }
        $userFriendlyData['yetkiler'] = empty($permsList) ? 'Yetki Yok' : implode(' | ', $permsList);
        
        $userFriendlyData['durum'] = 'Aktif';
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'INSERT',
            'roles',
            $recordId,
            json_encode($userFriendlyData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
    } catch (Exception $e) {
        error_log("logRoleInsert hatası: " . $e->getMessage());
        return false;
    }
}

function logRoleDelete($pdo, $userId, $recordId, $oldData) {
    try {
        $roleName = $oldData['name'] ?? 'Bilinmeyen Rol';
        $description = "Rol silindi: $roleName [ID: $recordId]";
        
        $userFriendlyData = [];
        
        $userFriendlyData['rol_adi'] = $oldData['name'] ?? 'Belirtilmemiş';
        $userFriendlyData['aciklama'] = ($oldData['description'] ?? '') ?: 'Açıklama Yok';        

        $permissions = json_decode($oldData['permissions'] ?? '{}', true) ?: [];
        $permsList = [];
        foreach ($permissions as $module => $perms) {
            $moduleNameTr = getModuleNameTurkish($module);
            $permNamesTr = array_map('getPermissionNameTurkish', $perms);
            $permsList[] = "$moduleNameTr: " . implode(', ', $permNamesTr);
        }
        $userFriendlyData['yetkiler'] = empty($permsList) ? 'Yetki Yok' : implode(' | ', $permsList);
        
        $userFriendlyData['durum'] = ($oldData['status'] ?? 1) == 1 ? 'Aktif' : 'Pasif';
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'DELETE',
            'roles',
            $recordId,
            json_encode($userFriendlyData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
    } catch (Exception $e) {
        error_log("logRoleDelete hatası: " . $e->getMessage());
        return false;
    }
}

function logRoleUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $affectedRole = $newData['name'] ?? 'Bilinmeyen Rol';
        
        $changes = [];
        $oldChanges = [];
        $newChanges = [];
        
        $fieldsToCheck = ['name', 'description', 'permissions', 'status'];
        
        foreach ($fieldsToCheck as $field) {
            if (array_key_exists($field, $oldData) && array_key_exists($field, $newData)) {
                $oldValue = $oldData[$field];
                $newValue = $newData[$field];
                
                if ($oldValue === null) $oldValue = '';
                if ($newValue === null) $newValue = '';
                
                if (strval($oldValue) !== strval($newValue)) {
                    if ($field == 'name') {
                        $changes[] = "Rol Adı: '{$oldData[$field]}' → '{$newData[$field]}'";
                        $oldChanges['rol_adi'] = $oldData[$field];
                        $newChanges['rol_adi'] = $newData[$field];
                    } elseif ($field == 'description') {
                        $oldDesc = $oldData[$field] ?: 'Açıklama Yok';
                        $newDesc = $newData[$field] ?: 'Açıklama Yok';
                        $changes[] = "Açıklama: '$oldDesc' → '$newDesc'";
                        $oldChanges['aciklama'] = $oldDesc;
                        $newChanges['aciklama'] = $newDesc;
                    } elseif ($field == 'permissions') {
                        $changes[] = "Yetkiler güncellendi";
                        
                        $oldPerms = json_decode($oldData[$field], true) ?: [];
                        $oldPermsList = [];
                        foreach ($oldPerms as $module => $perms) {
                            $moduleNameTr = getModuleNameTurkish($module);
                            $permNamesTr = array_map('getPermissionNameTurkish', $perms);
                            $oldPermsList[] = "$moduleNameTr: " . implode(', ', $permNamesTr);
                        }
                        
                        $newPerms = json_decode($newData[$field], true) ?: [];
                        $newPermsList = [];
                        foreach ($newPerms as $module => $perms) {
                            $moduleNameTr = getModuleNameTurkish($module);
                            $permNamesTr = array_map('getPermissionNameTurkish', $perms);
                            $newPermsList[] = "$moduleNameTr: " . implode(', ', $permNamesTr);
                        }
                        
                        $oldChanges['yetkiler'] = empty($oldPermsList) ? 'Yetki Yok' : implode(' | ', $oldPermsList);
                        $newChanges['yetkiler'] = empty($newPermsList) ? 'Yetki Yok' : implode(' | ', $newPermsList);
                    } elseif ($field == 'status') {
                        $oldStatus = $oldData[$field] == 1 ? 'Aktif' : 'Pasif';
                        $newStatus = $newData[$field] == 1 ? 'Aktif' : 'Pasif';
                        $changes[] = "Durum: '$oldStatus' → '$newStatus'";
                        $oldChanges['durum'] = $oldStatus;
                        $newChanges['durum'] = $newStatus;
                    }
                }
            }
        }
        
        if (empty($changes)) {
            $description = "Rol güncellendi: $affectedRole [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['durum' => 'Değişiklik yok'];
            $newChanges = ['durum' => 'Değişiklik yok'];
        } else {
            $description = "Rol güncellendi: $affectedRole [ID: $recordId] - " . implode(', ', $changes);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'UPDATE',
            'roles',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function getModuleNameTurkish($module) {
    $moduleNames = [
        'users' => 'Kullanıcılar',
        'apartments' => 'Apartmanlar', 
        'units' => 'Daireler',
        'expenses' => 'Giderler',
        'incomes' => 'Gelirler',
        'dues' => 'Aidatlar',
        'operations' => 'İşlemler',
        'reports' => 'Raporlar',
        'settings' => 'Ayarlar'
    ];
    return $moduleNames[$module] ?? $module;
}

function getPermissionNameTurkish($permission) {
    $permissionNames = [
        'view' => 'Görüntüle',
        'create' => 'Ekle', 
        'edit' => 'Düzenle',
        'delete' => 'Sil',
        'ticket_create' => 'Talep Oluştur',
        'technician_view' => 'Teknisyen Görüntüle',
        'technician_edit' => 'Teknisyen Düzenle'
    ];
    return $permissionNames[$permission] ?? $permission;
}

function logUnitInsert($pdo, $userId, $recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT au.*, a.name as apartment_name 
            FROM apartment_units au 
            LEFT JOIN apartments a ON au.apartment_id = a.id 
            WHERE au.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $unitNumber = $newData['unit_number'] ?? 'N/A';
        $apartmentName = $newData['apartment_name'] ?? 'Bilinmeyen Apartman';
        $floor = $newData['floor'] ?? 'N/A';
        $description = "Yeni daire eklendi: $unitNumber ($apartmentName - $floor. kat) [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'INSERT',
            'apartment_units',
            $recordId,
            json_encode([
                'Apartman ID' => $newData['apartment_id'] ?? null,
                'Apartman Adı' => $newData['apartment_name'] ?? null,
                'Daire No' => $newData['unit_number'] ?? null,
                'Kat' => $newData['floor'] ?? null,
                'Mal Sahibi' => $newData['owner_name'] ?? null,
                'Mal Sahibi Tel' => $newData['owner_phone'] ?? null,
                'Mal Sahibi Email' => $newData['owner_email'] ?? null,
                'Oturan' => $newData['resident_name'] ?? null,
                'Oturan Tel' => $newData['resident_phone'] ?? null,
                'Oturan Email' => $newData['resident_email'] ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logUnitUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("
            SELECT au.*, a.name as apartment_name 
            FROM apartment_units au 
            LEFT JOIN apartments a ON au.apartment_id = a.id 
            WHERE au.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        $unitNumber = $newData['unit_number'] ?? ($oldData['unit_number'] ?? 'Bilinmeyen');
        $apartmentName = $newData['apartment_name'] ?? 'Bilinmeyen Apartman';

        $fieldLabels = [
            'apartment_id'   => 'Apartman',
            'unit_number'    => 'Daire No',
            'floor'          => 'Kat',
            'owner_name'     => 'Mal Sahibi',
            'owner_phone'    => 'Mal Sahibi Telefon',
            'owner_email'    => 'Mal Sahibi E-posta',
            'resident_name'  => 'Oturan',
            'resident_phone' => 'Oturan Telefon',
            'resident_email' => 'Oturan E-posta',
            'status'         => 'Durum'
        ];

        $changes = [];
        $oldChanges = [];
        $newChanges = [];

        foreach ($fieldLabels as $field => $label) {
            if (isset($oldData[$field]) && isset($newData[$field]) && $oldData[$field] != $newData[$field]) {

                $oldVal = $oldData[$field] ?: 'Bilinmeyen';
                $newVal = $newData[$field] ?: 'Bilinmeyen';

                if ($field == 'apartment_id') {
                    $oldVal = getApartmentName($pdo, $oldData[$field]) ?: 'Bilinmeyen';
                    $newVal = getApartmentName($pdo, $newData[$field]) ?: 'Bilinmeyen';
                } elseif ($field == 'status') {
                    $oldVal = $oldData[$field] == 1 ? 'Aktif' : 'Pasif';
                    $newVal = $newData[$field] == 1 ? 'Aktif' : 'Pasif';
                }

                $oldChanges[$label] = $oldVal;
                $newChanges[$label] = $newVal;
                $changes[] = "$label: '$oldVal' → '$newVal'";
            }
        }

        if (empty($changes)) {
            $description = "Daire güncellendi: $unitNumber ($apartmentName) [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['Değişiklik' => 'Yok'];
            $newChanges = ['Değişiklik' => 'Yok'];
        } else {
            $description = "Daire güncellendi: $unitNumber ($apartmentName) [ID: $recordId] - " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'UPDATE',
            'apartment_units',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'Bilinmeyen',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmeyen',
            $description
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function logUnitDelete($pdo, $userId, $recordId, $oldData) {
    try {
        $unitNumber = $oldData['unit_number'] ?? 'Bilinmeyen';
        $apartmentName = $oldData['apartment_name'] ?? 'Bilinmeyen Apartman';
        $floor = $oldData['floor'] ?? 'N/A';
        $description = "Daire silindi: $unitNumber ($apartmentName - $floor. kat) [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'DELETE',
            'apartment_units',
            $recordId,
            json_encode([
                'Apartman ID' => $oldData['apartment_id'] ?? null,
                'Apartman Adı' => $oldData['apartment_name'] ?? null,
                'Daire No' => $oldData['unit_number'] ?? null,
                'Kat' => $oldData['floor'] ?? null,
                'Mal Sahibi' => $oldData['owner_name'] ?? null,
                'Mal Sahibi Tel' => $oldData['owner_phone'] ?? null,
                'Mal Sahibi Email' => $oldData['owner_email'] ?? null,
                'Oturan' => $oldData['resident_name'] ?? null,
                'Oturan Tel' => $oldData['resident_phone'] ?? null,
                'Oturan Email' => $oldData['resident_email'] ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Log kaydetme hatası: " . $e->getMessage());
        return false;
    }
}

function getApartmentName($pdo, $apartmentId) {
    if (!$apartmentId) return 'Apartman Atanmamış';
    
    try {
        $stmt = $pdo->prepare("SELECT name FROM apartments WHERE id = ?");
        $stmt->execute([$apartmentId]);
        $apartment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $apartment ? $apartment['name'] : "Apartman #$apartmentId";
    } catch (Exception $e) {
        return "Apartman #$apartmentId";
    }
}

function getIncomeCategoryName($pdo, $categoryId) {
    if (!$categoryId) return 'Kategori Yok';
    
    try {
        $stmt = $pdo->prepare("SELECT name FROM income_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : 'Bilinmeyen Kategori';
    } catch (Exception $e) {
        return 'Bilinmeyen Kategori';
    }
}

function getUserFullName($pdo, $userId) {
    if (!$userId) return 'Kullanıcı Yok';
    
    try {
        $stmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result['full_name'] ?: $result['username'];
        }
        return 'Bilinmeyen Kullanıcı';
    } catch (Exception $e) {
        return 'Bilinmeyen Kullanıcı';
    }
}

function logIncomeInsert($pdo, $userId, $recordId) {
    try {
        error_log("📝 Log kaydediliyor - User ID: $userId, Record ID: $recordId");
        
        $userCheck = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ?");
        $userCheck->execute([$userId]);
        $userInfo = $userCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($userInfo) {
            error_log("👤 Kullanıcı doğrulandı: " . $userInfo['username'] . " (" . $userInfo['full_name'] . ")");
        } else {
            error_log("❌ Kullanıcı bulunamadı: $userId");
        }
        
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
            FROM incomes i 
            LEFT JOIN income_categories c ON i.category_id = c.id 
            LEFT JOIN apartments a ON i.apartment_id = a.id 
            LEFT JOIN users u ON i.created_by = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newData) {
            error_log("❌ Gelir bulunamadı: ID $recordId");
            return false;
        }
        
        $description = $newData['description'] ?? 'Gelir';
        $amount = number_format($newData['amount'] ?? 0, 2) . ' TL';
        $apartmentName = $newData['apartment_name'] ?? 'Genel';
        $categoryName = $newData['category_name'] ?? 'Kategori Yok';
        
        $logDescription = "Yeni gelir eklendi: $description ($amount - $apartmentName - $categoryName) [ID: $recordId]";
        
        error_log("💾 Kaydedilecek log verisi: " . json_encode([
            'user_id' => $userId,
            'action' => 'INSERT',
            'module' => 'incomes',
            'record_id' => $recordId,
            'description' => $logDescription
        ]));
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $userId,
            'INSERT',
            'incomes',
            $recordId,
            json_encode([
                'Apartman' => $newData['apartment_name'] ?? 'Genel',
                'Açıklama' => $newData['description'] ?? null,
                'Tutar' => $newData['amount'] ?? null,
                'Gelir Tarihi' => $newData['income_date'] ?? null,
                'Kategori' => $newData['category_name'] ?? null,
                'Oluşturan' => $newData['creator_name'] ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $logDescription
        ]);
        
        if ($result) {
            error_log("✅ Log başarıyla kaydedildi");
        } else {
            error_log("❌ Log kaydedilemedi");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("💥 Gelir ekleme log hatası: " . $e->getMessage());
        return false;
    }
}

function logIncomeUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
            FROM incomes i 
            LEFT JOIN income_categories c ON i.category_id = c.id 
            LEFT JOIN apartments a ON i.apartment_id = a.id 
            LEFT JOIN users u ON i.created_by = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$newData) {
            error_log("Güncellenen gelir bulunamadı: ID $recordId");
            return false;
        }

        $description = $newData['description'] ?? ($oldData['description'] ?? 'Gelir');
        $apartmentName = $newData['apartment_name'] ?? 'Genel';

        $fieldLabels = [
            'apartment_id'   => 'Apartman',
            'description'    => 'Açıklama',
            'amount'         => 'Tutar',
            'income_date'    => 'Gelir Tarihi',
            'category_id'    => 'Kategori',
            'created_by'     => 'Oluşturan'
        ];

        $changes = [];
        $oldChanges = [];
        $newChanges = [];

        foreach ($fieldLabels as $field => $label) {
            if (isset($oldData[$field]) && isset($newData[$field]) && $oldData[$field] != $newData[$field]) {

                $oldVal = $oldData[$field] ?: 'Boş';
                $newVal = $newData[$field] ?: 'Boş';

                if ($field == 'apartment_id') {
                    $oldVal = getApartmentName($pdo, $oldData[$field]) ?: 'Genel';
                    $newVal = getApartmentName($pdo, $newData[$field]) ?: 'Genel';
                } elseif ($field == 'category_id') {
                    $oldVal = getIncomeCategoryName($pdo, $oldData[$field]) ?: 'Kategori Yok';
                    $newVal = getIncomeCategoryName($pdo, $newData[$field]) ?: 'Kategori Yok';
                } elseif ($field == 'created_by') {
                    $oldVal = getUserFullName($pdo, $oldData[$field]) ?: 'Kullanıcı Yok';
                    $newVal = getUserFullName($pdo, $newData[$field]) ?: 'Kullanıcı Yok';
                } elseif ($field == 'amount') {
                    $oldVal = number_format($oldData[$field], 2) . ' TL';
                    $newVal = number_format($newData[$field], 2) . ' TL';
                }

                $oldChanges[$label] = $oldVal;
                $newChanges[$label] = $newVal;
                $changes[] = "$label: '$oldVal' → '$newVal'";
            }
        }

        if (empty($changes)) {
            $logDescription = "Gelir güncellendi: $description ($apartmentName) [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['Değişiklik' => 'Yok'];
            $newChanges = ['Değişiklik' => 'Yok'];
        } else {
            $logDescription = "Gelir güncellendi: $description ($apartmentName) [ID: $recordId] - " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'UPDATE',
            'incomes',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'Bilinmeyen',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmeyen',
            $logDescription
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Gelir güncelleme log hatası: " . $e->getMessage());
        return false;
    }
}

function logIncomeDelete($pdo, $userId, $recordId, $oldData) {
    try {
        $description = $oldData['description'] ?? 'Gelir';
        $amount = number_format($oldData['amount'] ?? 0, 2) . ' TL';
        $apartmentName = $oldData['apartment_name'] ?? 'Genel';
        $categoryName = getIncomeCategoryName($pdo, $oldData['category_id']) ?? 'Kategori Yok';
        
        $logDescription = "Gelir silindi: $description ($amount - $apartmentName - $categoryName) [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'DELETE',
            'incomes',
            $recordId,
            json_encode([
                'Apartman' => $apartmentName,
                'Açıklama' => $oldData['description'] ?? null,
                'Tutar' => $oldData['amount'] ?? null,
                'Gelir Tarihi' => $oldData['income_date'] ?? null,
                'Kategori' => $categoryName,
                'Oluşturan' => getUserFullName($pdo, $oldData['created_by']) ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $logDescription
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Gelir silme log hatası: " . $e->getMessage());
        return false;
    }
}

function getExpenseCategoryName($pdo, $categoryId) {
    if (!$categoryId) return 'Kategori Yok';
    
    try {
        $stmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : 'Bilinmeyen Kategori';
    } catch (Exception $e) {
        return 'Bilinmeyen Kategori';
    }
}

function logExpenseInsert($pdo, $userId, $recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
            FROM expenses e 
            LEFT JOIN expense_categories c ON e.category_id = c.id 
            LEFT JOIN apartments a ON e.apartment_id = a.id 
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newData) {
            error_log("Gider bulunamadı: ID $recordId");
            return false;
        }
        
        $description = $newData['description'] ?? 'Gider';
        $amount = number_format($newData['amount'] ?? 0, 2) . ' TL';
        $apartmentName = $newData['apartment_name'] ?? 'Bilinmeyen Apartman';
        $categoryName = $newData['category_name'] ?? 'Kategori Yok';
        $vendor = $newData['vendor'] ?? '';
        
        $logDescription = "Yeni gider eklendi: $description ($amount - $apartmentName - $categoryName" . 
                         ($vendor ? " - $vendor" : "") . ") [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'INSERT',
            'expenses',
            $recordId,
            json_encode([
                'Apartman' => $newData['apartment_name'] ?? null,
                'Açıklama' => $newData['description'] ?? null,
                'Tutar' => $newData['amount'] ?? null,
                'Gider Tarihi' => $newData['expense_date'] ?? null,
                'Kategori' => $newData['category_name'] ?? null,
                'Tedarikçi' => $newData['vendor'] ?? null,
                'Oluşturan' => $newData['creator_name'] ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $logDescription
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Gider ekleme log hatası: " . $e->getMessage());
        return false;
    }
}

function logExpenseUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, c.name as category_name, a.name as apartment_name, u.full_name as creator_name
            FROM expenses e 
            LEFT JOIN expense_categories c ON e.category_id = c.id 
            LEFT JOIN apartments a ON e.apartment_id = a.id 
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$newData) {
            error_log("Güncellenen gider bulunamadı: ID $recordId");
            return false;
        }

        $description = $newData['description'] ?? ($oldData['description'] ?? 'Gider');
        $apartmentName = $newData['apartment_name'] ?? 'Bilinmeyen Apartman';

        $fieldLabels = [
            'apartment_id'   => 'Apartman',
            'description'    => 'Açıklama',
            'amount'         => 'Tutar',
            'expense_date'   => 'Gider Tarihi',
            'category_id'    => 'Kategori',
            'vendor'         => 'Tedarikçi',
            'created_by'     => 'Oluşturan'
        ];

        $changes = [];
        $oldChanges = [];
        $newChanges = [];

        foreach ($fieldLabels as $field => $label) {
            if (isset($oldData[$field]) && isset($newData[$field]) && $oldData[$field] != $newData[$field]) {

                $oldVal = $oldData[$field] ?: 'Boş';
                $newVal = $newData[$field] ?: 'Boş';

                if ($field == 'apartment_id') {
                    $oldVal = getApartmentName($pdo, $oldData[$field]) ?: 'Bilinmeyen Apartman';
                    $newVal = getApartmentName($pdo, $newData[$field]) ?: 'Bilinmeyen Apartman';
                } elseif ($field == 'category_id') {
                    $oldVal = getExpenseCategoryName($pdo, $oldData[$field]) ?: 'Kategori Yok';
                    $newVal = getExpenseCategoryName($pdo, $newData[$field]) ?: 'Kategori Yok';
                } elseif ($field == 'created_by') {
                    $oldVal = getUserFullName($pdo, $oldData[$field]) ?: 'Kullanıcı Yok';
                    $newVal = getUserFullName($pdo, $newData[$field]) ?: 'Kullanıcı Yok';
                } elseif ($field == 'amount') {
                    $oldVal = number_format($oldData[$field], 2) . ' TL';
                    $newVal = number_format($newData[$field], 2) . ' TL';
                }

                $oldChanges[$label] = $oldVal;
                $newChanges[$label] = $newVal;
                $changes[] = "$label: '$oldVal' → '$newVal'";
            }
        }

        if (empty($changes)) {
            $logDescription = "Gider güncellendi: $description ($apartmentName) [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['Değişiklik' => 'Yok'];
            $newChanges = ['Değişiklik' => 'Yok'];
        } else {
            $logDescription = "Gider güncellendi: $description ($apartmentName) [ID: $recordId] - " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'UPDATE',
            'expenses',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'Bilinmeyen',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmeyen',
            $logDescription
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Gider güncelleme log hatası: " . $e->getMessage());
        return false;
    }
}

function logExpenseDelete($pdo, $userId, $recordId, $oldData) {
    try {
        $description = $oldData['description'] ?? 'Gider';
        $amount = number_format($oldData['amount'] ?? 0, 2) . ' TL';
        $apartmentName = $oldData['apartment_name'] ?? 'Bilinmeyen Apartman';
        $categoryName = getExpenseCategoryName($pdo, $oldData['category_id']) ?? 'Kategori Yok';
        $vendor = $oldData['vendor'] ?? '';
        
        $logDescription = "Gider silindi: $description ($amount - $apartmentName - $categoryName" . 
                         ($vendor ? " - $vendor" : "") . ") [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'DELETE',
            'expenses',
            $recordId,
            json_encode([
                'Apartman' => $apartmentName,
                'Açıklama' => $oldData['description'] ?? null,
                'Tutar' => $oldData['amount'] ?? null,
                'Gider Tarihi' => $oldData['expense_date'] ?? null,
                'Kategori' => $categoryName,
                'Tedarikçi' => $oldData['vendor'] ?? null,
                'Oluşturan' => getUserFullName($pdo, $oldData['created_by']) ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $logDescription
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Gider silme log hatası: " . $e->getMessage());
        return false;
    }
}

function getDuesTypeName($pdo, $typeId) {
    if (!$typeId) return 'Aidat Türü Yok';
    
    try {
        $stmt = $pdo->prepare("SELECT name FROM dues_types WHERE id = ?");
        $stmt->execute([$typeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : 'Bilinmeyen Aidat Türü';
    } catch (Exception $e) {
        return 'Bilinmeyen Aidat Türü';
    }
}

function getUnitInfo($pdo, $unitId) {
    if (!$unitId) return 'Daire Yok';
    
    try {
        $stmt = $pdo->prepare("SELECT au.unit_number, a.name as apartment_name 
                              FROM apartment_units au 
                              LEFT JOIN apartments a ON au.apartment_id = a.id 
                              WHERE au.id = ?");
        $stmt->execute([$unitId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result['apartment_name'] . ' - ' . $result['unit_number'];
        }
        return 'Bilinmeyen Daire';
    } catch (Exception $e) {
        return 'Bilinmeyen Daire';
    }
}

function logDuesInsert($pdo, $userId, $recordId) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dt.name as dues_type_name, au.unit_number, a.name as apartment_name,
                   u.full_name as created_by_name, au.resident_name
            FROM dues d 
            LEFT JOIN dues_types dt ON d.dues_type_id = dt.id 
            LEFT JOIN apartment_units au ON d.unit_id = au.id
            LEFT JOIN apartments a ON au.apartment_id = a.id 
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newData) {
            error_log("Aidat bulunamadı: ID $recordId");
            return false;
        }
        
        $duesTypeName = $newData['dues_type_name'] ?? 'Aidat';
        $amount = number_format($newData['due_amount'] ?? 0, 2) . ' TL';
        $unitInfo = ($newData['apartment_name'] ?? 'Bilinmeyen') . ' - ' . ($newData['unit_number'] ?? 'Bilinmeyen');
        $residentName = $newData['resident_name'] ?? 'Boş Daire';
        $period = ($newData['period_month'] ?? 0) . '/' . ($newData['period_year'] ?? 0);
        
        $logDescription = "Yeni aidat eklendi: $duesTypeName ($amount - $unitInfo - $residentName - $period) [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'INSERT',
            'dues',
            $recordId,
            json_encode([
                'Daire' => $unitInfo,
                'Sakin' => $newData['resident_name'] ?? null,
                'Aidat Türü' => $newData['dues_type_name'] ?? null,
                'Tutar' => $newData['due_amount'] ?? null,
                'Dönem' => $period,
                'Vade Tarihi' => $newData['due_date'] ?? null,
                'Oluşturan' => $newData['created_by_name'] ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $logDescription
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Aidat ekleme log hatası: " . $e->getMessage());
        return false;
    }
}

function logDuesUpdate($pdo, $userId, $recordId, $oldData) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dt.name as dues_type_name, au.unit_number, a.name as apartment_name,
                   u.full_name as created_by_name, au.resident_name
            FROM dues d 
            LEFT JOIN dues_types dt ON d.dues_type_id = dt.id 
            LEFT JOIN apartment_units au ON d.unit_id = au.id
            LEFT JOIN apartments a ON au.apartment_id = a.id 
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$recordId]);
        $newData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$newData) {
            error_log("Güncellenen aidat bulunamadı: ID $recordId");
            return false;
        }

        $duesTypeName = $newData['dues_type_name'] ?? 'Aidat';
        $unitInfo = ($newData['apartment_name'] ?? 'Bilinmeyen') . ' - ' . ($newData['unit_number'] ?? 'Bilinmeyen');
        $residentName = $newData['resident_name'] ?? 'Boş Daire';

        $fieldLabels = [
            'unit_id'        => 'Daire',
            'dues_type_id'   => 'Aidat Türü',
            'due_amount'     => 'Tutar',
            'period_year'    => 'Yıl',
            'period_month'   => 'Ay',
            'due_date'       => 'Vade Tarihi',
            'is_paid'        => 'Ödeme Durumu',
            'paid_amount'    => 'Ödenen Tutar',
            'payment_method' => 'Ödeme Yöntemi',
            'payment_date'   => 'Ödeme Tarihi',
            'receipt_number' => 'Makbuz No'
        ];

        $changes = [];
        $oldChanges = [];
        $newChanges = [];

        foreach ($fieldLabels as $field => $label) {
            if (isset($oldData[$field]) && isset($newData[$field]) && $oldData[$field] != $newData[$field]) {

                $oldVal = $oldData[$field] ?: 'Boş';
                $newVal = $newData[$field] ?: 'Boş';

                if ($field == 'unit_id') {
                    $oldVal = getUnitInfo($pdo, $oldData[$field]) ?: 'Bilinmeyen Daire';
                    $newVal = getUnitInfo($pdo, $newData[$field]) ?: 'Bilinmeyen Daire';
                } elseif ($field == 'dues_type_id') {
                    $oldVal = getDuesTypeName($pdo, $oldData[$field]) ?: 'Aidat Türü Yok';
                    $newVal = getDuesTypeName($pdo, $newData[$field]) ?: 'Aidat Türü Yok';
                } elseif ($field == 'due_amount' || $field == 'paid_amount') {
                    $oldVal = number_format($oldData[$field], 2) . ' TL';
                    $newVal = number_format($newData[$field], 2) . ' TL';
                } elseif ($field == 'is_paid') {
                    $oldVal = $oldData[$field] == 1 ? 'Ödendi' : 'Ödenmedi';
                    $newVal = $newData[$field] == 1 ? 'Ödendi' : 'Ödenmedi';
                } elseif ($field == 'payment_method') {
                    $methods = [
                        'cash' => 'Nakit',
                        'credit_card' => 'Kredi Kartı',
                        'bank_transfer' => 'Banka Havalesi',
                        'check' => 'Çek'
                    ];
                    $oldVal = $methods[$oldData[$field]] ?? $oldData[$field];
                    $newVal = $methods[$newData[$field]] ?? $newData[$field];
                }

                $oldChanges[$label] = $oldVal;
                $newChanges[$label] = $newVal;
                $changes[] = "$label: '$oldVal' → '$newVal'";
            }
        }

        if (empty($changes)) {
            $logDescription = "Aidat güncellendi: $duesTypeName ($unitInfo - $residentName) [ID: $recordId] - Değişiklik tespit edilemedi";
            $oldChanges = ['Değişiklik' => 'Yok'];
            $newChanges = ['Değişiklik' => 'Yok'];
        } else {
            $logDescription = "Aidat güncellendi: $duesTypeName ($unitInfo - $residentName) [ID: $recordId] - " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            'UPDATE',
            'dues',
            $recordId,
            json_encode($oldChanges, JSON_UNESCAPED_UNICODE),
            json_encode($newChanges, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'Bilinmeyen',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmeyen',
            $logDescription
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Aidat güncelleme log hatası: " . $e->getMessage());
        return false;
    }
}

function logDuesDelete($pdo, $userId, $recordId, $oldData) {
    try {
        $duesTypeName = getDuesTypeName($pdo, $oldData['dues_type_id']) ?? 'Aidat';
        $amount = number_format($oldData['due_amount'] ?? 0, 2) . ' TL';
        $unitInfo = getUnitInfo($pdo, $oldData['unit_id']) ?? 'Bilinmeyen Daire';
        $period = ($oldData['period_month'] ?? 0) . '/' . ($oldData['period_year'] ?? 0);
        
        $logDescription = "Aidat silindi: $duesTypeName ($amount - $unitInfo - $period) [ID: $recordId]";
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'DELETE',
            'dues',
            $recordId,
            json_encode([
                'Daire' => $unitInfo,
                'Aidat Türü' => $duesTypeName,
                'Tutar' => $oldData['due_amount'] ?? null,
                'Dönem' => $period,
                'Vade Tarihi' => $oldData['due_date'] ?? null,
                'Ödeme Durumu' => $oldData['is_paid'] == 1 ? 'Ödendi' : 'Ödenmedi',
                'Ödenen Tutar' => $oldData['paid_amount'] ?? null
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $logDescription
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Aidat silme log hatası: " . $e->getMessage());
        return false;
    }
}

function logBulkOperation($pdo, $userId, $operation, $tableName, $affectedIds, $description = null) {
    $description = $description ?: "Toplu $operation işlemi: " . count($affectedIds) . " kayıt etkilendi";
    
    return logActivity(
        $userId, 
        'BULK_' . strtoupper($operation), 
        $tableName, 
        null, 
        null, 
        ['affected_ids' => $affectedIds, 'count' => count($affectedIds)], 
        $description
    );
}

function cleanOldLogs($days = 90) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        
        $deletedCount = $stmt->rowCount();
        
        logActivity(1, 'CLEANUP', 'system_logs', null, null, ['days' => $days, 'deleted_count' => $deletedCount], 
                   "Eski log kayıtları temizlendi: $deletedCount kayıt silindi");
        
        return $deletedCount;
    } catch (Exception $e) {
        error_log("Log temizleme hatası: " . $e->getMessage());
        return false;
    }
}

function getRoleName($pdo, $roleId) {
    if (!$roleId) return 'Rol Atanmamış';
    
    try {
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return $role ? $role['name'] : "Rol #$roleId";
    } catch (Exception $e) {
        return "Rol #$roleId";
    }
}

function debugLogOperation($operation, $tableName, $recordId, $oldData, $newData) {
    error_log("=== LOG DEBUG ===");
    error_log("Operation: $operation");
    error_log("Table: $tableName");
    error_log("Record ID: $recordId");
    error_log("Old Data: " . json_encode($oldData));
    error_log("New Data: " . json_encode($newData));
    error_log("=================");
}

function logSupportTicketCreated($pdo, $userId, $username, $userFullName, $ticketId, $ticketNumber, $ticketData) {
    try {
        $description = "Yeni destek talebi oluşturuldu: $ticketNumber - {$ticketData['title']}";
        
        $categoryNames = [
            'elektrik' => 'Elektrik',
            'su_tesisati' => 'Su Tesisatı', 
            'asansor' => 'Asansör',
            'temizlik' => 'Temizlik',
            'guvenlik' => 'Güvenlik',
            'diger' => 'Diğer'
        ];
        
        $priorityNames = ['dusuk' => 'Düşük', 'orta' => 'Orta', 'yuksek' => 'Yüksek', 'acil' => 'Acil'];
        $statusNames = ['acik' => 'Açık', 'inceleniyor' => 'İnceleniyor', 'islemde' => 'İşlemde', 'beklemede' => 'Beklemede', 'cozuldu' => 'Çözüldü', 'iptal' => 'İptal'];
        
        $logData = [
            'Talep No' => $ticketNumber,
            'Başlık' => $ticketData['title'],
            'Kategori' => $categoryNames[$ticketData['category']] ?? $ticketData['category'],
            'Öncelik' => $priorityNames[$ticketData['priority']] ?? $ticketData['priority'],
            'Durum' => $statusNames[$ticketData['status']] ?? $ticketData['status'],
            'Konum' => $ticketData['location'],
            'Talep Eden' => $ticketData['requester_name'] ?? $userFullName,
            'Açıklama' => substr($ticketData['description'], 0, 100) . (strlen($ticketData['description']) > 100 ? '...' : ''),
            'Oluşturan Kullanıcı' => $userFullName . " ($username)",
            'IP Adresi' => $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor',
            'Oluşturma Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'INSERT', 
            'support',
            $ticketId,
            json_encode($logData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
    } catch (Exception $e) {
        error_log("logSupportTicketCreated hatası: " . $e->getMessage());
        return false;
    }
}

function logSupportTicketUpdated($pdo, $userId, $username, $userFullName, $ticketId, $ticketNumber, $oldData, $newData, $changes) {
    try {
        $changesList = [];
        foreach ($changes as $field => $change) {
            $changesList[] = "{$field}: '{$change['old']}' → '{$change['new']}'";
        }
        $changesText = implode(', ', $changesList);
        
        $description = "Destek talebi güncellendi: $ticketNumber - $changesText";
        
        $priorityNames = ['dusuk' => 'Düşük', 'orta' => 'Orta', 'yuksek' => 'Yüksek', 'acil' => 'Acil'];
        $statusNames = ['acik' => 'Açık', 'inceleniyor' => 'İnceleniyor', 'islemde' => 'İşlemde', 'beklemede' => 'Beklemede', 'cozuldu' => 'Çözüldü', 'iptal' => 'İptal'];
        
       $oldTechnicianName = 'Atanmamış';
       if (!empty($oldData['assigned_technician_id'])) {
           $oldTechStmt = $pdo->prepare("SELECT name FROM support_technicians WHERE id = ?");
           $oldTechStmt->execute([$oldData['assigned_technician_id']]);
           $oldTechnicianName = $oldTechStmt->fetchColumn() ?: 'Bilinmiyor';
       }
       
       $newTechnicianName = 'Atanmamış';
       if (!empty($newData['assigned_technician_id'])) {
           $newTechStmt = $pdo->prepare("SELECT name FROM support_technicians WHERE id = ?");
           $newTechStmt->execute([$newData['assigned_technician_id']]);
           $newTechnicianName = $newTechStmt->fetchColumn() ?: 'Bilinmiyor';
       }
       
       $oldDataFormatted = [
           'Öncelik' => $priorityNames[$oldData['priority']] ?? $oldData['priority'],
           'Durum' => $statusNames[$oldData['status']] ?? $oldData['status'],
           'Atanan Teknisyen' => $oldTechnicianName
       ];
       
       $newDataFormatted = [
           'Öncelik' => $priorityNames[$newData['priority']] ?? $newData['priority'],
           'Durum' => $statusNames[$newData['status']] ?? $newData['status'],
           'Atanan Teknisyen' => $newTechnicianName
       ];

        $logData = [
            'Talep No' => $ticketNumber,
            'Güncelleyen Kullanıcı' => $userFullName . " ($username)",
            'Değişiklikler' => $changesList, 
            'Güncelleme Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'UPDATE', 
            'support',
            $ticketId,
            json_encode($oldDataFormatted, JSON_UNESCAPED_UNICODE),
            json_encode($logData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
    } catch (Exception $e) {
        error_log("logSupportTicketUpdated hatası: " . $e->getMessage());
        return false;
    }
}

function logTechnicianCreated($pdo, $userId, $username, $userFullName, $technicianId, $technicianData) {
    try {
        $description = "Teknisyen eklendi: {$technicianData['name']} ({$technicianData['email']}) - Ekleyen: $userFullName";
        
        $logData = [
            'Teknisyen ID' => $technicianId,
            'Teknisyen Adı' => $technicianData['name'],
            'E-posta' => $technicianData['email'],
            'Telefon' => $technicianData['phone'],
            'Durum' => 'Aktif',
            'Ekleyen Kullanıcı' => $userFullName . " ($username)",
            'IP' => $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor',
            'Tarayıcı' => $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor',
            'Ekleme Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'INSERT',
            'Teknisyen İşlemleri',
            $technicianId,
            json_encode($logData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
    } catch (Exception $e) {
        error_log("logTechnicianCreated hatası: " . $e->getMessage());
        return false;
    }
}

function logTechnicianUpdated($pdo, $userId, $username, $userFullName, $technicianId, $oldData, $newData, $changes) {
    try {
        $changesList = [];
        foreach ($changes as $field => $change) {
            $changesList[] = "{$field}: '{$change['old']}' → '{$change['new']}'";
        }
        $changesText = implode(', ', $changesList);
        
        $description = "Teknisyen güncellendi: {$newData['name']} - $changesText - Güncelleyen: $userFullName";
        
        $oldDataFormatted = [
            'Teknisyen Adı' => $oldData['name'],
            'Email' => $oldData['email'],
            'Telefon' => $oldData['phone']
        ];
        
        $newDataFormatted = [
            'Teknisyen Adı' => $newData['name'],
            'Email' => $newData['email'],
            'Telefon' => $newData['phone']
        ];
        
        $logData = [
            'Teknisyen ID' => $technicianId,
            'Teknisyen Adı' => $newData['name'],
            'Güncelleyen Kullanıcı' => $userFullName . " ($username)",
            'Değişiklikler' => $changesList,
            'Güncelleme Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            'UPDATE',
            'Teknisyen İşlemleri',
            $technicianId,
            json_encode($oldDataFormatted, JSON_UNESCAPED_UNICODE),
            json_encode($logData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
    } catch (Exception $e) {
        error_log("logTechnicianUpdated hatası: " . $e->getMessage());
        return false;
    }
}

function logTechnicianDeleted($pdo, $userId, $username, $userFullName, $technicianId, $technicianData) {
    try {
        $description = "Teknisyen kaldırıldı: {$technicianData['name']} ({$technicianData['email']}) - Kaldıran: $userFullName";
        
        $logData = [
            'Teknisyen ID' => $technicianId,
            'Teknsiyen Adı' => $technicianData['name'],
            'E-posta' => $technicianData['email'],
            'Telefon' => $technicianData['phone'],
            'Eski Durum' => 'Aktif',
            'Yeni Durum' => 'Pasif',
            'Kaldıran Kullanıcı' => $userFullName . " ($username)",
            'IP' => $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor',
            'Tarayıcı' => $_SERVER['HTTP_USER_AGENT'] ?? 'Bilinmiyor',
            'Kaldırma Zamanı' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs
            (user_id, action, module, record_id, old_data, new_data, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $oldDataFormatted = [
            'Teknisyen Adı' => $technicianData['name'],
            'Email' => $technicianData['email'],
            'Telefon' => $technicianData['phone'],
            'Durum' => 'Aktif'
        ];
        
        return $stmt->execute([
            $userId,
            'DELETE',
            'Teknisyen İşlemleri',
            $technicianId,
            json_encode($oldDataFormatted, JSON_UNESCAPED_UNICODE),
            json_encode($logData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $description
        ]);
        
    } catch (Exception $e) {
        error_log("logTechnicianDeleted hatası: " . $e->getMessage());
        return false;
    }
}
?>