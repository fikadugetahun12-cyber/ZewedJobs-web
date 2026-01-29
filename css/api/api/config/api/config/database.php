<?php
/**
 * Database Configuration
 */

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->database = DB_NAME;
    }
    
    public function connect() {
        if ($this->connection === null) {
            try {
                $this->connection = new mysqli(
                    $this->host,
                    $this->username,
                    $this->password,
                    $this->database
                );
                
                if ($this->connection->connect_error) {
                    throw new Exception("Connection failed: " . $this->connection->connect_error);
                }
                
                $this->connection->set_charset("utf8mb4");
                
            } catch (Exception $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->connection;
    }
    
    public function disconnect() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    public function query($sql, $params = []) {
        $conn = $this->connect();
        
        // Prepare statement
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters if any
        if (!empty($params)) {
            $types = '';
            $bind_params = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b'; // blob
                }
                $bind_params[] = $param;
            }
            
            array_unshift($bind_params, $types);
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($bind_params));
        }
        
        // Execute query
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Get result
        $result = $stmt->get_result();
        
        // For SELECT queries, fetch data
        if ($result) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            return $data;
        }
        
        // For INSERT, UPDATE, DELETE
        $affected_rows = $stmt->affected_rows;
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        return [
            'affected_rows' => $affected_rows,
            'insert_id' => $insert_id
        ];
    }
    
    public function insert($table, $data) {
        $keys = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');
        
        $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        return $this->query($sql, $values);
    }
    
    public function update($table, $data, $where, $where_params = []) {
        $set_parts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $set_parts[] = "$key = ?";
            $params[] = $value;
        }
        
        $params = array_merge($params, $where_params);
        $sql = "UPDATE $table SET " . implode(', ', $set_parts) . " WHERE $where";
        
        return $this->query($sql, $params);
    }
    
    public function select($table, $columns = '*', $where = null, $params = [], $limit = null, $order = null) {
        $sql = "SELECT $columns FROM $table";
        
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        if ($order) {
            $sql .= " ORDER BY $order";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }
    
    public function beginTransaction() {
        $this->connect()->begin_transaction();
    }
    
    public function commit() {
        $this->connect()->commit();
    }
    
    public function rollback() {
        $this->connect()->rollback();
    }
    
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    
    public function escape($value) {
        return $this->connect()->real_escape_string($value);
    }
}

// Load database credentials from environment or config file
$env = getenv('APP_ENV') ?: 'development';

if ($env === 'production') {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'production_user');
    define('DB_PASS', 'strong_password');
    define('DB_NAME', 'zewed_production');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'zewed_development');
}

// Create database instance
$db = new Database();
?>
