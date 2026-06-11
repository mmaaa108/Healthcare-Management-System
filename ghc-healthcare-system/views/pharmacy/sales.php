<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../login.php?type=pharmacist");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$filter = $_GET['filter'] ?? 'all';

$query = "SELECT 
            p.id,
            p.amount,
            p.payment_method,
            p.institution_type,
            p.paid_at,
            dm.quantity,
            dm.dosage_instructions,
            m.name as medication_name,
            u_patient.full_name as patient_name,
            u_doctor.full_name as doctor_name
          FROM payments p
          JOIN prescriptions pr ON p.prescription_id = pr.id
          LEFT JOIN dispensed_medications dm ON dm.prescription_id = pr.id
          LEFT JOIN medications m ON dm.medication_id = m.id
          JOIN patients pt ON p.patient_id = pt.id
          JOIN users u_patient ON pt.user_id = u_patient.id
          JOIN users u_doctor ON pr.doctor_id = u_doctor.id
          WHERE 1=1";

if ($filter !== 'all') {
    $query .= " AND p.institution_type = :filter";
}

$query .= " ORDER BY p.paid_at DESC LIMIT 100";

$sales = [];
$total_revenue = 0;
$total_transactions = 0;
$total_quantity = 0;

try {
    $stmt = $db->prepare($query);
    if ($filter !== 'all') {
        $stmt->bindParam(':filter', $filter);
    }
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_revenue = array_sum(array_column($sales, 'amount'));
    $total_transactions = count($sales);
    $total_quantity = array_sum(array_column($sales, 'quantity'));
} catch (Exception $e) {
    error_log("Sales page error: " . $e->getMessage());
    $sales = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('sales'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f8f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-pharmacy { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .table th { background: #e8f5e9; }
        .badge-government { background: #0d6efd; }
        .badge-private { background: #dc3545; }
        .badge-charity { background: #198754; }
    </style>
</head>
<body>
    <nav class="navbar navbar-pharmacy navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-cash-stack me-2"></i><?php echo __('sales'); ?>
            </span>
            <div>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i><?php echo __('back_to_dashboard'); ?>
                </a>
                <a href="../../controllers/auth_handler.php?action=logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i><?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-currency-dollar display-4 text-success mb-3"></i>
                        <h5 class="text-muted"><?php echo __('total_revenue'); ?></h5>
                        <h2 class="display-4 fw-bold text-success">$<?php echo number_format($total_revenue, 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-receipt display-4 text-info mb-3"></i>
                        <h5 class="text-muted"><?php echo __('total_transactions'); ?></h5>
                        <h2 class="display-4 fw-bold text-info"><?php echo $total_transactions; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-box-seam display-4 text-primary mb-3"></i>
                        <h5 class="text-muted"><?php echo __('total_quantity'); ?></h5>
                        <h2 class="display-4 fw-bold text-primary"><?php echo $total_quantity; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i><?php echo __('sales_history'); ?></h5>
                <div>
                    <a href="?filter=all" class="btn btn-sm <?php echo $filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo __('all_institutions'); ?></a>
                    <a href="?filter=government" class="btn btn-sm <?php echo $filter == 'government' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo __('government'); ?></a>
                    <a href="?filter=private" class="btn btn-sm <?php echo $filter == 'private' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo __('private'); ?></a>
                    <a href="?filter=charity" class="btn btn-sm <?php echo $filter == 'charity' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo __('charity'); ?></a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($sales)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                    <h4 class="text-muted"><?php echo __('no_sales_found'); ?></h4>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('patient'); ?></th>
                                <th><?php echo __('doctor'); ?></th>
                                <th><?php echo __('medication'); ?></th>
                                <th><?php echo __('quantity'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('payment_method'); ?></th>
                                <th><?php echo __('institution_type'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo !empty($sale['paid_at']) ? date('Y-m-d H:i', strtotime($sale['paid_at'])) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($sale['patient_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($sale['doctor_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($sale['medication_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo (int)($sale['quantity'] ?? 0); ?></td>
                                <td class="fw-bold">$<?php echo number_format($sale['amount'] ?? 0, 2); ?></td>
                                <td>
                                    <?php 
                                    // Logic: If Charity, hide payment method or show dash
                                    if ($sale['institution_type'] === 'charity') {
                                        echo '<span class="text-muted">-</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">' . htmlspecialchars($sale['payment_method'] ?? 'cash') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $inst_type = $sale['institution_type'] ?? 'government';
                                    $badge_class = 'badge-government';
                                    if ($inst_type == 'private') $badge_class = 'badge-private';
                                    if ($inst_type == 'charity') $badge_class = 'badge-charity';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $inst_type; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="table-success">
                                <td colspan="5" class="text-end fw-bold"><?php echo __('total'); ?>:</td>
                                <td class="fw-bold fs-5"><?php echo $total_quantity; ?></td>
                                <td class="fw-bold fs-5">$<?php echo number_format($total_revenue, 2); ?></td>
                                <td colspan="2" class="fw-bold"><?php echo $total_transactions; ?> <?php echo __('transactions'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>