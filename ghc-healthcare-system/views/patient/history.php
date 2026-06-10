<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get full medical history
$query = "SELECT p.*, pr.medication, pr.notes as prescription_notes, pr.consultation_duration, 
          pr.created_at as prescription_date, u.full_name as doctor_name
          FROM patients p 
          LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
          LEFT JOIN users u ON pr.doctor_id = u.id
          WHERE p.user_id = :user_id 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('recent_medical_history'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar-patient { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
        .history-card { border-radius: 15px; border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .timeline-dot { width: 15px; height: 15px; background: #ff6b6b; border-radius: 50%; display: inline-block; margin-right: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-patient navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-clock-history me-2"></i><?php echo __('recent_medical_history'); ?>
            </span>
            <div>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i><?php echo __('back_to_dashboard'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h3 class="fw-bold mb-4"><i class="bi bi-journal-medical me-2"></i><?php echo __('recent_medical_history'); ?></h3>

        <?php if (empty($history)): ?>
        <div class="card history-card p-5 text-center">
            <i class="bi bi-inbox display-1 text-muted mb-3"></i>
            <h4 class="text-muted"><?php echo __('no_active_appointment'); ?></h4>
        </div>
        <?php else: ?>
            <?php foreach ($history as $record): ?>
            <div class="card history-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="timeline-dot"></span>
                        <h5 class="mb-0 fw-bold"><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></h5>
                        <span class="badge bg-<?php echo $record['status'] == 'completed' ? 'success' : ($record['status'] == 'waiting' ? 'warning' : 'info'); ?> ms-3">
                            <?php echo __($record['status']); ?>
                        </span>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary"><i class="bi bi-clipboard2-pulse me-2"></i><?php echo __('condition_label'); ?></h6>
                            <p><?php echo nl2br(htmlspecialchars($record['current_conditions'])); ?></p>

                            <?php if ($record['other_diseases']): ?>
                            <h6 class="fw-bold text-danger mt-3"><i class="bi bi-file-medical me-2"></i><?php echo __('other_diseases'); ?></h6>
                            <p><?php echo nl2br(htmlspecialchars($record['other_diseases'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($record['medication']): ?>
                            <h6 class="fw-bold text-success"><i class="bi bi-capsule me-2"></i><?php echo __('prescribed_label'); ?></h6>
                            <div class="alert alert-light border">
                                <?php echo nl2br(htmlspecialchars($record['medication'])); ?>
                            </div>

                            <?php if ($record['prescription_notes']): ?>
                            <small class="text-muted">
                                <i class="bi bi-journal-text me-1"></i><?php echo __('doctors_notes'); ?>: 
                                <?php echo htmlspecialchars($record['prescription_notes']); ?>
                            </small>
                            <?php endif; ?>

                            <?php if ($record['doctor_name']): ?>
                            <p class="mt-2"><i class="bi bi-person-badge me-2"></i><?php echo __('doctor'); ?>: <?php echo htmlspecialchars($record['doctor_name']); ?></p>
                            <?php endif; ?>
                            <?php else: ?>
                            <p class="text-muted"><?php echo __('no_active_appointment'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <span class="badge bg-info"><?php echo __('priority'); ?>: <?php echo $record['priority']; ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($record['suggested_department']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
