<?php
// =====================================================
// SAMPLE CONFIGURATION
// Copy this file to config/database.php and fill in your own values.
// config/database.php is listed in .gitignore, so your real credentials
// are never committed to the repository.
// =====================================================

// Show errors only while developing locally. Keep these off on a live server.
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');                      // your MySQL password
define('DB_NAME', 'ca_rbac_his');
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('APP_NAME', 'CA-RBAC Hospital Information System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/ca_rbac_his');

// Session settings
define('SESSION_TIMEOUT', 1800);        // 30 minutes
define('MAX_FAILED_ATTEMPTS', 5);       // Account lock threshold
define('EMERGENCY_ACCESS_DURATION', 2); // Hours

// Risk score thresholds
define('RISK_LOW_MAX', 20);
define('RISK_MEDIUM_MAX', 50);
define('RISK_HIGH_MAX', 80);

// =====================================================
// HOSPITAL LOCATION (GPS geofence)
// Set these to the coordinates of the place where you are testing.
// Access is only allowed within HOSPITAL_RADIUS_METRES of this point.
// =====================================================
define('HOSPITAL_LAT', 0.000000);    // Latitude  (replace with your own)
define('HOSPITAL_LNG', 0.000000);    // Longitude (replace with your own)
define('HOSPITAL_RADIUS_METRES', 150);
define('HOSPITAL_NAME', 'University Hospital (Simulated)');

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed. Please check your configuration.']));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
