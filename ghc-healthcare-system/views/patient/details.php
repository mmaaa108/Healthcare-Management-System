<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../includes/language.php';

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('medical_details'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar-patient { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
        .form-card { border-radius: 20px; border: none; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
        .btn-submit { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); border: none; }
        .priority-info { background: #e8f5e9; border-radius: 15px; padding: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-patient navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-clipboard2-pulse me-2"></i><?php echo __('medical_details'); ?>
            </span>
            <div>
                <?php if ($lang === 'en'): ?>
                    <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                    <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i><?php echo __('back_to_dashboard'); ?>
                </a>
                <a href="../../controllers/auth_handler.php?action=logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i><?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card form-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-heart-pulse display-1 text-danger mb-3"></i>
                            <h3 class="fw-bold"><?php echo __('tell_us_health'); ?></h3>
                        </div>

                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form action="../../controllers/patient_handler.php" method="POST">
                            <input type="hidden" name="action" value="create_appointment">

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-chat-square-text me-2"></i><?php echo __('current_symptoms_conditions'); ?>
                                </label>
                                <textarea class="form-control" name="current_conditions" rows="6" required
                                          placeholder="<?php echo __('symptoms_placeholder'); ?>"></textarea>
                                <div class="form-text text-muted">
                                    <i class="bi bi-info-circle me-1"></i><?php echo __('symptoms_hint'); ?>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_chronic" id="is_chronic">
                                        <label class="form-check-label fw-bold" for="is_chronic">
                                            <i class="bi bi-shield-check me-2 text-warning"></i><?php echo __('i_have_chronic_diseases'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_pregnant" id="is_pregnant">
                                        <label class="form-check-label fw-bold" for="is_pregnant">
                                            <i class="bi bi-heart me-2 text-danger"></i><?php echo __('i_am_pregnant'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-file-medical me-2"></i><?php echo __('other_diseases_if_any'); ?>
                                </label>
                                <textarea class="form-control" name="other_diseases" rows="3"
                                          placeholder="<?php echo __('other_diseases_placeholder'); ?>"></textarea>
                                <div class="form-text"><?php echo __('other_diseases_example'); ?></div>
                            </div>

                            <div class="priority-info mb-4">
                                <h6 class="fw-bold text-success mb-3">
                                    <i class="bi bi-robot me-2"></i><?php echo __('ai_assistant_statement'); ?>
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo __('ai_recommend_department'); ?></li>
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo __('ai_assign_doctor'); ?></li>
                                    <li><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo __('ai_calculate_priority'); ?></li>
                                </ul>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-submit btn-lg text-white">
                                    <i class="bi bi-calendar-plus me-2"></i><?php echo __('book_appointment_now'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i><?php echo __('priority_levels'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6"><span class="badge bg-danger">1</span> <?php echo __('priority_1_critical'); ?></div>
                            <div class="col-md-6"><span class="badge bg-warning text-dark">2</span> <?php echo __('priority_2_pregnant'); ?></div>
                            <div class="col-md-6"><span class="badge bg-warning">3</span> <?php echo __('priority_3_elderly_chronic'); ?></div>
                            <div class="col-md-6"><span class="badge bg-info text-dark">4</span> <?php echo __('priority_4_chronic'); ?></div>
                            <div class="col-md-6"><span class="badge bg-primary">5</span> <?php echo __('priority_5_children'); ?></div>
                            <div class="col-md-6"><span class="badge bg-secondary">6</span> <?php echo __('priority_6_regular'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
