<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/Patient.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$queue_model = new Queue($db);
$patient_model = new Patient($db);

$user_id = $_SESSION['user_id'];
$status = $queue_model->getPatientStatus($user_id);
$position = $status ? $queue_model->getPatientPosition($user_id) : 0;
$wait_time = $status ? $queue_model->getEstimatedWaitingTime($user_id) : 0;

// Get recent history
$history_query = "SELECT p.current_conditions, pr.medication, p.created_at 
                  FROM patients p 
                  LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
                  WHERE p.user_id = :user_id 
                  ORDER BY p.created_at DESC 
                  LIMIT 5";
$stmt = $db->prepare($history_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total visits
$visits_query = "SELECT COUNT(*) as total FROM patients WHERE user_id = :user_id";
$stmt = $db->prepare($visits_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate actual average wait time from completed appointments
$avg_wait_query = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_wait 
                   FROM patients 
                   WHERE user_id = :user_id AND status = 'completed'";
$stmt = $db->prepare($avg_wait_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$avg_wait_result = $stmt->fetch(PDO::FETCH_ASSOC);
$actual_avg_wait = round($avg_wait_result['avg_wait'] ?? 0);

// ✅ متغيرات اللغة
$is_arabic = ($lang === 'ar');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('patient_portal'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar-patient { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
        .stat-card { border-radius: 15px; border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .status-waiting { background: #fff3cd; border-left: 5px solid #ffc107; }
        .status-consultation { background: #d1ecf1; border-left: 5px solid #0dcaf0; }
        .btn-book { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); border: none; }
        
        .ai-guidance-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
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
            font-size: 1.1rem;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
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
    <nav class="navbar navbar-patient navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-person-heart me-2"></i><?php echo __('patient_portal'); ?> - <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div>
                <?php if ($lang === 'en'): ?>
                    <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                    <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="../../controllers/auth_handler.php?action=logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> <?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold text-danger">
                    <i class="bi bi-hand-waving me-2"></i><?php echo sprintf(__('welcome_back_name'), htmlspecialchars($_SESSION['full_name'])); ?>
                </h2>
            </div>
        </div>

        <!-- بطاقات الإحصائيات -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card text-center p-3">
                    <i class="bi bi-calendar-check display-4 text-danger mb-2"></i>
                    <h5 class="text-muted"><?php echo __('total_visits'); ?></h5>
                    <h2 class="fw-bold text-danger"><?php echo $total_visits; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card text-center p-3">
                    <i class="bi bi-clock display-4 text-warning mb-2"></i>
                    <h5 class="text-muted"><?php echo __('avg_wait_time'); ?></h5>
                    <h2 class="fw-bold text-warning">
                        <?php if ($actual_avg_wait > 0): ?>
                            ~<?php echo $actual_avg_wait; ?> <?php echo __('min'); ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card text-center p-3">
                    <i class="bi bi-activity display-4 text-info mb-2"></i>
                    <h5 class="text-muted"><?php echo __('current_appointment_status'); ?></h5>
                    <h2 class="fw-bold text-info">
                        <?php 
                        if ($status) {
                            echo __($status['status']);
                        } else {
                            echo __('no_active_appointment');
                        }
                        ?>
                    </h2>
                </div>
            </div>
        </div>

        <?php if ($status): ?>
        <!-- ✅ حالة الموعد الحالي مع التوجيه الذكي -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card <?php echo $status['status'] === 'waiting' ? 'status-waiting' : 'status-consultation'; ?> p-4">
                    <h4 class="fw-bold mb-3">
                        <i class="bi bi-info-circle me-2"></i><?php echo __('current_appointment_status'); ?>
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p>
                                <strong><?php echo __('department'); ?>:</strong> 
                                <span class="badge bg-primary fs-6">
                                    <?php echo htmlspecialchars($status['suggested_department'] ?? 'General Practice'); ?>
                                </span>
                            </p>
                            <p>
                                <strong><?php echo __('priority'); ?>:</strong> 
                                <span class="priority-badge priority-<?php echo $status['priority']; ?>">
                                    <?php echo $status['priority']; ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong><?php echo __('patients_ahead_of_you'); ?>:</strong> 
                                <span class="badge bg-info fs-6"><?php echo $position; ?></span>
                            </p>
                            <p>
                                <strong><?php echo __('estimated_wait'); ?>:</strong> 
                                <span class="badge bg-success fs-6">~<?php echo $wait_time; ?> <?php echo __('min'); ?></span>
                            </p>
                        </div>
                    </div>

                    <!-- ✅ صندوق التوجيه الذكي - يظهر فقط إذا تم توجيه المريض -->
                    <?php if (!empty($status['suggested_department']) && $status['suggested_department'] !== 'General Practice'): ?>
                    <div class="ai-guidance-box">
                        <h5>
                            <i class="bi bi-robot me-2"></i>🤖 <?php echo $is_arabic ? 'التوجيه الذكي بالذكاء الاصطناعي' : 'AI Smart Guidance'; ?>
                        </h5>
                        
                        <div class="guidance-item">
                            <i class="bi bi-hospital"></i>
                            <strong><?php echo $is_arabic ? 'القسم الموصى به:' : 'Recommended Department:'; ?></strong>
                            <span class="fs-5"><?php echo htmlspecialchars($status['suggested_department']); ?></span>
                        </div>

                        <?php if (!empty($status['suggested_doctor']) && $status['suggested_doctor'] !== 'Pending Assignment'): ?>
                        <div class="guidance-item">
                            <i class="bi bi-person-badge"></i>
                            <strong><?php echo $is_arabic ? 'الطبيب المختص:' : 'Specialist Doctor:'; ?></strong>
                            <span class="fs-5">Dr. <?php echo htmlspecialchars($status['suggested_doctor']); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="guidance-item">
                            <i class="bi bi-info-circle"></i>
                            <small>
                                <?php echo $is_arabic 
                                    ? 'بناءً على أعراضك، قام الذكاء الاصطناعي بتوجيهك إلى القسم والطبيب الأنسب لحالتك. سيتم خدمتك حسب أولويتك الطبية.' 
                                    : 'Based on your symptoms, AI has directed you to the most appropriate department and doctor for your condition. You will be served according to your medical priority.'; ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="alert alert-light mt-3 mb-0">
                        <i class="bi bi-bell me-2"></i><?php echo __('notified_when_turn'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- لا يوجد موعد نشط -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <div class="card p-5">
                    <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
                    <h4 class="text-muted"><?php echo __('no_active_appointment'); ?></h4>
                    <p class="text-muted"><?php echo __('book_new_appointment_to_see_doctor'); ?></p>
                    <a href="details.php" class="btn btn-book btn-lg text-white">
                        <i class="bi bi-plus-circle me-2"></i><?php echo __('book_new_appointment'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- السجل الطبي الأخير -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i><?php echo __('recent_medical_history'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history)): ?>
                            <p class="text-muted text-center"><?php echo __('no_history_yet'); ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('date'); ?></th>
                                            <th><?php echo __('condition_label'); ?></th>
                                            <th><?php echo __('prescribed_label'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $record): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($record['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars(substr($record['current_conditions'], 0, 50)); ?>...</td>
                                            <td><?php echo htmlspecialchars($record['medication'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- الإجراءات السريعة -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-lightning me-2"></i><?php echo __('quick_actions'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="details.php" class="btn btn-outline-danger w-100 py-3">
                                    <i class="bi bi-plus-circle me-2"></i><?php echo __('book_new_appointment'); ?>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="waiting.php" class="btn btn-outline-warning w-100 py-3">
                                    <i class="bi bi-hourglass me-2"></i><?php echo __('view_waiting_room'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>