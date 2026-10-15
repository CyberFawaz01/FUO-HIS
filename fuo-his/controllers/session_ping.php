<?php
// Session ping to extend session
session_start();
if (!empty($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(401);
    echo json_encode(['status' => 'expired']);
}
