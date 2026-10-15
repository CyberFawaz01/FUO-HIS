<?php
// =====================================================
// CA-RBAC HIS — Policy Decision Point (PDP)
// Core authorization engine with location + shift + assignment enforcement
// =====================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/RiskEngine.php';
require_once __DIR__ . '/../includes/ContextHandler.php';
require_once __DIR__ . '/../includes/AuditLogger.php';

class PolicyDecisionPoint {
    private $db;
    private $riskEngine;
    private $contextHandler;
    private $auditLogger;

    public function __construct() {
        $this->db             = Database::getInstance();
        $this->riskEngine     = new RiskEngine();
        $this->contextHandler = new ContextHandler();
        $this->auditLogger    = new AuditLogger();
    }

    /**
     * Main authorization evaluation
     */
    public function evaluate($userId, $permission, $patientId = null) {
        $user = $this->db->fetchOne(
            "SELECT u.*, r.role_name, r.display_name AS role_display, r.can_emergency_override
             FROM users u JOIN roles r ON u.role_id = r.id
             WHERE u.id = ? AND u.is_active = 1",
            [$userId]
        );

        if (!$user) return $this->deny('User not found or inactive.');

        // 1. Check RBAC permission
        if (!$this->checkRBACPermission($user['role_id'], $permission)) {
            return $this->deny("Your role ({$user['role_name']}) does not have the '{$permission}' permission.");
        }

        // 2. Gather context
        $context = $this->contextHandler->gatherContext($userId);

        // 3. Check patient assignment
        if ($patientId) {
            $context['is_assigned'] = $this->contextHandler->checkPatientAssignment($userId, $patientId);
        }

        // 4. Check active emergency override (patient emergency claimed by this doctor)
        $emergency = $this->checkActiveEmergencyOverride($userId, $patientId);

        // 5. Calculate risk score
        $risk = $this->riskEngine->calculate($context, $user['role_id'], $patientId);

        // 6. Store access request
        $requestId = $this->storeAccessRequest($userId, $patientId, $permission, $context, $risk);

        // =====================================================
        // ADMIN — always granted
        // =====================================================
        if ($user['role_name'] === 'admin') {
            $this->auditLogger->log($userId, 'ACCESS_GRANTED', 'ehr', $patientId, 'granted',
                $risk['score'], $risk['level'], $context['ip_address'], 'Admin full access');
            return $this->grant('Admin access granted. Full system access.', false);
        }

        // =====================================================
        // CONSULTANT — always granted (senior medical staff)
        // =====================================================
        if ($user['role_name'] === 'consultant') {
            $this->auditLogger->log($userId, 'ACCESS_GRANTED', 'ehr', $patientId, 'granted',
                $risk['score'], $risk['level'], $context['ip_address'], 'Consultant full access');
            return $this->grant('Consultant access granted. All activity is fully logged.', false);
        }

        // =====================================================
        // EMERGENCY OVERRIDE — breaks all rules for specific patient
        // =====================================================
        if ($emergency) {
            $this->auditLogger->log($userId, 'EMERGENCY_ACCESS', 'ehr', $patientId, 'granted',
                $risk['score'], $risk['level'], $context['ip_address'], 'Emergency override active');
            return $this->grant(
                "Emergency override active. Access granted for this patient — fully logged and monitored.",
                true, 'conditional'
            );
        }

        // =====================================================
        // THREE CORE RULES FOR ALL OTHER STAFF
        // Rule 1: Must be inside hospital (GPS within 150m)
        // Rule 2: Must be on shift
        // Rule 3: Must be assigned to this patient
        // =====================================================

        // --- RULE 1: Shift CHECK ---
        if (!$context['is_on_shift']) {
            // Get next shift info
            $nextShift = $context['shift_data']['next_shift'] ?? null;
            $nextMsg   = $nextShift
                ? " Your next shift starts: {$nextShift}."
                : " No upcoming shift scheduled.";

            $this->auditLogger->log($userId, 'ACCESS_DENIED_SHIFT', 'ehr', $patientId, 'denied',
                $risk['score'], $risk['level'], $context['ip_address'], 'Off shift access attempt');

            return $this->deny(
                "Access Denied — You are not currently on duty.{$nextMsg} If this is an emergency, request an override.",
                'shift_denied'
            );
        }

        // --- RULE 2: Location CHECK ---
        
        $withinHospital = $context['is_within_hospital'];
        $geoAvailable   = $context['geo_available'];
        $distance       = $context['distance_from_hospital'];

        if ($geoAvailable && $withinHospital === false) {
            $distanceFormatted = $distance >= 1000
                ? round($distance / 1000, 1) . ' km'
                : $distance . ' metres';

            $this->auditLogger->log($userId, 'ACCESS_DENIED_LOCATION', 'ehr', $patientId, 'denied',
                $risk['score'], $risk['level'], $context['ip_address'],
                "Outside hospital — {$distanceFormatted} away");

            return $this->deny(
                "Access Denied — You are {$distanceFormatted} away from " . HOSPITAL_NAME .
                ". Patient records can only be accessed within " . HOSPITAL_RADIUS_METRES .
                "m of the hospital premises.",
                'location_denied'
            );
        }

        // --- RULE 3: ASSIGNMENT CHECK ---
        if ($patientId && isset($context['is_assigned']) && !$context['is_assigned']) {
            $this->auditLogger->log($userId, 'ACCESS_DENIED_UNASSIGNED', 'ehr', $patientId, 'denied',
                $risk['score'], $risk['level'], $context['ip_address'], 'Unassigned patient access attempt');

            return $this->deny(
                "Access Denied — This patient is not assigned to you. Contact the administrator to be assigned, or request an emergency override if this is urgent.",
                'unassigned_denied'
            );
        }

        // =====================================================
        // ALL THREE RULES PASSED — Grant access
        // =====================================================
        $this->auditLogger->log($userId, 'ACCESS_GRANTED', 'ehr', $patientId, 'granted',
            $risk['score'], $risk['level'], $context['ip_address'],
            "Access granted. On shift, inside hospital, assigned patient. Risk: {$risk['level']} ({$risk['score']})");

        $this->db->query(
            "UPDATE access_requests SET decision='granted', decision_reason=?, risk_score=?, risk_level=? WHERE id=?",
            ["All conditions met", $risk['score'], $risk['level'], $requestId]
        );

        return $this->grant(
            "Access granted. Risk: {$risk['level']} ({$risk['score']}) — On shift, inside hospital, assigned patient.",
            false,
            $risk['level'] === 'Medium' ? 'conditional' : 'granted'
        );
    }

