<?php

require_once 'database.php';
require_once 'response.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    
    public function generateToken($userId, $userData = []) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRE_TIME,
            'data' => $userData
        ];
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $token = $base64Header . "." . $base64Payload . "." . $base64Signature;
        
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + JWT_EXPIRE_TIME);
        
        $this->db->query(
            "INSERT INTO user_sessions (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
            [$userId, $tokenHash, $expiresAt]
        );
        
        return $token;
    }
    
   
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $token = str_replace('Bearer ', '', $token);
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        [$header, $payload, $signature] = $parts;
        
        $validSignature = hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true);
        $validSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));
        
        if (!hash_equals($signature, $validSignature)) {
            return false;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (!$payload || $payload['exp'] < time()) {
            return false;
        }
        
        $tokenHash = hash('sha256', $token);
        $session = $this->db->fetch(
            "SELECT * FROM user_sessions WHERE token_hash = ? AND expires_at > NOW()",
            [$tokenHash]
        );
        
        if (!$session) {
            return false;
        }
        
        return $payload;
    }
    
    
    public function authenticate($username, $password) {
        $sql = "SELECT u.*, r.name as role_name, r.permissions 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE (u.username = ? OR u.email = ?) AND u.status = 1";
        
        $user = $this->db->fetch($sql, [$username, $username]);
        
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        unset($user['password']);
        
        return $user;
    }
    
  
    public function getCurrentUser() {
        $token = $this->getTokenFromRequest();
        $payload = $this->validateToken($token);
        
        if (!$payload) {
            return null;
        }
        
        $sql = "SELECT u.*, r.name as role_name, r.permissions 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ? AND u.status = 1";
        
        $user = $this->db->fetch($sql, [$payload['user_id']]);
        
        if ($user) {
            unset($user['password']);
        }
        
        return $user;
    }
    
    
    public function getTokenFromRequest() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            return $headers['Authorization'];
        }
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return $value;
            }
        }
        
        return null;
    }
    
    
    public function logout() {
        $token = $this->getTokenFromRequest();
        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            $tokenHash = hash('sha256', $token);
            
            $this->db->execute(
                "DELETE FROM user_sessions WHERE token_hash = ?",
                [$tokenHash]
            );
        }
        
        return true;
    }
 
    public function cleanExpiredTokens() {
        return $this->db->execute("DELETE FROM user_sessions WHERE expires_at < NOW()");
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
   
    public static function isStrongPassword($password) {
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
    
    public function checkLoginAttempts($identifier) {
        $cacheFile = sys_get_temp_dir() . '/login_attempts_' . md5($identifier);
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data['blocked_until'] > time()) {
                Response::error('Çok fazla başarısız deneme. Lütfen daha sonra tekrar deneyin.', 429);
            }
            
            if ($data['attempts'] >= MAX_LOGIN_ATTEMPTS) {
                $data['blocked_until'] = time() + LOGIN_BLOCK_TIME;
                file_put_contents($cacheFile, json_encode($data));
                Response::error('Çok fazla başarısız deneme. Hesap geçici olarak bloke edildi.', 429);
            }
        }
    }
    
 
    public function recordFailedAttempt($identifier) {
        $cacheFile = sys_get_temp_dir() . '/login_attempts_' . md5($identifier);
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $data['attempts']++;
        } else {
            $data = ['attempts' => 1, 'blocked_until' => 0];
        }
        
        file_put_contents($cacheFile, json_encode($data));
    }
    

    public function resetLoginAttempts($identifier) {
        $cacheFile = sys_get_temp_dir() . '/login_attempts_' . md5($identifier);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
?>