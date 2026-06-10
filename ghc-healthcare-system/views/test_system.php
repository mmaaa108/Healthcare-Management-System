<?php
/**
 * سكربت اختبار النظام - تحقق من عمل كل شيء قبل المناقشة
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>🧪 اختبار نظام GHC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background: #f0f8f5; padding: 30px; }
        .test-card { margin-bottom: 15px; }
        .test-pass { border-left: 5px solid #28a745; }
        .test-fail { border-left: 5px solid #dc3545; }
        .test-warn { border-left: 5px solid #ffc107; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4">🧪 اختبار شامل لنظام GHC</h1>

<?php
$results = ['pass' => 0, 'fail' => 0, 'warn' => 0];

function test($name, $condition, $message = '') {
    global $results;
    if ($condition) {
        $results['pass']++;
        echo "<div class='card test-card test-pass'><div class='card-body'>";
        echo "✅ <strong>$name</strong>";
        if ($message) echo " - $message";
        echo "</div></div>";
    } else {
        $results['fail']++;
        echo "<div class='card test-card test-fail'><div class='card-body'>";
        echo "❌ <strong>$name</strong>";
        if ($message) echo " - $message";
        echo "</div></div>";
    }
}

function warn($name, $message) {
    global $results;
    $results['warn']++;
    echo "<div class='card test-card test-warn'><div class='card-body'>";
    echo "⚠️ <strong>$name</strong> - $message";
    echo "</div></div>";
}

// 1. اختبار الملفات الأساسية
$required_files = [
    'config/database.php',
    'config/ai_config.php',
    'includes/ai_service.php',
    'includes/functions.php',
    'includes/language.php',
    'includes/session.php',
    'models/Queue.php',
    'models/Patient.php',
    'models/Doctor.php',
    'models/Pharmacist.php',
    'models/Prescription.php',
    'models/User.php',
    'controllers/auth_handler.php',
    'controllers/patient_handler.php',
    'controllers/pharmacy_controller.php',
    'controllers/inventory_handler.php',
    'controllers/doctor_controller.php',
    'views/index.php',
    'views/login.php',
    'views/register.php',
    'translations/ar.json',
    'translations/en.json'
];

echo "<h3>📁 اختبار الملفات</h3>";
foreach ($required_files as $file) {
    test("ملف: $file", file_exists(__DIR__ . '/' . $file));
}

// 2. اختبار قاعدة البيانات
echo "<h3>🗄️ اختبار قاعدة البيانات</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    $db_obj = new Database();
    $db = $db_obj->getConnection();
    test("الاتصال بقاعدة البيانات", $db !== null);
    
    if ($db) {
        $tables = ['users', 'patients', 'doctors', 'pharmacists', 'medications', 
                   'prescriptions', 'payments', 'dispensed_medications'];
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            test("جدول: $table", $stmt->rowCount() > 0);
        }
        
        // اختبار الـ Triggers
        $stmt = $db->query("SHOW TRIGGERS");
        $triggers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        test("Trigger: after_medication_dispensed", in_array('after_medication_dispensed', $triggers));
        test("Trigger: before_medication_consumption", in_array('before_medication_consumption', $triggers));
        
        // اختبار الـ Views
        $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
        test("View: v_patient_queue", in_array('v_patient_queue', $views));
        test("View: v_pending_prescriptions", in_array('v_pending_prescriptions', $views));
    }
} catch (Exception $e) {
    test("الاتصال بقاعدة البيانات", false, $e->getMessage());
}

// 3. اختبار Queue Model
echo "<h3>❤️ اختبار Queue Model</h3>";
if (file_exists(__DIR__ . '/models/Queue.php')) {
    require_once __DIR__ . '/models/Queue.php';
    $queue = new Queue($db);
    test("دالة: hasActiveAppointment", method_exists($queue, 'hasActiveAppointment'));
    test("دالة: getPatientStatus", method_exists($queue, 'getPatientStatus'));
    test("دالة: getPatientPosition", method_exists($queue, 'getPatientPosition'));
    test("دالة: getEstimatedWaitingTime", method_exists($queue, 'getEstimatedWaitingTime'));
    test("دالة: getAllWaitingPatients", method_exists($queue, 'getAllWaitingPatients'));
    test("دالة: getNextPatient", method_exists($queue, 'getNextPatient'));
    test("دالة: markAsCompleted", method_exists($queue, 'markAsCompleted'));
}

// 4. اختبار ملفات الترجمة
echo "<h3>🌐 اختبار ملفات الترجمة</h3>";
foreach (['ar.json', 'en.json'] as $json_file) {
    $path = __DIR__ . '/translations/' . $json_file;
    if (file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
        test("ملف JSON صالح: $json_file", $data !== null);
        
        if ($data) {
            $first_key = array_key_first($data);
            $has_space = substr($first_key, -1) === ' ';
            test("بدون مسافات زائدة: $json_file", !$has_space, 
                 $has_space ? "يوجد مسافة في نهاية المفاتيح!" : "نظيف");
        }
    }
}

// 5. اختبار امتداد PHP
echo "<h3>⚙️ اختبار إعدادات PHP</h3>";
test("PHP cURL", extension_loaded('curl'));
test("PHP PDO", extension_loaded('pdo'));
test("PHP PDO MySQL", extension_loaded('pdo_mysql'));
test("PHP JSON", extension_loaded('json'));
test("PHP Version >= 8.0", version_compare(PHP_VERSION, '8.0.0', '>='));

// 6. اختبار ai_config.php
echo "<h3>🤖 اختبار إعدادات Gemini API</h3>";
if (file_exists(__DIR__ . '/config/ai_config.php')) {
    require_once __DIR__ . '/config/ai_config.php';
    $key_ok = defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE' && !empty(GEMINI_API_KEY);
    if ($key_ok) {
        test("مفتاح Gemini API مُعد", true);
    } else {
        warn("مفتاح Gemini API", "الرجاء وضع مفتاحك الحقيقي في config/ai_config.php (اختياري للتشغيل الأساسي)");
    }
}

// النتيجة النهائية
echo "<hr><h2 class='mt-4'>📊 النتيجة النهائية</h2>";
$total = $results['pass'] + $results['fail'] + $results['warn'];
$percent = $total > 0 ? round(($results['pass'] / $total) * 100) : 0;

echo "<div class='alert alert-";
echo $results['fail'] === 0 ? 'success' : 'danger';
echo "'>";
echo "<h4>✅ نجح: {$results['pass']} | ❌ فشل: {$results['fail']} | ⚠️ تحذير: {$results['warn']}</h4>";
echo "<h5>نسبة النجاح: $percent%</h5>";
if ($results['fail'] === 0) {
    echo "<h5 class='mt-3'>🎉 النظام جاهز للعمل!</h5>";
} else {
    echo "<h5 class='mt-3'>⚠️ يوجد أخطاء يجب إصلاحها قبل التشغيل</h5>";
}
echo "</div>";

echo "<a href='views/index.php' class='btn btn-primary btn-lg'>🚀 الذهاب للصفحة الرئيسية</a>";
echo " <a href='test_system.php' class='btn btn-secondary btn-lg'>🔄 إعادة الاختبار</a>";
?>
</div>
</body>
</html>