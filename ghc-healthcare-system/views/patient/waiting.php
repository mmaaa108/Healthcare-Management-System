<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$queue_model = new Queue($db);

$user_id = $_SESSION['user_id'];
$status = $queue_model->getPatientStatus($user_id);
$position = $status ? $queue_model->getPatientPosition($user_id) : 0;
$wait_time = $status ? $queue_model->getEstimatedWaitingTime($user_id) : 0;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('waiting_room'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .waiting-card { border-radius: 20px; border: none; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
        .queue-number { font-size: 8rem; font-weight: bold; color: #ff6b6b; }
        .ai-guidance-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }
        .ai-guidance-box h5 {
            color: white;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .guidance-item {
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            backdrop-filter: blur(10px);
        }
        .guidance-item i {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .priority-badge {
            font-size: 1.2rem;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
        }
        .priority-1 { background: #dc3545; color: white; }
        .priority-2 { background: #ffc107; color: #333; }
        .priority-3 { background: #fd7e14; color: white; }
        .priority-4 { background: #17a2b8; color: white; }
        .priority-5 { background: #007bff; color: white; }
        .priority-6 { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-patient navbar-dark" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-hourglass me-2"></i><?php echo __('waiting_room'); ?>
            </span>
            <div>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i><?php echo __('back_to_dashboard'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo __('appointment_booked_success'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card waiting-card p-5">
                    <?php if ($status && $status['status'] === 'waiting'): ?>
                    <div class="mb-4">
                        <i class="bi bi-people-fill display-1 text-warning mb-3"></i>
                        <h3 class="fw-bold"><?php echo __('please_wait_turn'); ?></h3>
                    </div>

                    <div class="queue-number mb-3"><?php echo $position + 1; ?></div>
                    <p class="text-muted fs-5"><?php echo __('patients_ahead_of_you'); ?>: <strong><?php echo $position; ?></strong></p>

                    <div class="row justify-content-center mt-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><i class="bi bi-clock me-2"></i><?php echo sprintf(__('estimated_wait'), $wait_time); ?></h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5>
                                        <i class="bi bi-flag me-2"></i><?php echo __('priority'); ?>: 
                                        <span class="priority-badge priority-<?php echo $status['priority']; ?>">
                                            <?php echo $status['priority']; ?>
                                        </span>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ صندوق التوجيه الذكي -->
                    <?php if (!empty($status['suggested_department']) && $status['suggested_department'] !== 'General Practice'): ?>
                    <div class="ai-guidance-box">
                        <h5>
                            <i class="bi bi-robot me-2"></i>🤖 التوجيه الذكي بالذكاء الاصطناعي
                        </h5>
                        
                        <div class="guidance-item">
                            <i class="bi bi-hospital"></i>
                            <strong>القسم الموصى به:</strong>
                            <span class="fs-5"><?php echo __('department'); ?>: <?php echo htmlspecialchars(translateDepartment($status['suggested_department'])); ?></span>
                        </div>

                        <?php if (!empty($status['suggested_doctor']) && $status['suggested_doctor'] !== 'Pending Assignment'): ?>
                        <div class="guidance-item">
                            <i class="bi bi-person-badge"></i>
                            <strong>الطبيب المختص:</strong>
                            <span class="fs-5">Dr. <?php echo htmlspecialchars($status['suggested_doctor']); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="guidance-item">
                            <i class="bi bi-info-circle"></i>
                            <small>
                                بناءً على أعراضك، قام الذكاء الاصطناعي بتوجيهك إلى القسم والطبيب الأنسب لحالتك.
                                سيتم خدمتك حسب أولويتك الطبية.
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2"><?php echo __('notified_when_turn'); ?></p>
                    </div>

                    <?php elseif ($status && $status['status'] === 'in_consultation'): ?>
                    <div class="mb-4">
                        <i class="bi bi-stethoscope display-1 text-info mb-3"></i>
                        <h3 class="fw-bold text-info"><?php echo __('consultation_in_progress'); ?></h3>
                    </div>
                    <p class="fs-5"><?php echo __('doctor_seeing_you_now'); ?></p>
                    
                    <?php if (!empty($status['suggested_doctor']) && $status['suggested_doctor'] !== 'Pending Assignment'): ?>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-person-badge me-2"></i>
                        <strong>الطبيب:</strong> Dr. <?php echo htmlspecialchars($status['suggested_doctor']); ?>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill display-1 text-success mb-3"></i>
                        <h3 class="fw-bold text-success"><?php echo __('appointment_completed'); ?></h3>
                    </div>
                    <a href="dashboard.php" class="btn btn-danger btn-lg">
                        <i class="bi bi-house me-2"></i><?php echo __('back_to_dashboard'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($status && $status['status'] === 'waiting'): ?>
    <script>
        // Auto-refresh every 30 seconds to update queue position
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
    <?php endif; ?>
</body>
</html>