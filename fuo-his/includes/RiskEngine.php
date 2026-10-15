<?php
// =====================================================
// CA-RBAC HIS — Risk Evaluation Engine
// =====================================================
require_once __DIR__ . '/../config/database.php';

class RiskEngine {
    private $db;

    // Risk factor weights
    const FACTORS = [
        'after_hours'           => +25,
        'unknown_device'        => +30,
        'remote_access'         => +20,
        'unassigned_patient'    => +35,
        'outside_premises'      => +40,
        'off_duty'              => +35,
        'critical_emergency'    => -30,
        'consultant_role'       => -20,
        'trusted_network'       => -15,
        'trusted_device'        => -10,
        'on_shift'              => -10,
        'assigned_patient'      => -15,
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Calculate risk score based on context
     */
    public function calculate($context, $roleId, $patientId = null) {
        $score = 0;
        $appliedFactors = [];

        // --- Positive risk factors (increase risk) ---

        if ($context['is_after_hours']) {
            $score += self::FACTORS['after_hours'];
            $appliedFactors[] = ['factor' => 'After-hours access', 'weight' => self::FACTORS['after_hours']];
        }

        if (!$context['device_trusted']) {
            $score += self::FACTORS['unknown_device'];
            $appliedFactors[] = ['factor' => 'Unknown/untrusted device', 'weight' => self::FACTORS['unknown_device']];
        }

        if ($context['location_type'] === 'external') {
            $score += self::FACTORS['outside_premises'];
            $appliedFactors[] = ['factor' => 'Outside hospital premises', 'weight' => self::FACTORS['outside_premises']];
        } elseif ($context['location_type'] === 'vpn') {
            $score += self::FACTORS['remote_access'];
            $appliedFactors[] = ['factor' => 'Remote/VPN access', 'weight' => self::FACTORS['remote_access']];
        }

        if (!$context['is_on_shift']) {
            $score += self::FACTORS['off_duty'];
            $appliedFactors[] = ['factor' => 'Off-duty access attempt', 'weight' => self::FACTORS['off_duty']];
        }

        if ($patientId && isset($context['is_assigned']) && !$context['is_assigned']) {
            $score += self::FACTORS['unassigned_patient'];
            $appliedFactors[] = ['factor' => 'Patient not assigned to user', 'weight' => self::FACTORS['unassigned_patient']];
        }

        // --- Negative risk factors (reduce risk) ---

        if ($context['is_trusted_network']) {
            $score += self::FACTORS['trusted_network'];
            $appliedFactors[] = ['factor' => 'Trusted hospital network', 'weight' => self::FACTORS['trusted_network']];
        }

        if ($context['device_trusted']) {
            $score += self::FACTORS['trusted_device'];
            $appliedFactors[] = ['factor' => 'Trusted registered device', 'weight' => self::FACTORS['trusted_device']];
        }

        if ($context['is_on_shift']) {
            $score += self::FACTORS['on_shift'];
            $appliedFactors[] = ['factor' => 'Currently on scheduled shift', 'weight' => self::FACTORS['on_shift']];
        }

        if ($patientId && isset($context['is_assigned']) && $context['is_assigned']) {
            $score += self::FACTORS['assigned_patient'];
            $appliedFactors[] = ['factor' => 'Patient assigned to user', 'weight' => self::FACTORS['assigned_patient']];
        }

        // Role-based modifiers
        $role = $this->db->fetchOne("SELECT risk_modifier FROM roles WHERE id = ?", [$roleId]);
        if ($role && $role['risk_modifier'] != 0) {
            $score += $role['risk_modifier'];
            $appliedFactors[] = ['factor' => 'Role modifier', 'weight' => $role['risk_modifier']];
        }

        // Consultant special reduction
        if ($roleId == 7) {
            $score += self::FACTORS['consultant_role'];
            $appliedFactors[] = ['factor' => 'Consultant role privilege', 'weight' => self::FACTORS['consultant_role']];
        }

        // Check if patient is in emergency
        if ($patientId) {
            $patient = $this->db->fetchOne("SELECT is_emergency FROM patients WHERE id = ?", [$patientId]);
            if ($patient && $patient['is_emergency']) {
                $score += self::FACTORS['critical_emergency'];
                $appliedFactors[] = ['factor' => 'Critical patient emergency', 'weight' => self::FACTORS['critical_emergency']];
            }
        }

        // Ensure score doesn't go below 0
        $score = max(0, $score);

        $riskLevel = $this->getRiskLevel($score);

        return [
            'score'    => $score,
            'level'    => $riskLevel,
            'factors'  => $appliedFactors,
            'decision' => $this->getDecision($score, $riskLevel),
        ];
    }

    public function getRiskLevel($score) {
        if ($score <= RISK_LOW_MAX)    return 'Low';
        if ($score <= RISK_MEDIUM_MAX) return 'Medium';
        if ($score <= RISK_HIGH_MAX)   return 'High';
        return 'Critical';
    }

    public function getDecision($score, $level) {
        switch ($level) {
            case 'Low':      return 'grant';
            case 'Medium':   return 'conditional';
            case 'High':     return 'override_required';
            case 'Critical': return 'deny';
            default:         return 'deny';
        }
    }

    public function getRiskBadgeClass($level) {
        return [
            'Low'      => 'success',
            'Medium'   => 'warning',
            'High'     => 'danger',
            'Critical' => 'dark',
        ][$level] ?? 'secondary';
    }

    /**
     * Store risk score in DB
     */
    public function storeRiskScore($userId, $score, $level, $factors, $requestId = null) {
        $this->db->query(
            "INSERT INTO risk_scores (user_id, access_request_id, final_score, risk_level, factors) VALUES (?,?,?,?,?)",
            [$userId, $requestId, $score, $level, json_encode($factors)]
        );
        return $this->db->lastInsertId();
    }
}
