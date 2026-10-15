<?php
// =====================================================
// CA-RBAC HIS — Audit Logger (Audit Module)
// =====================================================
require_once __DIR__ . '/../config/database.php';

class AuditLogger {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function log($userId, $action, $module = 'system', $patientId = null,
                        $decision = 'info', $riskScore = null, $riskLevel = null,
                        $ip = null, $details = null) {
        $ip       = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $riskLevel = $riskLevel ?? 'Low';

        $this->db->query(
            "INSERT INTO audit_logs (user_id, action, module, patient_id, decision, risk_score, risk_level, ip_address, user_agent, details)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$userId, $action, $module, $patientId, $decision, $riskScore, $riskLevel, $ip, $ua, $details]
        );
    }

    public function getLogs($limit = 100, $filters = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['decision'])) {
            $where[] = 'al.decision = ?';
            $params[] = $filters['decision'];
        }
        if (!empty($filters['module'])) {
            $where[] = 'al.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = "SELECT al.*, u.full_name, u.staff_id, r.role_name,
                       p.full_name AS patient_name, p.patient_id AS patient_code
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN patients p ON al.patient_id = p.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY al.created_at DESC LIMIT ?";
        $params[] = (int)$limit;

        return $this->db->fetchAll($sql, $params);
    }

    public function getStats() {
        return [
            'total'       => $this->db->fetchOne("SELECT COUNT(*) as c FROM audit_logs")['c'],
            'granted'     => $this->db->fetchOne("SELECT COUNT(*) as c FROM audit_logs WHERE decision='granted'")['c'],
            'denied'      => $this->db->fetchOne("SELECT COUNT(*) as c FROM audit_logs WHERE decision='denied'")['c'],
            'today'       => $this->db->fetchOne("SELECT COUNT(*) as c FROM audit_logs WHERE DATE(created_at)=CURDATE()")['c'],
            'high_risk'   => $this->db->fetchOne("SELECT COUNT(*) as c FROM audit_logs WHERE risk_level IN ('High','Critical')")['c'],
        ];
    }
}
