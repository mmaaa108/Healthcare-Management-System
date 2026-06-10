<?php
session_start();
require_once __DIR__ . '/../includes/language.php';

$user_type = $_GET['type'] ?? 'patient';
$valid_types = ['patient', 'doctor', 'pharmacist'];

if (!in_array($user_type, $valid_types)) {
    header("Location: index.php");
    exit();
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login'); ?> - <?php echo __($user_type); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }
        .login-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .patient-color { color: #e74c3c; }
        .doctor-color { color: #3498db; }
        .pharmacist-color { color: #27ae60; }
        .btn-login {
            border-radius: 25px;
            padding: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card">
                    <div class="text-center mb-4">
                        <div class="login-icon <?php echo $user_type; ?>-color">
                            <?php if ($user_type === 'patient'): ?>
                                <i class="bi bi-person-heart"></i>
                            <?php elseif ($user_type === 'doctor'): ?>
                                <i class="bi bi-heart-pulse-fill"></i>
                            <?php else: ?>
                                <i class="bi bi-capsule-pill"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="fw-bold"><?php echo __('welcome_back'); ?></h3>
                        <p class="text-muted"><?php echo __('login_as'); ?> <?php echo __($user_type); ?></p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <form action="../controllers/auth_handler.php" method="POST">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="bi bi-card-text me-2"></i><?php echo __('national_id'); ?>
                            </label>
                            <input type="text" class="form-control form-control-lg" name="national_id" 
                                   placeholder="<?php echo __('enter_national_id'); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-lock me-2"></i><?php echo __('password'); ?>
                            </label>
                            <input type="password" class="form-control form-control-lg" name="password" 
                                   placeholder="<?php echo __('enter_password'); ?>" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-<?php echo $user_type === 'patient' ? 'danger' : ($user_type === 'doctor' ? 'primary' : 'success'); ?> btn-login btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i><?php echo __('login'); ?>
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="text-muted mb-2"><?php echo __('dont_have_account'); ?></p>
                        <a href="register.php?type=<?php echo $user_type; ?>" class="btn btn-outline-dark btn-sm">
                            <?php echo __('register_here'); ?>
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <a href="index.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left me-1"></i><?php echo __('back_to_home'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
