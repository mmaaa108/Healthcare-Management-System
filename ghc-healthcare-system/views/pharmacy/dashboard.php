<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../login.php?type=pharmacist");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pharmacist.php';
require_once __DIR__ . '/../../models/Prescription.php';
require_once __DIR__ . '/../../includes/language.php';

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

$database = new Database();
$db = $database->getConnection();
$prescription_model = new Prescription($db);

// Get pending prescriptions
$pending = [];
try {
    $pending = $prescription_model->getPendingForPharmacy();
} catch (Exception $e) {
    error_log("Error fetching pending prescriptions: " . $e->getMessage());
}

// Get all medications for dropdown
$medications = [];
try {
    $meds_query = "SELECT * FROM medications ORDER BY name";
    $stmt = $db->prepare($meds_query);
    $stmt->execute();
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching medications: " . $e->getMessage());
}

// Get low stock medications
$low_stock_medications = [];
try {
    $low_stock_query = "SELECT * FROM medications WHERE quantity_in_stock > 0 AND quantity_in_stock < 50 ORDER BY quantity_in_stock ASC";
    $stmt = $db->prepare($low_stock_query);
    $stmt->execute();
    $low_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching low stock: " . $e->getMessage());
}

// Get out of stock medications
$out_of_stock_medications = [];
try {
    $out_stock_query = "SELECT * FROM medications WHERE quantity_in_stock <= 0 ORDER BY name";
    $stmt = $db->prepare($out_stock_query);
    $stmt->execute();
    $out_of_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching out of stock: " . $e->getMessage());
}

$low_stock_count = count($low_stock_medications);
$out_stock_count = count($out_of_stock_medications);

