<?php

class Response {
    
  public static function success($data = null, $message = null, $code = 200) {
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => true,
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
    
  
    public static function error($message, $code = 400, $errors = null) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
   
    public static function validationError($errors, $message = "Validation failed") {
        self::error($message, 422, $errors);
    }
   
    public static function unauthorized($message = "Yetkisiz erişim") {
        self::error($message, 401);
    }
   
    public static function forbidden($message = "Bu işlem için yetkiniz bulunmuyor") {
        self::error($message, 403);
    }
    
    public static function notFound($message = "Kayıt bulunamadı") {
        self::error($message, 404);
    }
   
    public static function serverError($message = "Sunucu hatası oluştu") {
        self::error($message, 500);
    }
    
    public static function paginated($data, $total, $page, $limit, $message = null) {
        $totalPages = ceil($total / $limit);
        
        $pagination = [
            'current_page' => (int)$page,
            'per_page' => (int)$limit,
            'total' => (int)$total,
            'total_pages' => (int)$totalPages,
            'has_more' => $page < $totalPages
        ];
        
        $response = [
            'data' => $data,
            'pagination' => $pagination
        ];
        
        self::success($response, $message);
    }
    
    public static function setCorsHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier);
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data['reset_time'] > time()) {
                if ($data['requests'] >= $maxRequests) {
                    self::error('Rate limit exceeded. Try again later.', 429);
                }
                $data['requests']++;
            } else {
                $data = ['requests' => 1, 'reset_time' => time() + $timeWindow];
            }
        } else {
            $data = ['requests' => 1, 'reset_time' => time() + $timeWindow];
        }
        
        file_put_contents($cacheFile, json_encode($data));
    }
}
?>