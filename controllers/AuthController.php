<?php
// =====================================================
// CA-RBAC HIS — Authentication Controller (FIXED)
// =====================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ContextHandler.php';
require_once __DIR__ . '/../includes/RiskEngine.php';
require_once __DIR__ . '/../includes/AuditLogger.php';

class AuthController {
    private $db;
    private $context;
    private $risk;
    private $audit;

    public function __construct() {
        $this->db      = Database::getInstance();
        $this->context = new ContextHandler();
        $this->risk    = new RiskEngine();
        $this->audit   = new AuditLogger();
        $this->startSecureSession();
    }

    private function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
            session_start();
        }
    }

    public function login($staffId, $password) {
        $ip = $this->context->getClientIP();

        // Fetch user — JOIN on r.id not r.role_id
        $user = $this->db->fetchOne(
            "SELECT u.*, r.role_name, r.display_name as role_display, r.can_emergency_override
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.staff_id = ? AND u.is_active = 1",
            [trim($staffId)]
        );

        if (!$user) {
            $this->audit->log(null, 'LOGIN_FAILED', 'auth', null, 'denied', null, null, $ip,
                "Staff ID not found: {$staffId}");
            return ['success' => false, 'message' => 'Invalid Staff ID or password.'];
        }

        // Account locked?
        if ($user['is_locked']) {
            $this->audit->log($user['id'], 'LOGIN_LOCKED', 'auth', null, 'denied', null, null, $ip,
                'Account locked due to failed attempts');
            return ['success' => false, 'message' => 'Account is locked. Contact the administrator.'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = $user['failed_attempts'] + 1;
            $lock     = $attempts >= MAX_FAILED_ATTEMPTS ? 1 : 0;
            $this->db->query(
                "UPDATE users SET failed_attempts = ?, is_locked = ? WHERE id = ?",
                [$attempts, $lock, $user['id']]
            );
            $this->audit->log($user['id'], 'LOGIN_FAILED', 'auth', null, 'denied', null, null, $ip,
                "Wrong password. Attempt {$attempts}/" . MAX_FAILED_ATTEMPTS);
            $remaining = MAX_FAILED_ATTEMPTS - $attempts;
            $msg = $remaining > 0
                ? "Invalid credentials. {$remaining} attempt(s) remaining."
                : 'Account locked. Contact administrator.';
            return ['success' => false, 'message' => $msg];
        }

        // Gather context
        $ctx = $this->context->gatherContext($user['id']);

        // Calculate login-time risk
        $loginRisk = $this->risk->calculate($ctx, $user['role_id']);

        // Login is always allowed regardless of location
        // Location is only enforced when accessing patient EHR (in PolicyDecisionPoint.php)

        // Successful login — reset failed attempts
        $this->db->query(
            "UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE id = ?",
            [$user['id']]
        );

        // Create session token
        $token      = bin2hex(random_bytes(32));
        $expires    = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $fingerprint = $this->context->generateDeviceFingerprint();

        $this->db->query(
            "INSERT INTO login_sessions (user_id, session_token, ip_address, user_agent, device_fingerprint, is_trusted_network, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$user['id'], $token, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '',
             $fingerprint, $ctx['is_trusted_network'] ? 1 : 0, $expires]
        );

        // Register device if new
        $existing = $this->db->fetchOne(
            "SELECT id FROM device_registry WHERE user_id = ? AND device_fingerprint = ?",
            [$user['id'], $fingerprint]
        );
        if (!$existing) {
            $this->db->query(
                "INSERT INTO device_registry (user_id, device_fingerprint, device_type, user_agent, is_trusted)
                 VALUES (?, ?, ?, ?, ?)",
                [$user['id'], $fingerprint, $ctx['device_type'],
                 $_SERVER['HTTP_USER_AGENT'] ?? '',
                 $ctx['is_trusted_network'] ? 1 : 0]
            );
        }

        // Build session
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['staff_id']      = $user['staff_id'];
        $_SESSION['full_name']     = $user['full_name'];
        $_SESSION['role_id']       = $user['role_id'];
        $_SESSION['role_name']     = $user['role_name'];
        $_SESSION['role_display']  = $user['role_display'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['session_token'] = $token;
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['context']       = $ctx;
        $_SESSION['login_risk']    = $loginRisk;

        $this->context->logContext($user['id'], $ctx);
        $this->audit->log($user['id'], 'LOGIN_SUCCESS', 'auth', null, 'granted',
            $loginRisk['score'], $loginRisk['level'], $ip,
            "Successful login. Risk: {$loginRisk['level']} ({$loginRisk['score']})");

        // Role-based redirect
        $redirects = [
            'admin'      => 'views/admin/dashboard.php',
            'doctor'     => 'views/doctor/dashboard.php',
            'nurse'      => 'views/nurse/dashboard.php',
            'pharmacist' => 'views/pharmacist/dashboard.php',
            'lab_tech'   => 'views/lab/dashboard.php',
            'surgeon'    => 'views/surgeon/dashboard.php',
            'consultant' => 'views/consultant/dashboard.php',
        ];

        return [
            'success'  => true,
            'message'  => 'Login successful. Welcome, ' . $user['full_name'] . '.',
            'redirect' => $redirects[$user['role_name']] ?? 'views/shared/dashboard.php',
            'risk'     => $loginRisk,
            'user'     => $user,
        ];
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->db->query(
                "UPDATE login_sessions SET is_active = 0, logout_at = NOW()
                 WHERE user_id = ? AND is_active = 1",
                [$_SESSION['user_id']]
            );
            $this->audit->log($_SESSION['user_id'], 'LOGOUT', 'auth', null, 'info',
                null, null, null, 'User logged out');
        }
        session_destroy();
    }

    public function requireLogin() {
        $this->startSecureSession();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/index.php?timeout=1');
            exit;
        }
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            $this->logout();
            header('Location: ' . APP_URL . '/index.php?timeout=1');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    public function requireRole($roles) {
        $this->requireLogin();
        $roles = (array)$roles;
        if (!in_array($_SESSION['role_name'], $roles)) {
            header('Location: ' . APP_URL . '/views/shared/unauthorized.php');
            exit;
        }
    }

    public function isLoggedIn() {
        return !empty($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetchOne(
            "SELECT u.*, r.role_name, r.display_name as role_display
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?",
            [$_SESSION['user_id']]
        );
    }
}
