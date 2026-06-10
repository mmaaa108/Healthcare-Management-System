<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../views/login.php?type=pharmacist");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// =================================================================
// إضافة دواء جديد
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'add_medication') {
    $name = htmlspecialchars(strip_tags($_POST['name']));
    $category = htmlspecialchars(strip_tags($_POST['category']));
    $quantity = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $description = htmlspecialchars(strip_tags($_POST['description'] ?? ''));

    $check_query = "SELECT COUNT(*) as count FROM medications WHERE name = :name";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':name', $name);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        header("Location: ../views/pharmacy/inventory.php?error=" . urlencode("Medication already exists! Please update quantity instead."));
        exit();
    }

    $query = "INSERT INTO medications (name, category, description, quantity_in_stock, unit_price, created_at)
              VALUES (:name, :category, :description, :quantity, :unit_price, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':quantity', $quantity);
    $stmt->bindParam(':unit_price', $unit_price);
    
    if ($stmt->execute()) {
        header("Location: ../views/pharmacy/inventory.php?success=" . urlencode("✅ Medication added successfully!"));
    } else {
        header("Location: ../views/pharmacy/inventory.php?error=" . urlencode("❌ Failed to add medication."));
    }
    exit();
}

// =================================================================
// تحديث دواء
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_medication') {
    $id = intval($_POST['medication_id']);
    $name = htmlspecialchars(strip_tags($_POST['name']));
    $category = htmlspecialchars(strip_tags($_POST['category']));
    $quantity = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $description = htmlspecialchars(strip_tags($_POST['description'] ?? ''));

    $check_query = "SELECT COUNT(*) as count FROM medications WHERE name = :name AND id != :id";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        header("Location: ../views/pharmacy/inventory.php?error=" . urlencode("Medication name already exists!"));
        exit();
    }

    $query = "UPDATE medications SET
              name = :name, category = :category, description = :description,
              quantity_in_stock = :quantity, unit_price = :unit_price, updated_at = NOW()
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':quantity', $quantity);
    $stmt->bindParam(':unit_price', $unit_price);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        header("Location: ../views/pharmacy/inventory.php?success=" . urlencode("✅ Medication updated successfully!"));
    } else {
        header("Location: ../views/pharmacy/inventory.php?error=" . urlencode("❌ Failed to update medication."));
    }
    exit();
}

// =================================================================
// حذف دواء
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_medication') {
    $id = intval($_GET['id']);
    $query = "DELETE FROM medications WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        header("Location: ../views/pharmacy/inventory.php?success=" . urlencode("✅ Medication deleted successfully!"));
    } else {
        header("Location: ../views/pharmacy/inventory.php?error=" . urlencode("❌ Failed to delete medication."));
    }
    exit();
}

// =================================================================
// استهلاك دواء (مع تفعيل Trigger التحقق من المخزون)
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'consume_medication') {
    $id = intval($_POST['medication_id']);
    $consume_quantity = intval($_POST['consume_quantity']);
    $pharmacist_id = $_SESSION['user_id'];

    $query = "SELECT name, quantity_in_stock FROM medications WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $med = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($med) {
        try {
            $db->beginTransaction();

            // 1. إدراج سجل في جدول الاستهلاك لتفعيل Trigger before_medication_consumption
            // إذا كان المخزون غير كافٍ، سيرمي الـ Trigger خطأ SQLSTATE 45000
            $log_query = "INSERT INTO medication_consumption_log 
                          (medication_id, quantity_consumed, consumed_by, created_at) 
                          VALUES (:medication_id, :quantity_consumed, :consumed_by, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':medication_id', $id);
            $log_stmt->bindParam(':quantity_consumed', $consume_quantity);
            $log_stmt->bindParam(':consumed_by', $pharmacist_id);
            $log_stmt->execute();

            // 2. تحديث الكمية (الـ Trigger يتحقق فقط، لا يُحدّث)
            $new_quantity = $med['quantity_in_stock'] - $consume_quantity;
            $update_query = "UPDATE medications SET quantity_in_stock = :quantity WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':quantity', $new_quantity);
            $update_stmt->bindParam(':id', $id);
            $update_stmt->execute();

            $db->commit();

            $message = "✅ Consumed $consume_quantity units of {$med['name']}. Remaining: $new_quantity";
            if ($new_quantity === 0) {
                $message .= " ⚠️ OUT OF STOCK!";
            } elseif ($new_quantity < 50) {
                $message .= " ⚠️ LOW STOCK!";
            }
            header("Location: ../views/pharmacy/inventory.php?success=" . urlencode($message));
        } catch (PDOException $e) {
            $db->rollBack();
            header("Location: ../views/pharmacy/inventory.php?error=" . urlencode("❌ " . $e->getMessage()));
        }
    }
    exit();
}

header("Location: ../views/pharmacy/inventory.php");
exit();
?>