    /**
     * Check RBAC permission
     */
    public function checkRBACPermission($roleId, $permissionName) {
        $result = $this->db->fetchOne(
            "SELECT rp.id FROM role_permissions rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.permission_name = ?",
            [$roleId, $permissionName]
        );
        return $result !== false;
    }

    /**
     * Check for active emergency override
     * Triggered when doctor claims a patient emergency
     * Access lasts 2 hours after emergency resolved
     */
    private function checkActiveEmergencyOverride($userId, $patientId) {
        if (!$patientId) return null;

        // Check if doctor has claimed an emergency for this specific patient
        // and it is still active OR resolved within last 2 hours
        return $this->db->fetchOne(
            "SELECT pe.* FROM patient_emergencies pe
             WHERE pe.claimed_by = ?
             AND pe.patient_id = ?
             AND (
                 pe.status IN ('active', 'claimed')
                 OR (pe.status = 'resolved' AND pe.resolved_at > DATE_SUB(NOW(), INTERVAL 2 HOUR))
             )
             ORDER BY pe.created_at DESC LIMIT 1",
            [$userId, $patientId]
        );
    }

    /**
     * Store access request record
     */
    private function storeAccessRequest($userId, $patientId, $permission, $context, $risk) {
        return $this->db->insert(
            "INSERT INTO access_requests
             (user_id, patient_id, permission_requested, risk_score, risk_level,
              context_data, ip_address, device_fingerprint, is_on_shift, is_trusted_network, decision)
             VALUES (?,?,?,?,?,?,?,?,?,?,'pending')",
            [
                $userId, $patientId, $permission,
                $risk['score'], $risk['level'],
                json_encode($context),
                $context['ip_address'],
                $context['device_fingerprint'],
                $context['is_on_shift'] ? 1 : 0,
                $context['is_trusted_network'] ? 1 : 0,
            ]
        );
    }

    private function grant($reason, $emergency = false, $type = 'granted') {
        return [
            'allowed'   => true,
            'decision'  => $type,
            'reason'    => $reason,
            'emergency' => $emergency,
            'risk'      => ['score' => 0, 'level' => 'Low', 'factors' => []],
        ];
    }

    private function deny($reason, $type = 'denied') {
        return [
            'allowed'   => false,
            'decision'  => $type,
            'reason'    => $reason,
            'emergency' => false,
            'risk'      => ['score' => 0, 'level' => 'Low', 'factors' => []],
        ];
    }
}
