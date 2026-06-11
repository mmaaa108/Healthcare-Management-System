<?php
session_start();
require_once __DIR__ . '/../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../views/login.php?type=pharmacist");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'dispense_medication') {
    $prescription_id = filter_input(INPUT_POST, 'prescription_id', FILTER_VALIDATE_INT) ?? 0;
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT) ?? 0;
    $medication_id = filter_input(INPUT_POST, 'medication_id', FILTER_VALIDATE_INT) ?? 0;
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?? 1;
    $dosage_instructions = htmlspecialchars($_POST['dosage_instructions'] ?? '');
    $institution_type = in_array($_POST['institution_type'] ?? '', ['government', 'private', 'charity']) 
        ? $_POST['institution_type'] : 'government';
    $payment_method = in_array($_POST['payment_method'] ?? '', ['cash', 'bank', 'palPay', 'jawalPay']) 
        ? $_POST['payment_method'] : 'cash';
    $redirect_to = ($_POST['redirect_to'] ?? 'dashboard') === 'sales' ? 'sales' : 'dashboard';
    $pharmacist_id = $_SESSION['user_id'];

    // Validation
    if ($prescription_id <= 0 || $patient_id <= 0 || $medication_id <= 0) {
        header("Location: ../views/pharmacy/dashboard.php?error=" . urlencode('Invalid IDs provided'));
        exit();
    }
    
    if ($quantity <= 0) {
        header("Location: ../views/pharmacy/dashboard.php?error=" . urlencode('Quantity must be greater than 0'));
        exit();
    }

    try {
        $db->beginTransaction();

        // 1. Verify prescription exists and is pending
        $check_prescription = "SELECT status FROM prescriptions WHERE id = :prescription_id LIMIT 1";
        $stmt = $db->prepare($check_prescription);
        $stmt->bindParam(':prescription_id', $prescription_id);
        $stmt->execute();
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prescription) {
            throw new Exception('Prescription not found');
        }
        if ($prescription['status'] === 'dispensed') {
            throw new Exception('Prescription already dispensed');
        }

        // 2. Verify medication exists and has sufficient stock
$check_medication = "SELECT quantity_in_stock, unit_price, name FROM medications WHERE id = :medication_id LIMIT 1";
$stmt = $db->prepare($check_medication);
$stmt->bindParam(':medication_id', $medication_id);
$stmt->execute();
$medication = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medication) {
    throw new Exception('Medication not found');
}
if ($medication['quantity_in_stock'] <= 0) {
    throw new Exception('Medication is out of stock');
}
if ($medication['quantity_in_stock'] < $quantity) {
    throw new Exception('Insufficient stock. Available: ' . $medication['quantity_in_stock']);
}

// ✅ 3. حساب المبلغ بناءً على نوع المؤسسة
if ($institution_type === 'charity') {
    $amount = 0.00; // مجاني تماماً
    $payment_method = 'cash'; // نستخدم cash لتجنب أخطاء ENUM في قاعدة البيانات، لكن المبلغ سيكون 0
} else {
    $amount = round(((float) $medication['unit_price']) * $quantity, 2);
}

        // 4. Record the dispensing event
        $dosage_times_per_day = 1;
        if (preg_match('/(\d+)\s*(?:time|times|x)\s*(?:a\s*)?day/i', $dosage_instructions, $matches)) {
            $dosage_times_per_day = max(1, (int) $matches[1]);
        }

        $dispense_query = "INSERT INTO dispensed_medications 
            (prescription_id, medication_id, quantity, dosage_times_per_day, dosage_instructions, dispensed_by) 
            VALUES 
            (:prescription_id, :medication_id, :quantity, :dosage_times_per_day, :dosage_instructions, :dispensed_by)";
        $stmt = $db->prepare($dispense_query);
        $stmt->bindParam(':prescription_id', $prescription_id);
        $stmt->bindParam(':medication_id', $medication_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':dosage_times_per_day', $dosage_times_per_day);
        $stmt->bindParam(':dosage_instructions', $dosage_instructions);
        $stmt->bindParam(':dispensed_by', $pharmacist_id);
        $stmt->execute();

        // 5. Insert payment record
        $payment_query = "INSERT INTO payments 
            (prescription_id, patient_id, amount, payment_method, institution_type, 
             transaction_id, status, paid_at) 
            VALUES 
            (:prescription_id, :patient_id, :amount, :payment_method, :institution_type, 
             :transaction_id, 'completed', NOW())";
        $stmt = $db->prepare($payment_query);
        $stmt->bindParam(':prescription_id', $prescription_id);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':institution_type', $institution_type);
        $transaction_id = 'RX-' . $prescription_id . '-M' . $medication_id . '-P' . $pharmacist_id . '-' . time();
        $stmt->bindParam(':transaction_id', $transaction_id);
        $stmt->execute();

        // 6. Update prescription status to dispensed
        $update_prescription = "UPDATE prescriptions SET status = 'dispensed' WHERE id = :prescription_id";
        $stmt = $db->prepare($update_prescription);
        $stmt->bindParam(':prescription_id', $prescription_id);
        $stmt->execute();

        $db->commit();

        // Success message with amount info
        $amount_display = $institution_type === 'charity' 
            ? 'FREE (Charity)' 
            : '$' . number_format($amount, 2);
        
        $redirect_url = $redirect_to === 'sales' 
            ? "../views/pharmacy/sales.php?success=" . urlencode("Medication dispensed successfully! Amount: $amount_display")
            : "../views/pharmacy/dashboard.php?success=" . urlencode("Medication dispensed successfully! Amount: $amount_display");
        header("Location: " . $redirect_url);
        exit();

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Dispense medication PDO error: " . $e->getMessage());
        header("Location: ../views/pharmacy/dashboard.php?error=" . urlencode('Database error occurred'));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Dispense medication error: " . $e->getMessage());
        header("Location: ../views/pharmacy/dashboard.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: ../views/pharmacy/dashboard.php");
exit();
?>