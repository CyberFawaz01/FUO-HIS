<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
$auth = new AuthController();
$auth->logout();
header('Location: ' . APP_URL . '/index.php');
exit;
