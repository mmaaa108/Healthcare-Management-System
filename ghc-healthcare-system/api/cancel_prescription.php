<?php
/**
 * إلغاء الوصفة الطبية (عند عدم توفر الدواء)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$prescription_id = $_POST['prescription_id'] ?? 0;
$reason = $_POST['reason'] ?? 'Medication not available in warehouse';

if ($prescription_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid prescription ID']);
    exit();
}

$lang = $_SESSION['lang'] ?? 'ar';
$is_arabic = ($lang === 'ar');

try {
    $database = new Database();
    $db = $database->getConnection();

    // التحقق من وجود الوصفة وحالتها
    $check_query = "SELECT id, status FROM prescriptions WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':id', $prescription_id);
    $stmt->execute();
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescription) {
        echo json_encode(['success' => false, 'message' => $is_arabic ? 'الوصفة غير موجودة' : 'Prescription not found']);
        exit();
    }

    if ($prescription['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => $is_arabic ? 'الوصفة ليست قيد الانتظار' : 'Prescription is not pending']);
        exit();
    }

    // تحديث حالة الوصفة إلى ملغاة
    $update_query = "UPDATE prescriptions 
                     SET status = 'cancelled',
                         notes = CONCAT(IFNULL(notes, ''), '\n[Cancelled by Pharmacist] Reason: ', :reason)
                     WHERE id = :id";
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':id', $prescription_id);
    $stmt->bindParam(':reason', $reason);
    
    if ($stmt->execute() && $stmt->rowCount() > 0) {
        // تسجيل العملية في سجل النظام
        try {
            $log_query = "INSERT INTO system_logs (user_id, action, description, ip_address)
                          VALUES (:user_id, 'PRESCRIPTION_CANCELLED', :description, :ip)";
            $log_stmt = $db->prepare($log_query);
            $user_id = $_SESSION['user_id'];
            $description = "Pharmacist cancelled prescription ID: $prescription_id - Reason: $reason";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt->bindParam(':user_id', $user_id);
            $log_stmt->bindParam(':description', $description);
            $log_stmt->bindParam(':ip', $ip);
            $log_stmt->execute();
        } catch (Exception $e) {
            error_log("Logging error: " . $e->getMessage());
        }

        $msg = $is_arabic 
            ? 'تم إنهاء الطلب بنجاح. سيتم إخطار الطبيب والمريض.'
            : 'Order cancelled successfully. Doctor and patient will be notified.';
        
        echo json_encode([
            'success' => true, 
            'message' => $msg,
            'prescription_id' => $prescription_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $is_arabic ? 'فشل إنهاء الطلب' : 'Failed to cancel order']);
    }

} catch (Exception $e) {
    error_log("Cancel prescription error: " . $e->getMessage());
    $msg = $is_arabic ? 'حدث خطأ في الخادم' : 'Server error occurred';
    echo json_encode(['success' => false, 'message' => $msg]);
}
?>