// ✅ متغيرات اللغة
$is_arabic = ($lang === 'ar');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('pharmacist'); ?> - <?php echo __('dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f8f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-pharmacy { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .prescription-card { border-left: 5px solid #17a2b8; }
        .btn-dispense { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; }
        .ai-btn { 
            background: linear-gradient(135deg, #6f42c1 0%, #8e44ad 100%); 
            border: none;
            transition: all 0.3s;
        }
        .ai-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(111, 66, 193, 0.4);
        }
        
        .stock-alert-section { border-radius: 15px; margin-bottom: 20px; }
        .stock-alert-critical { background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); color: white; }
        .stock-alert-warning { background: linear-gradient(135deg, #ffc107 0%, #ffdb4d 100%); color: #333; }
        .stock-alert-item { background: rgba(255,255,255,0.95); border-radius: 10px; padding: 12px 15px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .pulse-animation { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
        
        .ai-result-container { margin-top: 15px; }
        .charity-notice { margin-top: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-pharmacy navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-capsule-pill me-2"></i><?php echo __('pharmacist'); ?> - <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Pharmacist'); ?>
            </span>
            <div>
                <?php if ($lang === 'en'): ?>
                <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="inventory.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-box-seam me-1"></i><?php echo __('inventory'); ?>
                </a>
                <a href="sales.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-cash-stack me-1"></i><?php echo __('sales'); ?>
                </a>
                <a href="../../controllers/auth_handler.php?action=logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i><?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- CRITICAL ALERTS: Out of Stock -->
        <?php if ($out_stock_count > 0): ?>
        <div class="card stock-alert-section stock-alert-critical pulse-animation">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-exclamation-diamond-fill fs-1 me-3"></i>
                    <div>
                        <h4 class="mb-0 fw-bold"><?php echo __('critical_stock_alert'); ?> - <?php echo $out_stock_count; ?> <?php echo __('items'); ?></h4>
                        <small><?php echo __('immediate_restock_required'); ?></small>
                    </div>
                </div>
                <div class="stock-alerts-list">
                    <?php foreach (array_slice($out_of_stock_medications, 0, 5) as $med): ?>
                    <div class="stock-alert-item">
                        <div>
                            <i class="bi bi-x-circle-fill me-2"></i>
                            <strong><?php echo htmlspecialchars($med['name']); ?></strong>
                            <span class="opacity-75">(<?php echo htmlspecialchars($med['category']); ?>)</span>
                        </div>
                        <span class="badge bg-white text-danger"><?php echo __('out_of_stock'); ?> - 0 <?php echo __('units'); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- WARNING ALERTS: Low Stock -->
        <?php if ($low_stock_count > 0): ?>
        <div class="card stock-alert-section stock-alert-warning">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-exclamation-triangle-fill fs-1 me-3 text-warning"></i>
                    <div>
                        <h4 class="mb-0 fw-bold"><?php echo __('low_stock_warning'); ?> - <?php echo $low_stock_count; ?> <?php echo __('items'); ?></h4>
                        <small><?php echo __('please_restock_soon'); ?></small>
                    </div>
                </div>
                <div class="stock-alerts-list">
                    <?php foreach (array_slice($low_stock_medications, 0, 5) as $med): ?>
                    <div class="stock-alert-item">
                        <div>
                            <i class="bi bi-exclamation-circle-fill text-warning me-2"></i>
                            <strong><?php echo htmlspecialchars($med['name']); ?></strong>
                            <span class="opacity-75">(<?php echo htmlspecialchars($med['category']); ?>)</span>
                        </div>
                        <span class="badge bg-warning text-dark"><?php echo $med['quantity_in_stock']; ?> <?php echo __('units_left'); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-prescription display-4 text-info mb-3"></i>
                        <h5 class="text-muted"><?php echo __('prescriptions'); ?></h5>
                        <h2 class="display-4 fw-bold text-info"><?php echo count($pending); ?></h2>
                        <small class="text-muted"><?php echo __('pending'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <i class="bi bi-exclamation-triangle display-4 text-warning mb-3"></i>
                        <h5 class="text-muted"><?php echo __('low_stock'); ?></h5>
                        <h2 class="display-4 fw-bold text-warning"><?php echo $low_stock_count; ?></h2>
                        <small class="text-muted"><?php echo __('items'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <i class="bi bi-x-octagon display-4 text-danger mb-3"></i>
                        <h5 class="text-muted"><?php echo __('out_of_stock'); ?></h5>
                        <h2 class="display-4 fw-bold text-danger"><?php echo $out_stock_count; ?></h2>
                        <small class="text-muted"><?php echo __('items'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-capsule display-4 text-primary mb-3"></i>
                        <h5 class="text-muted"><?php echo __('total_medications'); ?></h5>
                        <h2 class="display-4 fw-bold text-primary"><?php echo count($medications); ?></h2>
                        <small class="text-muted"><?php echo __('items'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Prescriptions -->
        <div class="row">
            <div class="col-12">
                <h4 class="fw-bold mb-3">
                    <i class="bi bi-prescription2 me-2"></i><?php echo __('pending_prescriptions'); ?>
                </h4>
                
                <?php if (empty($pending)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted mb-4"></i>
                        <h4 class="text-muted"><?php echo __('no_pending_prescriptions'); ?></h4>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($pending as $prescription): ?>
                <div class="card prescription-card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <!-- بيانات المريض الكاملة -->
                            <div class="col-md-4">
                                <h5 class="fw-bold">
                                    <i class="bi bi-person me-2"></i><?php echo htmlspecialchars($prescription['patient_name'] ?? 'Unknown'); ?>
                                </h5>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-card-text me-2"></i><?php echo __('national_id'); ?>: 
                                    <strong><?php echo htmlspecialchars($prescription['patient_national_id'] ?? 'N/A'); ?></strong>
                                </p>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-calendar me-2"></i><?php echo __('age'); ?>: 
                                    <strong><?php echo $prescription['patient_age'] ?? '0'; ?> <?php echo __('years'); ?></strong>
                                </p>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-gender-ambiguous me-2"></i><?php echo __('gender'); ?>: 
                                    <strong><?php echo htmlspecialchars(__($prescription['patient_gender'] ?? 'unknown')); ?></strong>
                                </p>
                                <?php if (!empty($prescription['is_pregnant'])): ?>
                                <span class="badge bg-pink text-dark me-1">
                                    <i class="bi bi-heart-pulse me-1"></i><?php echo __('pregnant'); ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($prescription['is_chronic'])): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-shield-check me-1"></i><?php echo __('chronic'); ?>
                                </span>
                                <?php endif; ?>
                                <hr>
                                <span class="badge bg-info">
                                    <i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($prescription['doctor_name'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                            
                            <!-- الوصفة الطبية -->
                            <div class="col-md-4">
                                <h6 class="fw-bold text-primary">
                                    <i class="bi bi-capsule me-2"></i><?php echo __('prescribed_medication'); ?>
                                </h6>
                                <div class="alert alert-light border prescribed-med-text">
                                    <?php echo nl2br(htmlspecialchars($prescription['medication'] ?? 'No medication specified')); ?>
                                </div>
                                <?php if (!empty($prescription['notes'])): ?>
                                <small class="text-muted doctor-notes">
                                    <i class="bi bi-journal-text me-1"></i><?php echo htmlspecialchars($prescription['notes']); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            
                            <!-- نموذج الصرف -->
                            <div class="col-md-4">
                                <form action="../../controllers/pharmacy_controller.php" method="POST" class="dispense-form">
                                    <input type="hidden" name="action" value="dispense_medication">
                                    <input type="hidden" name="redirect_to" value="dashboard">
                                    <input type="hidden" name="prescription_id" value="<?php echo htmlspecialchars($prescription['prescription_id'] ?? 0); ?>">
                                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($prescription['patient_id'] ?? 0); ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold"><?php echo __('select_medication'); ?></label>
                                        <select class="form-select medication-select" name="medication_id" required>
                                            <option value=""><?php echo __('choose_medication'); ?></option>
                                            <?php foreach ($medications as $med): ?>
                                            <option value="<?php echo $med['id']; ?>" 
                                                    data-price="<?php echo $med['unit_price']; ?>"
                                                    data-stock="<?php echo $med['quantity_in_stock']; ?>"
                                                    data-name="<?php echo htmlspecialchars($med['name']); ?>">
                                                <?php echo htmlspecialchars($med['name']); ?> 
                                                (<?php echo $med['quantity_in_stock']; ?> <?php echo __('in_stock'); ?>)
                                                - $<?php echo $med['unit_price']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label"><?php echo __('quantity'); ?></label>
                                            <input type="number" class="form-control quantity-input" name="quantity" value="1" min="1" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label"><?php echo __('total'); ?></label>
                                            <input type="text" class="form-control total-price" readonly value="$0.00">
                                            <input type="hidden" name="amount" class="amount-hidden" value="0.00">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo __('dosage_instructions'); ?></label>
                                        <input type="text" class="form-control" name="dosage_instructions" placeholder="<?php echo __('e_g_3_times_daily'); ?>">
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label"><?php echo __('institution_type'); ?></label>
                                            <select class="form-select institution-select" name="institution_type" required>
                                                <option value="government"><?php echo __('government'); ?></option>
                                                <option value="private"><?php echo __('private'); ?></option>
                                                <option value="charity"><?php echo __('charity'); ?> (<?php echo $is_arabic ? 'مجاني' : 'Free'; ?>)</option>
                                            </select>
                                        </div>
                                        <div class="col-6 payment-method-col">
                                            <label class="form-label"><?php echo __('payment_method'); ?></label>
                                            <select class="form-select payment-method-select" name="payment_method">
                                                <option value="cash"><?php echo __('cash'); ?></option>
                                                <option value="bank"><?php echo __('bank'); ?></option>
                                                <option value="palPay">PalPay</option>
                                                <option value="jawalPay">JawalPay</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- رسالة خيري -->
                                    <div class="alert alert-success charity-notice" style="display:none;">
                                        <i class="bi bi-heart-fill me-2"></i>
                                        <strong><?php echo __('charity'); ?>:</strong> 
                                        <?php echo $is_arabic ? 'سيتم صرف الدواء مجاناً (المبلغ = $0.00)' : 'Medication will be dispensed for free (Amount = $0.00)'; ?>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-dispense btn-lg text-white">
                                            <i class="bi bi-check-circle me-2"></i><?php echo __('dispense_medication'); ?>
                                        </button>
                                        <!-- ✅ زر AI ثابت دائماً -->
                                        <button type="button" class="btn ai-btn text-white ai-alternative-btn">
                                            <i class="bi bi-robot me-2"></i><?php echo $is_arabic ? 'اقتراح AI للدواء المناسب' : 'AI Suggest Medication'; ?>
                                        </button>
                                    </div>
                                    
                                    <!-- حاوية نتيجة الذكاء الاصطناعي -->
                                    <div class="ai-result-container"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ متغيرات اللغة
const isArabic = <?php echo $is_arabic ? 'true' : 'false'; ?>;

// ====================================================================
// 1. دالة لتحديث الواجهة بناءً على نوع المؤسسة (خيري أو غير ذلك)
// ====================================================================
function updatePaymentVisibility(form) {
    const institutionSelect = form.querySelector('.institution-select');
    const paymentCol = form.querySelector('.payment-method-col');
    const paymentSelect = form.querySelector('.payment-method-select');
    const charityNotice = form.querySelector('.charity-notice');
    const totalInput = form.querySelector('.total-price');
    const amountHidden = form.querySelector('.amount-hidden');
    
    if (!institutionSelect) return;

    const institutionType = institutionSelect.value;
    
    if (institutionType === 'charity') {
        if (paymentCol) paymentCol.style.display = 'none';
        if (paymentSelect) {
            paymentSelect.disabled = true;
            paymentSelect.value = 'cash';
        }
        if (charityNotice) charityNotice.style.display = 'block';
        if (totalInput) totalInput.value = isArabic ? '$0.00 (مجاني)' : '$0.00 (Free)';
        if (amountHidden) amountHidden.value = '0.00';
    } else {
        if (paymentCol) paymentCol.style.display = 'block';
        if (paymentSelect) {
            paymentSelect.disabled = false;
            paymentSelect.value = 'cash';
        }
        if (charityNotice) charityNotice.style.display = 'none';
        
        const medSelect = form.querySelector('.medication-select');
        const quantityInput = form.querySelector('.quantity-input');
        if (medSelect && quantityInput) {
            const option = medSelect.options[medSelect.selectedIndex];
            const price = parseFloat(option.dataset.price) || 0;
            const quantity = parseInt(quantityInput.value) || 1;
            const computed = (price * quantity) || 0;
            if (totalInput) totalInput.value = '$' + computed.toFixed(2);
            if (amountHidden) amountHidden.value = computed.toFixed(2);
        }
    }
}

// ====================================================================
// 2. معالجة تغيير الدواء
// ====================================================================
document.querySelectorAll('.medication-select').forEach(select => {
    select.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const price = parseFloat(option.dataset.price) || 0;
        const stock = parseInt(option.dataset.stock) || 0;
        const form = this.closest('form');
        const quantityInput = form.querySelector('.quantity-input');
        const aiResultDiv = form.querySelector('.ai-result-container');

        if (aiResultDiv) aiResultDiv.innerHTML = '';

        if (stock <= 0) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }

        const institutionSelect = form.querySelector('.institution-select');
        if (institutionSelect && institutionSelect.value !== 'charity') {
            const quantity = parseInt(quantityInput.value) || 1;
            const totalInput = form.querySelector('.total-price');
            const amountHidden = form.querySelector('.amount-hidden');
            const computed = (price * quantity) || 0;
            if (totalInput) totalInput.value = '$' + computed.toFixed(2);
            if (amountHidden) amountHidden.value = computed.toFixed(2);
        }
    });
});

// ====================================================================
// 3. معالجة تغيير الكمية
// ====================================================================
document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('input', function() {
        const form = this.closest('form');
        const institutionSelect = form.querySelector('.institution-select');
        
        if (institutionSelect && institutionSelect.value !== 'charity') {
            const select = form.querySelector('.medication-select');
            const option = select.options[select.selectedIndex];
            const price = parseFloat(option.dataset.price) || 0;
            const totalInput = form.querySelector('.total-price');
            const amountHidden = form.querySelector('.amount-hidden');
            const quantity = parseInt(this.value) || 1;
            const computed = (price * quantity) || 0;
            if (totalInput) totalInput.value = '$' + computed.toFixed(2);
            if (amountHidden) amountHidden.value = computed.toFixed(2);
        }
    });
});

// ====================================================================
// 4. معالجة تغيير نوع المؤسسة (خيري / حكومي / خاص)
// ====================================================================
document.querySelectorAll('.institution-select').forEach(select => {
    select.addEventListener('change', function() {
        updatePaymentVisibility(this.closest('form'));
    });
});

// ====================================================================
// 5. تطبيق الحالة الابتدائية عند تحميل الصفحة
// ====================================================================
document.querySelectorAll('.dispense-form').forEach(form => {
    updatePaymentVisibility(form);
});

// ====================================================================
// 6. كود الذكاء الاصطناعي لاقتراح الدواء المناسب (مع دعم اللغتين)
// ====================================================================
document.querySelectorAll('.ai-alternative-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const form = this.closest('form');
        const patientId = form.querySelector('input[name="patient_id"]').value;
        const prescribedMed = form.closest('.card-body').querySelector('.prescribed-med-text').innerText.trim();
        const notesEl = form.closest('.card-body').querySelector('.doctor-notes');
        const notes = notesEl ? notesEl.innerText.replace(/^.*?:\s*/, '').trim() : '';
        const resultDiv = form.querySelector('.ai-result-container');
        
        // ✅ رسائل التحميل حسب اللغة
        const loadingMsg = isArabic 
            ? 'جاري تحليل الوصفة وحالة المريض واقتراح الدواء المناسب...'
            : 'Analyzing prescription and patient condition to suggest appropriate medication...';
        
        resultDiv.innerHTML = `
            <div class="alert alert-info mt-3">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    <span>${loadingMsg}</span>
                </div>
            </div>
        `;
        this.disabled = true;

        const formData = new FormData();
        formData.append('patient_id', patientId);
        formData.append('prescribed_medication', prescribedMed);
        formData.append('notes', notes);

        fetch('../../api/get_ai_alternative.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            this.disabled = false;
            if (data.success) {
                if (data.medication_found) {
                    // ✅ حالة وجود الدواء في المخزون
                    const s = data.suggestion;
                    
                    const suggestionTitle = isArabic ? 'اقتراح الذكاء الاصطناعي:' : 'AI Suggestion:';
                    const medLabel = isArabic ? 'الدواء المناسب:' : 'Recommended Medication:';
                    const reasonLabel = isArabic ? 'السبب:' : 'Reason:';
                    const selectBtn = isArabic ? 'اختيار هذا الدواء' : 'Select this Medication';
                    
                    resultDiv.innerHTML = `
                        <div class="alert alert-success mt-3 border-start border-4 border-success">
                            <h6 class="fw-bold"><i class="bi bi-robot me-2"></i>${suggestionTitle}</h6>
                            <p class="mb-1"><strong>${medLabel}</strong> ${s.suggested_medication_name}</p>
                            <p class="mb-2"><small><strong>${reasonLabel}</strong> ${s.reasoning}</small></p>
                            <button type="button" class="btn btn-sm btn-outline-success select-ai-alt" data-med-id="${s.suggested_medication_id}">
                                <i class="bi bi-check-circle me-1"></i> ${selectBtn}
                            </button>
                        </div>
                    `;
                    
                    resultDiv.querySelector('.select-ai-alt').addEventListener('click', function() {
                        const medId = this.getAttribute('data-med-id');
                        const medSelect = form.querySelector('.medication-select');
                        medSelect.value = medId;
                        medSelect.dispatchEvent(new Event('change'));
                        resultDiv.innerHTML = '';
                    });
                } else {
                    // ❌ حالة عدم وجود الدواء (الشراء من الخارج)
                    const alertTitle = isArabic ? 'تنبيه هام:' : 'Important Notice:';
                    const defaultMsg = isArabic 
                        ? `الدواء الموصوف (${prescribedMed}) غير متوفر في المستودع. يجب شراؤه من صيدلية خارجية حسب الوصفة الطبية.`
                        : `The prescribed medication (${prescribedMed}) is not available in the warehouse. It must be purchased from an external pharmacy according to the prescription.`;
                    
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger mt-3 border-start border-4 border-danger">
                            <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>${alertTitle}</h6>
                            <p class="mb-0">${data.message || defaultMsg}</p>
                            <p class="mb-0 mt-2"><strong>${isArabic ? 'الوصفة المطلوبة:' : 'Required Prescription:'}</strong> ${prescribedMed}</p>
                        </div>
                    `;
                }
            } else {
                const errorMsg = isArabic 
                    ? `حدث خطأ: ${data.message || 'فشل خدمة الذكاء الاصطناعي'}`
                    : `Error occurred: ${data.message || 'AI service failed'}`;
                resultDiv.innerHTML = `<div class="alert alert-warning mt-3">${errorMsg}</div>`;
            }
        })
        .catch(error => {
            this.disabled = false;
            const connectionError = isArabic 
                ? 'فشل الاتصال بالخادم. يرجى المحاولة لاحقاً.'
                : 'Failed to connect to the server. Please try again later.';
            resultDiv.innerHTML = `<div class="alert alert-danger mt-3">${connectionError}</div>`;
            console.error('AI Alternative Error:', error);
        });
    });
});
</script>
</body>
</html>