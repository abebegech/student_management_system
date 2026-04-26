<?php
/**
 * Secure Database Connection Class using PDO
 * Implements prepared statements and connection management
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $host = 'localhost';
    private $dbname = 'student_system';
    private $username = 'root';
    private $password = '';
    
    // Private constructor to prevent direct creation
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get PDO connection
    public function getConnection() {
        return $this->pdo;
    }
    
    // Prepare and execute query with parameters
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
        }
    }
    
    // Fetch single record
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Fetch all records
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Insert record and return last insert ID
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    // Update record and return affected rows
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Delete record and return affected rows
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Check if record exists
    public function exists($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn() > 0;
    }
    
    // Get count of records
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return (int)$stmt->fetchColumn();
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->pdo->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
