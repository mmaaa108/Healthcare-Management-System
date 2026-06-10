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

// Get all medications
$query = "SELECT * FROM medications ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('inventory'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($lang === 'ar'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f8f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-pharmacy { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stock-low { background: #fff3cd; }
        .stock-out { background: #f8d7da; }
        .stock-ok { background: #d1ecf1; }
    </style>
</head>
<body>
    <nav class="navbar navbar-pharmacy navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-box-seam me-2"></i><?php echo __('inventory'); ?>
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
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-capsule display-4 text-primary mb-3"></i>
                        <h5 class="text-muted"><?php echo __('total_medications'); ?></h5>
                        <h2 class="display-4 fw-bold text-primary"><?php echo count($medications); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i><?php echo __('medication_list'); ?></h5>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i><?php echo __('add_new'); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?php echo __('name'); ?></th>
                                <th><?php echo __('category'); ?></th>
                                <th><?php echo __('quantity'); ?></th>
                                <th><?php echo __('unit_price'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medications as $med): 
                                $stock_class = '';
                                $status_badge = '';
                                if ($med['quantity_in_stock'] <= 0) {
                                    $stock_class = 'stock-out';
                                    $status_badge = '<span class="badge bg-danger">' . __('out_of_stock') . '</span>';
                                } elseif ($med['quantity_in_stock'] < 50) {
                                    $stock_class = 'stock-low';
                                    $status_badge = '<span class="badge bg-warning">' . __('low_stock') . '</span>';
                                } else {
                                    $stock_class = 'stock-ok';
                                    $status_badge = '<span class="badge bg-success">' . __('in_stock') . '</span>';
                                }
                            ?>
                            <tr class="<?php echo $stock_class; ?>">
                                <td class="fw-bold"><?php echo htmlspecialchars($med['name']); ?></td>
                                <td><?php echo htmlspecialchars($med['category']); ?></td>
                                <td><?php echo $med['quantity_in_stock']; ?></td>
                                <td>$<?php echo $med['unit_price']; ?></td>
                                <td><?php echo $status_badge; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $med['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="../../controllers/inventory_handler.php?action=delete_medication&id=<?php echo $med['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('<?php echo __('confirm_delete'); ?> <?php echo htmlspecialchars($med['name']); ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('add_medication'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../controllers/inventory_handler.php" method="POST">
                    <input type="hidden" name="action" value="add_medication">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('name'); ?></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('category'); ?></label>
                            <select class="form-select" name="category" required>
                                <option value="Pain Relief"><?php echo __('pain_relief'); ?></option>
                                <option value="Antibiotic"><?php echo __('antibiotic'); ?></option>
                                <option value="Diabetes"><?php echo __('diabetes'); ?></option>
                                <option value="Hypertension"><?php echo __('hypertension'); ?></option>
                                <option value="Gastric"><?php echo __('gastric'); ?></option>
                                <option value="Respiratory"><?php echo __('respiratory'); ?></option>
                                <option value="Allergy"><?php echo __('allergy'); ?></option>
                                <option value="Other"><?php echo __('other'); ?></option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label"><?php echo __('quantity'); ?></label>
                                <input type="number" class="form-control" name="quantity" value="0" min="0" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label"><?php echo __('unit_price'); ?></label>
                                <input type="number" class="form-control" name="unit_price" value="0" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('description'); ?></label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" class="btn btn-success"><?php echo __('save'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($medications as $med): ?>
    <div class="modal fade" id="editModal<?php echo $med['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('edit'); ?> - <?php echo htmlspecialchars($med['name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../controllers/inventory_handler.php" method="POST">
                    <input type="hidden" name="action" value="update_medication">
                    <input type="hidden" name="medication_id" value="<?php echo $med['id']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('name'); ?></label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($med['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('category'); ?></label>
                            <select class="form-select" name="category" required>
                                <option value="Pain Relief" <?php echo $med['category'] == 'Pain Relief' ? 'selected' : ''; ?>><?php echo __('pain_relief'); ?></option>
                                <option value="Antibiotic" <?php echo $med['category'] == 'Antibiotic' ? 'selected' : ''; ?>><?php echo __('antibiotic'); ?></option>
                                <option value="Diabetes" <?php echo $med['category'] == 'Diabetes' ? 'selected' : ''; ?>><?php echo __('diabetes'); ?></option>
                                <option value="Hypertension" <?php echo $med['category'] == 'Hypertension' ? 'selected' : ''; ?>><?php echo __('hypertension'); ?></option>
                                <option value="Gastric" <?php echo $med['category'] == 'Gastric' ? 'selected' : ''; ?>><?php echo __('gastric'); ?></option>
                                <option value="Respiratory" <?php echo $med['category'] == 'Respiratory' ? 'selected' : ''; ?>><?php echo __('respiratory'); ?></option>
                                <option value="Allergy" <?php echo $med['category'] == 'Allergy' ? 'selected' : ''; ?>><?php echo __('allergy'); ?></option>
                                <option value="Other" <?php echo $med['category'] == 'Other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label"><?php echo __('quantity'); ?></label>
                                <input type="number" class="form-control" name="quantity" value="<?php echo $med['quantity_in_stock']; ?>" min="0" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label"><?php echo __('unit_price'); ?></label>
                                <input type="number" class="form-control" name="unit_price" value="<?php echo $med['unit_price']; ?>" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('description'); ?></label>
                            <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($med['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo __('update'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>