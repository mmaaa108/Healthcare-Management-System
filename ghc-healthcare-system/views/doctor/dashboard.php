<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: ../login.php?type=doctor");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Doctor.php';
require_once __DIR__ . '/../../models/Patient.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/Prescription.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$queue_model = new Queue($db);
$prescription_model = new Prescription($db);
$doctor_model = new Doctor($db);

// جلب معلومات الطبيب الحالي
$doctor_model = new Doctor($db);
$doctor_info = $doctor_model->getDoctorInfo($_SESSION['user_id']);
$doctor_name = $_SESSION['full_name'];
$doctor_specialization = $doctor_info['specialization'] ?? 'General Practice';

// ✅ جلب المرضى الموجهين لهذا الطبيب تحديداً
$queue = $queue_model->getAllWaitingPatientsByDoctor($doctor_name, $doctor_specialization);
$next_patient = !empty($queue) ? $queue[0] : null;

// ✅ جلب المرضى الموجهين لهذا الطبيب تحديداً (بناءً على تخصصه)
$queue = $queue_model->getAllWaitingPatientsByDoctor($doctor_name, $doctor_specialization);
$next_patient = !empty($queue) ? $queue[0] : null;

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_consultation'])) {
    try {
        if (empty($_POST['patient_id'])) {
            header("Location: dashboard.php?error=missing_patient");
            exit();
        }

        $prescription_model->patient_id = intval($_POST['patient_id']);
        $prescription_model->doctor_id = $_SESSION['user_id'];
        $prescription_model->medication = htmlspecialchars($_POST['medication'] ?? '');
        $prescription_model->notes = htmlspecialchars($_POST['notes'] ?? '');
        $prescription_model->consultation_duration = intval($_POST['duration'] ?? 15);

        if ($prescription_model->create()) {
            if ($queue_model->markAsCompleted($_POST['patient_id'])) {
                header("Location: dashboard.php?success=1");
                exit();
            } else {
                header("Location: dashboard.php?warning=prescription_created");
                exit();
            }
        } else {
            header("Location: dashboard.php?error=prescription_failed");
            exit();
        }
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
        header("Location: dashboard.php?error=system_error");
        exit();
    }
}

