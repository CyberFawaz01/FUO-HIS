<?php
// =====================================================
// CA-RBAC HIS — Context Handler (PIP)
// Policy Information Point
// =====================================================
require_once __DIR__ . '/../config/database.php';

class ContextHandler {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Gather all contextual attributes for the current request
     */
    public function gatherContext($userId) {
        $ip          = $this->getClientIP();
        $networkInfo = $this->evaluateNetwork($ip);
        $shiftInfo   = $this->checkShiftStatus($userId);
        $fingerprint = $this->generateDeviceFingerprint();
        $deviceInfo  = $this->evaluateDevice($userId, $fingerprint);
        $currentHour = (int)date('H');
        $isAfterHours = ($currentHour < 7 || $currentHour >= 22);

        // Geolocation from session (set by JS at login)
        $geoLat  = $_SESSION['geo_lat']  ?? null;
        $geoLng  = $_SESSION['geo_lng']  ?? null;
        $geoAddr = $_SESSION['geo_addr'] ?? null;

        // Calculate distance from hospital
        $distanceInfo = $this->calculateDistanceFromHospital($geoLat, $geoLng);

        // Save geo to user record if available
        if ($geoLat && $geoLng) {
            $this->db->query(
                "UPDATE users SET geolocation_lat=?, geolocation_lng=?, geolocation_address=? WHERE id=?",
                [$geoLat, $geoLng, $geoAddr, $userId]
            );
        }

        return [
            'ip_address'           => $ip,
            'user_agent'           => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'device_fingerprint'   => $fingerprint,
            'device_trusted'       => $deviceInfo['is_trusted'],
            'device_type'          => $deviceInfo['device_type'],
            'is_trusted_network'   => $networkInfo['is_trusted'],
            'location_type'        => $networkInfo['location_type'],
            'network_name'         => $networkInfo['name'],
            'is_on_shift'          => $shiftInfo['on_shift'],
            'shift_data'           => $shiftInfo,
            'is_after_hours'       => $isAfterHours,
            'current_time'         => date('Y-m-d H:i:s'),
            'day_of_week'          => date('l'),
            'geolocation_lat'      => $geoLat,
            'geolocation_lng'      => $geoLng,
            'geolocation_address'  => $geoAddr,
            'geo_available'        => !empty($geoLat),
            'distance_from_hospital' => $distanceInfo['distance'],
            'is_within_hospital'   => $distanceInfo['within_radius'],
            'distance_message'     => $distanceInfo['message'],
        ];
    }

    /**
     * Calculate distance from hospital using Haversine formula
     * Returns distance in metres
     */
    public function calculateDistanceFromHospital($lat, $lng) {
        // If no GPS available, treat as unknown — don't block (fallback to network check)
        if (empty($lat) || empty($lng)) {
            return [
                'distance'      => null,
                'within_radius' => null,  // null = unknown
                'message'       => 'Location not available — GPS permission required',
            ];
        }

        $hospitalLat = HOSPITAL_LAT;
        $hospitalLng = HOSPITAL_LNG;
        $radius      = HOSPITAL_RADIUS_METRES;

        // Haversine formula
        $earthRadius = 6371000; // metres
        $latDiff = deg2rad($lat - $hospitalLat);
        $lngDiff = deg2rad($lng - $hospitalLng);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($hospitalLat)) * cos(deg2rad($lat)) *
             sin($lngDiff / 2) * sin($lngDiff / 2);

        $c        = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c; // distance in metres

        $withinRadius = $distance <= $radius;

        if ($withinRadius) {
            $message = "You are " . round($distance) . "m from the hospital — within permitted zone ✓";
        } else {
            $distanceKm = $distance >= 1000
                ? round($distance / 1000, 1) . " km"
                : round($distance) . " metres";
            $message = "You are {$distanceKm} away from the hospital. Access requires presence within " . $radius . "m.";
        }

