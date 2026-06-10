<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_service.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$patient_id = $_POST['patient_id'] ?? 0;
$prescribed_medication = $_POST['prescribed_medication'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$patient_id || empty($prescribed_medication)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit();
}

// ✅ تحديد اللغة الحالية
$lang = $_SESSION['lang'] ?? 'ar';
$is_arabic = ($lang === 'ar');

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Get patient info
    $patient_query = "SELECT p.*, u.age, u.gender, u.full_name
                      FROM patients p
                      JOIN users u ON p.user_id = u.id
                      WHERE p.id = :patient_id";
    $stmt = $db->prepare($patient_query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get all in-stock medications
    $meds_query = "SELECT id, name, category, description FROM medications WHERE quantity_in_stock > 0";
    $stmt = $db->prepare($meds_query);
    $stmt->execute();
    $available_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($available_medications)) {
        $msg = $is_arabic 
            ? "المخزون فارغ تماماً. الدواء الموصوف '$prescribed_medication' غير متوفر ويجب شراؤه من الخارج."
            : "The warehouse is completely empty. The prescribed medication '$prescribed_medication' is not available and must be purchased externally.";
        echo json_encode([
            'success' => true,
            'medication_found' => false,
            'message' => $msg
        ]);
        exit();
    }

    $meds_list = [];
    foreach ($available_medications as $med) {
        $meds_list[] = "- ID: " . $med['id'] . " | Name: " . $med['name'] . " | Category: " . $med['category'];
    }
    $meds_text = implode("\n", $meds_list);

    // 3. ✅ بناء Prompt حسب اللغة الحالية - محسّن لاقتراح الدواء المناسب
    if ($is_arabic) {
        $system_prompt = "أنت مساعد ذكاء اصطناعي خبير في الصيدلة والسلامة الدوائية. مهمتك هي تحليل الوصفة الطبية المقدمة واقتراح الدواء المناسب من قائمة 'الأدوية المتوفرة' المرفقة.

يجب أن تأخذ بعين الاعتبار:
1. عمر المريض وجنسه
2. حالة الحمل (إن وجدت)
3. الأمراض المزمنة (إن وجدت)
4. التاريخ المرضي والحالات الأخرى
5. ملاحظات الطبيب

القواعد:
- إذا وُجد دواء مطابق أو مشابه للدواء الموصوف في القائمة، اقترحه مع شرح سبب مطابقته.
- إذا لم يوجد دواء مطابق أو مشابه آمن، أخبر الصيدلي بأن الدواء الموصوف غير متوفر ويجب شراؤه من الخارج.

يجب أن تستجيب بالعربية فقط.

أعد ONLY كائن JSON صالح بدون أي نصوص إضافية أو markdown.

إذا وُجد دواء مناسب من القائمة، أعد:
{
  \"medication_found\": true,
  \"suggested_medication_id\": <رقم صحيح من القائمة>,
  \"suggested_medication_name\": \"<اسم الدواء كما هو في القائمة>\",
  \"reasoning\": \"<شرح مفصل بالعربية لماذا هذا الدواء مناسب لهذه الوصفة وآمن لهذا المريض>\"
}

إذا لم يوجد دواء مناسب من القائمة، أعد بالضبط:
{
  \"medication_found\": false,
  \"message\": \"الدواء الموصوف '$prescribed_medication' غير متوفر في المستودع، ولا يوجد بديل مناسب وآمن له في المخزون الحالي. يجب شراؤه من صيدلية خارجية حسب الوصفة الطبية.\"
}";
    } else {
        $system_prompt = "You are a highly intelligent and safe pharmacy assistant AI. Your task is to analyze the given prescription and suggest the most appropriate medication from the provided 'In-Stock Medications' list.

You MUST consider:
1. Patient's age and gender
2. Pregnancy status (if applicable)
3. Chronic conditions (if applicable)
4. Medical history and other conditions
5. Doctor's notes

Rules:
- If a matching or similar medication to the prescribed one exists in the list, suggest it with an explanation of why it matches.
- If no matching or safe similar medication exists, inform the pharmacist that the prescribed medication is not available and must be purchased externally.

You MUST respond in ENGLISH only.

Return ONLY a valid JSON object. Do not include any markdown or extra text.

IF a suitable medication exists in the list, return:
{
  \"medication_found\": true,
  \"suggested_medication_id\": <integer ID from the list>,
  \"suggested_medication_name\": \"<string name>\",
  \"reasoning\": \"<detailed explanation in English of why this medication is appropriate for this prescription and safe for this patient>\"
}

IF NO suitable medication exists in the provided list, return EXACTLY:
{
  \"medication_found\": false,
  \"message\": \"The prescribed medication '$prescribed_medication' is not available in the warehouse, and there is no suitable and safe alternative in the current inventory. It must be purchased from an external pharmacy according to the prescription.\"
}";
    }

    $user_prompt = "Prescribed Medication: " . $prescribed_medication . "\n";
    $user_prompt .= "Doctor's Notes: " . $notes . "\n";
    $user_prompt .= "Patient Name: " . ($patient['full_name'] ?? 'Unknown') . "\n";
    $user_prompt .= "Patient Age: " . ($patient['age'] ?? 'Unknown') . "\n";
    $user_prompt .= "Patient Gender: " . ($patient['gender'] ?? 'Unknown') . "\n";
    
    if (!empty($patient['is_pregnant'])) {
        $user_prompt .= "⚠️ Condition: PREGNANT\n";
    }
    if (!empty($patient['is_chronic'])) {
        $user_prompt .= "⚠️ Condition: CHRONIC DISEASES PRESENT\n";
    }
    
    $user_prompt .= "Patient Medical History/Other Conditions: " . ($patient['other_diseases'] ?? 'None identified') . "\n";
    $user_prompt .= "In-Stock Medications List:\n" . $meds_text;

    // 4. Call AI Service
    $response = AIService::callOpenAI($system_prompt, $user_prompt, true);

    if ($response) {
        if (isset($response['medication_found']) && $response['medication_found'] === true) {
            echo json_encode([
                'success' => true,
                'medication_found' => true,
                'suggestion' => $response
            ]);
        } else {
            $default_msg = $is_arabic 
                ? "الدواء الموصوف '$prescribed_medication' غير متوفر في المستودع، ولا يوجد بديل مناسب وآمن له في المخزون الحالي. يجب شراؤه من صيدلية خارجية."
                : "The prescribed medication '$prescribed_medication' is not available in the warehouse, and there is no suitable and safe alternative in the current inventory. It must be purchased from an external pharmacy.";
            echo json_encode([
                'success' => true,
                'medication_found' => false,
                'message' => $response['message'] ?? $default_msg
            ]);
        }
    } else {
        $msg = $is_arabic 
            ? 'فشل خدمة الذكاء الاصطناعي في الاستجابة. يرجى المحاولة لاحقاً.'
            : 'AI service failed to respond. Please try again later.';
        echo json_encode(['success' => false, 'message' => $msg]);
    }

} catch (Exception $e) {
    error_log("AI Alternative Error: " . $e->getMessage());
    $msg = $is_arabic ? 'حدث خطأ في الخادم.' : 'Server error occurred.';
    echo json_encode(['success' => false, 'message' => $msg]);
}
?>