// ✅ الحصول على اقتراح الذكاء الاصطناعي مع بيانات المريض الكاملة
$ai_suggestion = null;
if ($next_patient) {
    $ai_suggestion = getMedicationSuggestion(
        $next_patient['current_conditions'],
        $next_patient // تمرير كل بيانات المريض للذكاء الاصطناعي
    );
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('doctor_dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f8f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-doctor { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .patient-card { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); border-left: 5px solid #00acc1; }
        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        
        /* ✅ تصميم محسّن لصندوق اقتراح الذكاء الاصطناعي */
        .ai-suggestion { 
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
            border-left: 5px solid #2196f3; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 20px;
            position: relative;
        }
        .ai-suggestion h6 {
            color: #1565c0;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .ai-medication-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 4px solid #4caf50;
        }
        .ai-considerations-box {
            background: #fff8e1;
            border-radius: 8px;
            padding: 12px;
            border-left: 4px solid #ff9800;
            font-size: 0.9rem;
        }
        .btn-copy-ai {
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-copy-ai:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
            color: white;
        }
        .btn-refresh-ai {
            background: #9e9e9e;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-size: 0.85rem;
        }
        .ai-loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .ai-loading .spinner-border {
            width: 2rem;
            height: 2rem;
        }
        
        .btn-complete { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; color: white; padding: 15px 30px; font-size: 1.1rem; border-radius: 10px; }
        .btn-complete:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4); }
        
        .doctor-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-doctor navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-hospital me-2"></i><?php echo __('doctor_dashboard'); ?> - Dr. <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div>
                <span class="doctor-badge d-none d-md-inline-block me-2">
                    <i class="bi bi-award me-1"></i><?php echo htmlspecialchars($doctor_specialization); ?>
                </span>
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

    <div class="container-fluid py-4">
        <!-- رسائل التنبيه -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo __('consultation_completed_sent'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] === 'prescription_failed'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-x-circle me-2"></i>فشل إنشاء الوصفة الطبية. يرجى المحاولة مرة أخرى.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] === 'system_error'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-x-circle me-2"></i>حدث خطأ في النظام. يرجى التواصل مع الإدارة.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['warning']) && $_GET['warning'] === 'prescription_created'): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>تم إنشاء الوصفة لكن لم يتم تحديث حالة المريض.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-people display-4 text-success mb-3"></i>
                        <h5 class="text-muted"><?php echo __('patients_waiting'); ?></h5>
                        <h2 class="display-4 fw-bold text-success"><?php echo count($queue); ?></h2>
                        <small class="text-muted">
                            <i class="bi bi-funnel me-1"></i>
                            موجهون لـ: <?php echo htmlspecialchars($doctor_specialization); ?>
                        </small>
                    </div>
                </div>
                
                <!-- بطاقة معلومات الطبيب -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-person-badge me-2"></i>معلوماتك المهنية
                        </h6>
                        <p class="mb-2"><strong>الاسم:</strong> Dr. <?php echo htmlspecialchars($doctor_name); ?></p>
                        <p class="mb-2"><strong>التخصص:</strong> <?php echo htmlspecialchars($doctor_specialization); ?></p>
                        <p class="mb-0"><strong>سنوات الخبرة:</strong> <?php echo $doctor_info['years_experience'] ?? 0; ?> سنة</p>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <?php if ($next_patient): ?>
                <div class="card patient-card">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-fill me-2"></i><?php echo __('current_patient'); ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">
                                    <i class="bi bi-person-badge me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($next_patient['full_name']); ?>
                                </h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-card-text me-2"></i><?php echo __('national_id'); ?>:</td>
                                        <td><?php echo htmlspecialchars($next_patient['national_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-calendar me-2"></i><?php echo __('age'); ?>:</td>
                                        <td><?php echo $next_patient['age']; ?> <?php echo __('years'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-gender-ambiguous me-2"></i><?php echo __('gender'); ?>:</td>
                                        <td><?php echo htmlspecialchars(__($next_patient['gender'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <p class="mb-2">
                                        <i class="bi bi-shield-check me-2"></i>
                                        <strong><?php echo __('chronic'); ?>:</strong> <?php echo $next_patient['is_chronic'] ? __('yes') : __('no'); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="bi bi-heart-pulse me-2"></i>
                                        <strong><?php echo __('pregnant'); ?>:</strong> <?php echo $next_patient['is_pregnant'] ? __('yes') : __('no'); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong><?php echo __('critical'); ?>:</strong> <?php echo $next_patient['is_critical'] ? __('yes') : __('no'); ?>
                                    </p>
                                </div>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-flag me-1"></i><?php echo __('priority'); ?>: <?php echo $next_patient['priority']; ?>
                                </span>
                                <?php if (!empty($next_patient['suggested_department'])): ?>
                                <span class="badge bg-info ms-1">
                                    <i class="bi bi-hospital me-1"></i><?php echo htmlspecialchars($next_patient['suggested_department']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-clipboard2-pulse me-2"></i><?php echo __('current_symptoms'); ?>:
                            </h6>
                            <div class="alert alert-light border">
                                <?php echo nl2br(htmlspecialchars($next_patient['current_conditions'] ?? '')); ?>
                            </div>
                        </div>

                        <?php if (!empty($next_patient['other_diseases'])): ?>
                        <div class="mb-3">
                            <h6 class="fw-bold text-danger">
                                <i class="bi bi-file-medical me-2"></i><?php echo __('other_diseases'); ?>:
                            </h6>
                            <div class="alert alert-warning border">
                                <?php echo nl2br(htmlspecialchars($next_patient['other_diseases'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ✅ صندوق اقتراح الذكاء الاصطناعي المحسّن -->
                        <?php if ($ai_suggestion && is_array($ai_suggestion)): ?>
                        <div class="ai-suggestion" id="aiSuggestionBox">
                            <h6>
                                <i class="bi bi-robot me-2"></i><?php echo __('ai_treatment_suggestion'); ?>
                                <small class="text-muted ms-2">(مُحلّل حسب حالة المريض)</small>
                            </h6>
                            
                            <!-- الدواء المقترح -->
                            <div class="ai-medication-box">
                                <p class="mb-1 fw-bold text-success">
                                    <i class="bi bi-capsule me-2"></i>💊 الدواء المقترح:
                                </p>
                                <p class="mb-0" id="aiMedicationText">
                                    <?php echo htmlspecialchars($ai_suggestion['primary_medication'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            
                            <!-- ملاحظات الأمان -->
                            <div class="ai-considerations-box">
                                <p class="mb-1 fw-bold text-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>⚠️ ملاحظات الأمان والتحذيرات:
                                </p>
                                <p class="mb-0 text-muted">
                                    <?php echo nl2br(htmlspecialchars($ai_suggestion['considerations'] ?? 'No specific considerations.')); ?>
                                </p>
                            </div>
                            
                            <!-- أزرار التحكم -->
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-copy-ai" onclick="copyAiToPrescription()">
                                    <i class="bi bi-clipboard-check me-1"></i> استخدام هذا الاقتراح في الوصفة
                                </button>
                                <button type="button" class="btn btn-refresh-ai" onclick="refreshAiSuggestion()">
                                    <i class="bi bi-arrow-clockwise me-1"></i> تحديث الاقتراح
                                </button>
                            </div>
                            
                            <!-- مؤشر التحميل -->
                            <div class="ai-loading" id="aiLoading">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">جاري تحليل حالة المريض بواسطة الذكاء الاصطناعي...</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            اقتراح الذكاء الاصطناعي غير متوفر حالياً. يرجى كتابة الوصفة يدوياً.
                        </div>
                        <?php endif; ?>

                        <hr>
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="patient_id" value="<?php echo $next_patient['id']; ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-capsule me-2"></i><?php echo __('prescribed_medication'); ?> *
                                </label>
                                <textarea class="form-control" name="medication" id="medicationTextarea" rows="3" required 
                                          placeholder="<?php echo __('medication_placeholder'); ?>"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-journal-medical me-2"></i><?php echo __('doctors_notes'); ?>
                                </label>
                                <textarea class="form-control" name="notes" id="notesTextarea" rows="4" 
                                          placeholder="<?php echo __('notes_placeholder'); ?>"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-clock me-2"></i><?php echo __('consultation_duration'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="duration" value="15" min="5" max="120" required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="complete_consultation" class="btn btn-complete btn-lg">
                                    <i class="bi bi-check-circle me-2"></i><?php echo __('complete_and_send'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted mb-4"></i>
                        <h4 class="text-muted"><?php echo __('no_patients_queue'); ?></h4>
                        <p class="text-muted">لا يوجد مرضى موجهون لتخصصك (<strong><?php echo htmlspecialchars($doctor_specialization); ?></strong>) في الانتظار حالياً.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (count($queue) > 1): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul me-2"></i><?php echo __('upcoming_patients'); ?> (<?php echo count($queue) - 1; ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo __('name'); ?></th>
                                    <th><?php echo __('age'); ?></th>
                                    <th><?php echo __('priority'); ?></th>
                                    <th><?php echo __('condition'); ?></th>
                                    <th>القسم المقترح</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($queue, 1, 5) as $patient): 
                                    $prio_class = '';
                                    $badge_class = 'bg-secondary';
                                    switch($patient['priority']) {
                                        case 1: $prio_class = 'table-danger'; $badge_class = 'bg-danger'; break;
                                        case 2: $prio_class = 'table-warning'; $badge_class = 'bg-warning text-dark'; break;
                                        case 3: $prio_class = 'table-warning'; $badge_class = 'bg-warning text-dark'; break;
                                        case 4: $prio_class = 'table-info'; $badge_class = 'bg-info text-dark'; break;
                                        case 5: $prio_class = 'table-primary'; $badge_class = 'bg-primary'; break;
                                        case 6: $prio_class = ''; $badge_class = 'bg-secondary'; break;
                                    }
                                ?>
                                <tr class="<?php echo $prio_class; ?>">
                                    <td class="fw-bold"><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                    <td><?php echo $patient['age']; ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $patient['priority']; ?></span></td>
                                    <td><?php echo substr($patient['current_conditions'], 0, 50) . '...'; ?></td>
                                    <td>
                                        <?php if (!empty($patient['suggested_department'])): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo htmlspecialchars($patient['suggested_department']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ✅ دالة نسخ اقتراح الذكاء الاصطناعي إلى حقل الوصفة
        function copyAiToPrescription() {
            const aiText = document.getElementById('aiMedicationText').innerText.trim();
            const prescriptionTextarea = document.getElementById('medicationTextarea');
            const notesTextarea = document.getElementById('notesTextarea');
            
            if (prescriptionTextarea && aiText) {
                // نسخ الدواء إلى الوصفة
                if (prescriptionTextarea.value.trim() === '') {
                    prescriptionTextarea.value = aiText;
                } else {
                    prescriptionTextarea.value += "\n\n--- AI Suggestion ---\n" + aiText;
                }
                
                // نسخ ملاحظات الأمان إلى ملاحظات الطبيب
                const considerationsBox = document.querySelector('.ai-considerations-box p:last-child');
                if (considerationsBox && notesTextarea) {
                    const considerations = considerationsBox.innerText.trim();
                    if (considerations && !considerations.includes('No specific considerations')) {
                        if (notesTextarea.value.trim() === '') {
                            notesTextarea.value = "⚠️ AI Safety Notes:\n" + considerations;
                        } else {
                            notesTextarea.value += "\n\n⚠️ AI Safety Notes:\n" + considerations;
                        }
                    }
                }
                
                // تأثير بصري
                prescriptionTextarea.style.borderColor = '#4caf50';
                prescriptionTextarea.style.backgroundColor = '#e8f5e9';
                setTimeout(() => {
                    prescriptionTextarea.style.borderColor = '';
                    prescriptionTextarea.style.backgroundColor = '';
                }, 2000);
                
                // إشعار نجاح
                showNotification('✅ تم نسخ اقتراح الذكاء الاصطناعي إلى الوصفة بنجاح!');
            }
        }
        
        // ✅ دالة تحديث اقتراح الذكاء الاصطناعي (AJAX)
        function refreshAiSuggestion() {
            const loadingDiv = document.getElementById('aiLoading');
            const suggestionBox = document.getElementById('aiSuggestionBox');
            
            if (loadingDiv) {
                loadingDiv.style.display = 'block';
                
                // محاكاة تحديث (يمكن استبداله بطلب AJAX حقيقي)
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        }
        
        // ✅ دالة عرض الإشعارات
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            notification.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // ✅ تحديث تلقائي للطابور كل 30 ثانية (فقط إذا لم يكن الطبيب يكتب)
        setInterval(function() {
            let medInput = document.getElementById('medicationTextarea');
            let notesInput = document.getElementById('notesTextarea');

            let canRefresh = (!medInput && !notesInput) || 
                             (medInput && medInput.value.trim() === '' && notesInput && notesInput.value.trim() === '');

            if (canRefresh) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>