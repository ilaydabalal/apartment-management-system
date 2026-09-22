<?php

require_once 'auth.php';
require_once 'response.php';

class Yetki {
    private $auth;
    private $currentUser;
    
    private $modules = [
        'users' => [
            'view' => 'Kullanıcıları görüntüleme',
            'create' => 'Kullanıcı ekleme', 
            'edit' => 'Kullanıcı düzenleme',
            'delete' => 'Kullanıcı silme (pasifleştirme)',

        ],
        'apartments' => [
            'view' => 'Apartman bilgilerini görüntüleme',
            'create' => 'Apartman ekleme',
            'edit' => 'Apartman düzenleme', 
            'delete' => 'Apartman silme (pasifleştirme)',

        ],
        'dues' => [
            'view' => 'Aidat bilgilerini görüntüleme',
            'create' => 'Aidat belirleme',
            'edit' => 'Aidat düzenleme',
            'delete' => 'Aidat silme (pasifleştirme)', 

        ],
        'finance' => [
            'income_view' => 'Gelir görüntüleme',
            'income_create' => 'Gelir ekleme',
            'income_edit' => 'Gelir düzenleme',
            'expense_view' => 'Gider görüntüleme', 
            'expense_create' => 'Gider ekleme',
            'expense_edit' => 'Gider düzenleme',
           
        ],
        'roles' => [
            'view' => 'Rolleri görüntüleme',
            'create' => 'Rol oluşturma',
            'edit' => 'Rol düzenleme',
            'delete' => 'Rol silme (pasifleştirme)',
        ]
    ];
    
    public function __construct() {
        $this->auth = new Auth();
        $this->currentUser = $this->auth->getCurrentUser();
    }
    
    public function check($module, $action) {
        if (!$this->currentUser) {
            return false;
        }
        
        if ($this->currentUser['role_name'] === 'Süper Admin') {
            return true;
        }
        
        if (!$this->currentUser['permissions']) {
            return false;
        }
        
        $permissions = json_decode($this->currentUser['permissions'], true);
        
        if (!isset($permissions[$module])) {
            return false;
        }
        
        return in_array($action, $permissions[$module]);
    }
 
    public function require($module, $action) {
        if (!$this->currentUser) {
            Response::unauthorized('Oturum açmanız gerekiyor');
        }
        
        if (!$this->check($module, $action)) {
            Response::forbidden("Bu işlem için yetkiniz bulunmuyor: {$module}.{$action}");
        }
        
        return true;
    }
    
    public function checkAny($permissions) {
        foreach ($permissions as $permission) {
            [$module, $action] = explode('.', $permission);
            if ($this->check($module, $action)) {
                return true;
            }
        }
        return false;
    }

    public function checkAll($permissions) {
        foreach ($permissions as $permission) {
            [$module, $action] = explode('.', $permission);
            if (!$this->check($module, $action)) {
                return false;
            }
        }
        return true;
    }
    
    public function getUserPermissions() {
        if (!$this->currentUser) {
            return [];
        }
        
        if ($this->currentUser['role_name'] === 'Süper Admin') {
            return $this->modules; 
        }
        
        $permissions = json_decode($this->currentUser['permissions'], true);
        return $permissions ?: [];
    }

    public function getSystemModules() {
        return $this->modules;
    }

    public function validatePermissions($permissions) {
        $errors = [];
        
        if (!is_array($permissions)) {
            $errors[] = 'Yetkiler array formatında olmalıdır';
            return $errors;
        }
        
        foreach ($permissions as $module => $actions) {
            if (!isset($this->modules[$module])) {
                $errors[] = "Geçersiz modül: {$module}";
                continue;
            }
            
            if (!is_array($actions)) {
                $errors[] = "Modül işlemleri array formatında olmalıdır: {$module}";
                continue;
            }
            
            foreach ($actions as $action) {
                if (!isset($this->modules[$module][$action])) {
                    $errors[] = "Geçersiz işlem: {$module}.{$action}";
                }
            }
        }
        
        return $errors;
    }

    public function sanitizePermissions($permissions) {
        $clean = [];
        
        foreach ($permissions as $module => $actions) {
            if (isset($this->modules[$module]) && is_array($actions)) {
                $cleanActions = [];
                foreach ($actions as $action) {
                    if (isset($this->modules[$module][$action])) {
                        $cleanActions[] = $action;
                    }
                }
                if (!empty($cleanActions)) {
                    $clean[$module] = $cleanActions;
                }
            }
        }
        
        return $clean;
    }
    

    public function saveRolePermissions($roleId, $permissions) {
        $db = Database::getInstance();
        
        $errors = $this->validatePermissions($permissions);
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        $cleanPermissions = $this->sanitizePermissions($permissions);
        
        $result = $db->execute(
            "UPDATE roles SET permissions = ?, updated_at = NOW() WHERE id = ?",
            [json_encode($cleanPermissions, JSON_UNESCAPED_UNICODE), $roleId]
        );
        
        return $result > 0;
    }
    

    public static function middleware($module, $action) {
        $yetki = new self();
        $yetki->require($module, $action);
    }
    
    public function canEditOwnProfile() {
        return $this->currentUser !== null;
    }
    
    public function canAccessApartment($apartmentId) {
        if (!$this->currentUser) {
            return false;
        }
        
        if ($this->currentUser['role_name'] === 'Süper Admin') {
            return true;
        }

        return true;
    }

    public function getCurrentUserId() {
        return $this->currentUser ? $this->currentUser['id'] : null;
    }

    public function isActiveUser() {
        return $this->currentUser && $this->currentUser['status'] == 1;
    }
}

function requirePermission($module, $action) {
    $yetki = new Yetki();
    $yetki->require($module, $action);
}

function getCurrentUser() {
    $auth = new Auth();
    return $auth->getCurrentUser();
}
?>