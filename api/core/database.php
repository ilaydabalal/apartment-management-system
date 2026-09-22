<?php


require_once __DIR__ . '/../../config/database.php';

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        $this->connect();
    }
    
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
  
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            throw new Exception("Veritabanı bağlantısı kurulamadı");
        }
    }
    
  
    public function getConnection() {
        return $this->connection;
    }
    
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("SQL Hatası: " . $e->getMessage() . " - SQL: " . $sql);
            throw new Exception("Veritabanı sorgu hatası");
        }
    }
    
   
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
   
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
  
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
    
public function execute($sql, $params = []) {
    $stmt = $this->query($sql, $params);
    return $stmt->rowCount();
}
    

    public function count($table, $where = "", $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        $result = $this->fetch($sql, $params);
        return (int)$result['count'];
    }
    

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
  
    public function commit() {
        return $this->connection->commit();
    }
   
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function escape($string) {
        return $this->connection->quote($string);
    }
  
    public function close() {
        $this->connection = null;
    }
 
    public function getLastError() {
        $errorInfo = $this->connection->errorInfo();
        return $errorInfo[2] ?? null;
    }
}
?>