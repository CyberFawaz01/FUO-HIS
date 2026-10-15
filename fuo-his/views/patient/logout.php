<?php
session_start();
unset($_SESSION['patient_id'], $_SESSION['patient_name'], $_SESSION['patient_pid']);
session_destroy();
require_once __DIR__ . '/../../config/database.php';
header('Location: ' . APP_URL . '/index.php');
exit;
