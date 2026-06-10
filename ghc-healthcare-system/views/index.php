<?php
session_start();
require_once __DIR__ . '/../includes/language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('healthcare_system'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .portal-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .portal-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .patient-icon { color: #e74c3c; }
        .doctor-icon { color: #3498db; }
        .pharmacist-icon { color: #27ae60; }
        .btn-portal {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 15px;
        }
        .header-title {
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .lang-switch {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .feature-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 5px;
            background: rgba(255,255,255,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="lang-switch">
        <?php if ($lang === 'en'): ?>
            <a href="../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm">عربي</a>
        <?php else: ?>
            <a href="../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm">English</a>
        <?php endif; ?>
    </div>

    <div class="container py-5">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h1 class="display-3 fw-bold header-title mb-3">
                    <i class="bi bi-hospital-fill me-3"></i><?php echo __('healthcare_system'); ?>
                </h1>
                <p class="lead text-white mb-4"><?php echo __('ai_powered_platform'); ?></p>
                <div class="mb-4">
                    <span class="feature-badge"><i class="bi bi-robot me-1"></i> <?php echo __('ai_powered'); ?></span>
                    <span class="feature-badge"><i class="bi bi-shield-check me-1"></i> <?php echo __('secure'); ?></span>
                    <span class="feature-badge"><i class="bi bi-lightning me-1"></i> <?php echo __('fast'); ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <!-- Patient Portal -->
            <div class="col-md-4">
                <div class="portal-card h-100">
                    <div class="portal-icon patient-icon">
                        <i class="bi bi-person-heart"></i>
                    </div>
                    <h3 class="fw-bold mb-3"><?php echo __('patient'); ?></h3>
                    <p class="text-muted mb-4"><?php echo __('patient_desc'); ?></p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="bi bi-stethoscope me-1"></i> <?php echo __('ai_diagnosis'); ?><br>
                            <i class="bi bi-sort-numeric-up me-1"></i> <?php echo __('priority_queue'); ?>
                        </small>
                    </div>
                    <a href="login.php?type=patient" class="btn btn-danger btn-portal">
                        <i class="bi bi-box-arrow-in-right me-2"></i><?php echo __('login'); ?>
                    </a>
                </div>
            </div>

            <!-- Doctor Portal -->
            <div class="col-md-4">
                <div class="portal-card h-100">
                    <div class="portal-icon doctor-icon">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-3"><?php echo __('doctor'); ?></h3>
                    <p class="text-muted mb-4"><?php echo __('doctor_desc'); ?></p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="bi bi-people me-1"></i> <?php echo __('smart_queue'); ?><br>
                            <i class="bi bi-robot me-1"></i> <?php echo __('ai_assistant'); ?>
                        </small>
                    </div>
                    <a href="login.php?type=doctor" class="btn btn-primary btn-portal">
                        <i class="bi bi-box-arrow-in-right me-2"></i><?php echo __('login'); ?>
                    </a>
                </div>
            </div>

            <!-- Pharmacist Portal -->
            <div class="col-md-4">
                <div class="portal-card h-100">
                    <div class="portal-icon pharmacist-icon">
                        <i class="bi bi-capsule-pill"></i>
                    </div>
                    <h3 class="fw-bold mb-3"><?php echo __('pharmacist'); ?></h3>
                    <p class="text-muted mb-4"><?php echo __('pharmacist_desc'); ?></p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="bi bi-credit-card me-1"></i> <?php echo __('e_payment'); ?><br>
                            <i class="bi bi-box-seam me-1"></i> <?php echo __('inventory'); ?>
                        </small>
                    </div>
                    <a href="login.php?type=pharmacist" class="btn btn-success btn-portal">
                        <i class="bi bi-box-arrow-in-right me-2"></i><?php echo __('login'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 text-center">
                <p class="text-white-50">
                    <i class="bi bi-code-slash me-2"></i>GHC - Graduation Research Healthcare System
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