        return [
            'distance'      => round($distance),
            'within_radius' => $withinRadius,
            'message'       => $message,
        ];
    }

    /**
     * Get real client IP
     */
    public function getClientIP() {
        $keys = ['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '127.0.0.1';
    }

    /**
     * Evaluate network trust
     */
    public function evaluateNetwork($ip) {
        // localhost = trusted for development
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return ['is_trusted' => true, 'location_type' => 'internal', 'name' => 'Localhost (Hospital Network)'];
        }

        $trustedPrefixes = [
            '192.168.' => ['internal', 'Hospital Internal Network'],
            '10.0.'    => ['vpn',      'Hospital VPN'],
            '172.16.'  => ['vpn',      'Private Network'],
            '10.'      => ['vpn',      'Hospital VPN'],
        ];

        foreach ($trustedPrefixes as $prefix => $info) {
            if (strpos($ip, $prefix) === 0) {
                return ['is_trusted' => true, 'location_type' => $info[0], 'name' => $info[1]];
            }
        }

        // DB check
        $networks = $this->db->fetchAll("SELECT * FROM trusted_networks");
        foreach ($networks as $net) {
            if (strpos($ip, $net['ip_prefix']) === 0) {
                return ['is_trusted' => true, 'location_type' => 'internal', 'name' => $net['network_name']];
            }
        }

        return ['is_trusted' => false, 'location_type' => 'external', 'name' => 'External Network (' . $ip . ')'];
    }

    /**
     * Check shift status
     */
    public function checkShiftStatus($userId) {
        $today       = date('Y-m-d');
        $currentTime = date('H:i:s');

        $shift = $this->db->fetchOne(
            "SELECT s.*, d.dept_name FROM staff_shifts s
             LEFT JOIN departments d ON s.department_id = d.id
             WHERE s.user_id = ? AND s.shift_date = ? AND s.is_active = 1
             AND ((s.start_time <= s.end_time AND ? >= s.start_time AND ? <= s.end_time)
                  OR (s.start_time > s.end_time AND (? >= s.start_time OR ? <= s.end_time)))
             LIMIT 1",
            [$userId, $today, $currentTime, $currentTime, $currentTime, $currentTime]
        );

        if ($shift) {
            return [
                'on_shift'   => true,
                'shift_name' => $shift['shift_name'],
                'start_time' => $shift['start_time'],
                'end_time'   => $shift['end_time'],
                'department' => $shift['dept_name'],
            ];
        }

        $next = $this->db->fetchOne(
            "SELECT * FROM staff_shifts WHERE user_id=? AND shift_date>=CURDATE() AND is_active=1 ORDER BY shift_date, start_time LIMIT 1",
            [$userId]
        );

        return [
            'on_shift'   => false,
            'next_shift' => $next ? $next['shift_date'] . ' ' . $next['start_time'] : null,
        ];
    }

    /**
     * Evaluate device trust
     */
    public function evaluateDevice($userId, $fingerprint) {
        $device = $this->db->fetchOne(
            "SELECT * FROM device_registry WHERE user_id = ? AND device_fingerprint = ?",
            [$userId, $fingerprint]
        );

        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $type = 'desktop';
        if (preg_match('/mobile/i', $ua))       $type = 'mobile';
        elseif (preg_match('/tablet|ipad/i', $ua)) $type = 'tablet';

        if ($device) {
            $trusted = $device['is_trusted'] &&
                       ($device['trust_expires'] === null || strtotime($device['trust_expires']) > time());
            return ['is_trusted' => $trusted, 'device_type' => $device['device_type'] ?? $type, 'known' => true];
        }

        return ['is_trusted' => false, 'device_type' => $type, 'known' => false];
    }

    /**
     * Generate device fingerprint
     */
    public function generateDeviceFingerprint() {
        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $lang   = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $ip     = $this->getClientIP();
        return hash('sha256', $ua . $accept . $lang . substr($ip, 0, strrpos($ip, '.')));
    }

    /**
     * Check patient assignment
     */
    public function checkPatientAssignment($userId, $patientId) {
        $result = $this->db->fetchOne(
            "SELECT id FROM assignments WHERE user_id = ? AND patient_id = ? AND is_active = 1",
            [$userId, $patientId]
        );
        return $result !== false;
    }

    /**
     * Log context snapshot
     */
    public function logContext($userId, $context) {
        $this->db->query(
            "INSERT INTO context_logs (user_id, ip_address, location_type, device_type, is_trusted_network, is_on_shift, is_after_hours, shift_info, risk_factors)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $context['ip_address'],
                $context['location_type'],
                $context['device_type'],
                $context['is_trusted_network'] ? 1 : 0,
                $context['is_on_shift'] ? 1 : 0,
                $context['is_after_hours'] ? 1 : 0,
                json_encode($context['shift_data']),
                json_encode([])
            ]
        );
    }
}
