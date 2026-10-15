<?php
// =====================================================
// CA-RBAC HIS — Add EHR Record Controller
// =====================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../includes/PolicyDecisionPoint.php';
require_once __DIR__ . '/../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireLogin();
$pdp   = new PolicyDecisionPoint();
$db    = Database::getInstance();
$audit = new AuditLogger();

$userId    = $_SESSION['user_id'];
$roleId    = $_SESSION['role_id'];
$roleName  = $_SESSION['role_name'];
$patientId = (int)($_POST['patient_id'] ?? 0);
$recType   = $_POST['record_type']       ?? '';
$title     = trim($_POST['title']        ?? '');
$content   = trim($_POST['content']      ?? '');
$sens      = $_POST['sensitivity_level'] ?? 'Low';

if (!$patientId || !$recType || !$title || !$content) {
    $_SESSION['flash_error'] = 'All fields are required.';
    header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
    exit;
}

// Policy check
$decision = $pdp->evaluate($userId, 'write_ehr', $patientId);
if (!$decision['allowed']) {
    $_SESSION['flash_error'] = 'Access denied: ' . $decision['reason'];
    header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
    exit;
}

// Valid record types — lab_request added for doctors
$validTypes = [
    'diagnosis','medication','allergy','treatment',
    'lab_report','lab_request','surgery_note','consultation','vitals'
];

if (!in_array($recType, $validTypes)) {
    $_SESSION['flash_error'] = 'Invalid record type.';
    header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
    exit;
}

// Only lab_tech can create lab_report directly
if ($recType === 'lab_report' && $roleName !== 'lab_tech' && $roleName !== 'admin' && $roleName !== 'consultant') {
    $_SESSION['flash_error'] = 'Only lab technicians can upload lab reports. Use Lab Request instead.';
    header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
    exit;
}

// Only doctors/surgeons/consultants can create lab_request
if ($recType === 'lab_request' && !in_array($roleName, ['doctor','surgeon','consultant','admin'])) {
    $_SESSION['flash_error'] = 'Only doctors can issue lab test requests.';
    header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
    exit;
}

$db->query(
    "INSERT INTO patient_records (patient_id, record_type, title, content, created_by, sensitivity_level)
     VALUES (?, ?, ?, ?, ?, ?)",
    [$patientId, $recType, $title, $content, $userId, $sens]
);

$audit->log($userId, 'EHR_RECORD_ADDED', 'ehr', $patientId, 'granted', null, null, null,
    "Added $recType record: $title");

$_SESSION['flash_success'] = "Record '$title' added successfully.";
header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
exit;
