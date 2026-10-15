<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController();
$pageTitle = 'Unauthorized — CA-RBAC HIS';
if ($auth->isLoggedIn()) include __DIR__ . '/../../includes/header.php';
?>
<?php if (!$auth->isLoggedIn()): ?>
<!DOCTYPE html><html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unauthorized</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body class="bg-light">
<?php endif; ?>
<div class="d-flex flex-column align-items-center justify-content-center" style="min-height:60vh">
  <div class="text-center">
    <i class="bi bi-shield-x" style="font-size:5rem;color:#dc3545"></i>
    <h2 class="mt-3 text-danger">Access Denied</h2>
    <p class="text-muted">You do not have permission to access this resource.</p>
    <p class="small text-muted">Your role: <strong><?= htmlspecialchars($_SESSION['role_display'] ?? 'Unknown') ?></strong></p>
    <div class="d-flex gap-2 justify-content-center mt-4">
      <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Go Back</a>
      <?php if ($auth->isLoggedIn()): ?>
      <a href="<?= APP_URL ?>/index.php" class="btn btn-primary"><i class="bi bi-house me-1"></i>Dashboard</a>
      <?php else: ?>
      <a href="<?= APP_URL ?>/index.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if ($auth->isLoggedIn()): include __DIR__ . '/../../includes/footer.php'; else: ?>
</body></html>
<?php endif; ?>
