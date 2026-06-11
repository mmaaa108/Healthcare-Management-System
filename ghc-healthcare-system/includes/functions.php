<?php
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function calculatePriority($patient) {
    if ($patient['is_critical'] == 1) return 1;
    elseif ($patient['is_pregnant'] == 1) return 2;
    elseif ($patient['age'] >= 60 && $patient['is_chronic'] == 1) return 3;
    elseif ($patient['is_chronic'] == 1) return 4;
    elseif ($patient['age'] < 18) return 5;
    return 6;
}

require_once __DIR__ . '/ai_service.php';
require_once __DIR__ . '/../models/Doctor.php';

/**
 * ✅ قاموس الترجمة الشامل (عربي ↔ إنجليزي)
 * يحتوي على كل الأسماء الممكنة للأقسام الطبية
 */
function getDepartmentDictionary() {
    return [
        // English => [Arabic variants]
        'Cardiology' => ['طب القلب', 'القلب', 'cardiology', 'cardio'],
        'Neurology' => ['طب الأعصاب', 'الأعصاب', 'neurology', 'neuro'],
        'Orthopedics' => ['جراحة العظام', 'العظام', 'orthopedics', 'orthopedic'],
        'Pediatrics' => ['طب الأطفال', 'الأطفال', 'pediatrics', 'pediatric'],
        'Dermatology' => ['الأمراض الجلدية', 'الجلدية', 'الجلد', 'dermatology'],
        'Ophthalmology' => ['طب العيون', 'العيون', 'ophthalmology'],
        'ENT' => ['الأنف والأذن والحنجرة', 'أذن', 'أنف', 'حنجرة', 'ent'],
        'General Practice' => ['الممارسة العامة', 'عام', 'general practice', 'general'],
        'Internal Medicine' => ['الطب الباطني', 'باطني', 'internal medicine', 'internal'],
        'Gynecology' => ['النساء والتوليد', 'نساء', 'توليد', 'gynecology', 'gynecological'],
        'Urology' => ['المسالك البولية', 'مسالك', 'urology'],
        'Psychiatry' => ['الطب النفسي', 'نفسي', 'psychiatry'],
        'Dentistry' => ['طب الأسنان', 'الأسنان', 'dentistry', 'dental'],
        'Emergency Medicine' => ['طب الطوارئ', 'طوارئ', 'emergency'],
        'Gastroenterology' => ['الجهاز الهضمي', 'هضمي', 'gastroenterology'],
        'Endocrinology' => ['الغدد الصماء', 'الغدد', 'endocrinology'],
        'Pulmonology' => ['أمراض الصدر', 'الصدر', 'pulmonology', 'respiratory'],
        'Oncology' => ['الأورام', 'oncology'],
        'Rheumatology' => ['الروماتيزم', 'rheumatology'],
        'Surgery' => ['الجراحة العامة', 'جراحة', 'surgery', 'surgical'],
    ];
}

/**
 * ✅ تحويل أي اسم قسم (عربي أو إنجليزي) إلى الإنجليزية الموحدة
 */
function normalizeDepartment($department_name) {
    if (empty($department_name)) return 'General Practice';
    
    $department_name = trim($department_name);
    $dictionary = getDepartmentDictionary();
    
    // إذا كان موجوداً كمفتاح (إنجليزي)
    if (isset($dictionary[$department_name])) {
        return $department_name;
    }
    
    // البحث في القيم العربية
    $lower_input = strtolower($department_name);
    foreach ($dictionary as $english => $arabic_variants) {
        if (strtolower($english) === $lower_input) {
            return $english;
        }
        foreach ($arabic_variants as $variant) {
            if (strtolower($variant) === $lower_input) {
                return $english;
            }
            // مطابقة جزئية (إذا كان الاسم يحتوي على الكلمة)
            if (strpos($lower_input, strtolower($variant)) !== false) {
                return $english;
            }
        }
    }
    
    // إذا لم يُعثر على مطابقة، أعد الاسم كما هو
    return $department_name;
}

/**
 * ✅ ترجمة القسم من الإنجليزية إلى لغة المستخدم للعرض
 */
function translateDepartment($department, $target_lang = null) {
    if ($target_lang === null) {
        $target_lang = $_SESSION['lang'] ?? 'ar';
    }
    
    if ($target_lang === 'en') return $department;
    
    $dictionary = getDepartmentDictionary();
    if (isset($dictionary[$department])) {
        return $dictionary[$department][0]; // أول قيمة عربية
    }
    return $department;
}

function getCurrentLanguage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['lang'] ?? 'ar';
}

/**
 * ✅ توجيه المريض - مع إجبار صارم على الإنجليزية + أمثلة
 */
function getAISuggestion($db, $symptoms, $conditions) {
    try {
        $doctor_model = new Doctor($db);
        $doctors = $doctor_model->getAllDoctors();
        
        $doctors_list = [];
        foreach ($doctors as $doc) {
            $doctors_list[] = "- " . $doc['full_name'] . " (Specialization: " . $doc['specialization'] . ")";
        }
        $doctors_text = implode("\n", $doctors_list);

        // ✅ Prompt صارم جداً مع أمثلة
        $system_prompt = "You are a medical triage AI. Your ONLY job is to match patient symptoms to the BEST doctor from the list below.

CRITICAL RULES:
1. The 'department' field MUST be EXACTLY one of the specializations from the list (copy it character by character).
2. The 'doctor' field MUST be EXACTLY one of the names from the list (copy it character by character).
3. DO NOT translate, DO NOT modify, DO NOT add 'Dr.' if not in the list.

EXAMPLES:
- If list has 'Ahmad Khalil' with 'Cardiology', return: {\"department\": \"Cardiology\", \"doctor\": \"Ahmad Khalil\"}
- If list has 'Dr. Sara Ali' with 'Pediatrics', return: {\"department\": \"Pediatrics\", \"doctor\": \"Dr. Sara Ali\"}

Return ONLY a valid JSON object:
{
  \"department\": \"<EXACT specialization from list>\",
  \"doctor\": \"<EXACT name from list>\"
}

If no match, use: {\"department\": \"General Practice\", \"doctor\": \"Pending Assignment\"}";

        $user_prompt = "Patient Symptoms: " . $symptoms . "\nOther Conditions: " . $conditions . "\n\nAvailable Doctors (use EXACT names):\n" . $doctors_text;

        $response = AIService::callOpenAI($system_prompt, $user_prompt, true);

        if ($response && isset($response['department']) && isset($response['doctor'])) {
            // ✅ تطبيع القسم إلى الإنجليزية الموحدة
            $normalized_dept = normalizeDepartment($response['department']);
            
            return [
                'department' => $normalized_dept,
                'doctor' => sanitizeInput($response['doctor'])
            ];
        }
    } catch (Exception $e) {
        error_log("AI Suggestion Error: " . $e->getMessage());
    }
    
    return [
        'department' => 'General Practice',
        'doctor' => 'Pending Assignment'
    ];
}

/**
 * ✅ مساعدة الطبيب
 */
function getMedicationSuggestion($diagnosis, $patient_info = []) {
    try {
        $lang = getCurrentLanguage();
        $lang_instruction = ($lang === 'ar') 
            ? "You MUST respond in ARABIC language." 
            : "You MUST respond in ENGLISH language.";

        $system_prompt = "You are an expert Clinical Pharmacology AI assistant. Based on the diagnosis/symptoms, suggest the most appropriate primary medication with exact dosage and frequency. 
CRITICAL: Consider the patient's age, gender, pregnancy status, and chronic conditions to avoid contraindications.

{$lang_instruction}

Return ONLY a valid JSON object with two keys: 
1) 'primary_medication' (string)
2) 'considerations' (string)";

        $user_prompt = "Diagnosis/Symptoms: " . $diagnosis . "\n";
        $user_prompt .= "Patient Age: " . ($patient_info['age'] ?? 'Unknown') . "\n";
        $user_prompt .= "Patient Gender: " . ($patient_info['gender'] ?? 'Unknown') . "\n";
        
        if (!empty($patient_info['is_pregnant'])) $user_prompt .= "⚠️ Condition: PREGNANT\n";
        if (!empty($patient_info['is_chronic'])) $user_prompt .= "⚠️ Condition: CHRONIC DISEASES PRESENT\n";
        
        $user_prompt .= "Other Medical History: " . ($patient_info['other_diseases'] ?? 'None identified');

        $response = AIService::callOpenAI($system_prompt, $user_prompt, true);

        if ($response && isset($response['primary_medication'])) {
            return [
                'primary_medication' => sanitizeInput($response['primary_medication']),
                'considerations' => sanitizeInput($response['considerations'] ?? ($lang === 'ar' ? 'لا توجد ملاحظات خاصة.' : 'No specific considerations.'))
            ];
        }
    } catch (Throwable $e) {
        error_log("AI Medication Suggestion Error: " . $e->getMessage());
    }

    // Fallback
    $lang = getCurrentLanguage();
    $diagnosis_lower = strtolower($diagnosis);
    $is_pregnant = !empty($patient_info['is_pregnant']);
    
    if ($lang === 'ar') {
        if (strpos($diagnosis_lower, 'ألم') !== false || strpos($diagnosis_lower, 'حمى') !== false || 
            strpos($diagnosis_lower, 'pain') !== false || strpos($diagnosis_lower, 'fever') !== false) {
            $med = $is_pregnant ? 'باراسيتامول 500 ملغ (آمن أثناء الحمل)' : 'باراسيتامول 500 ملغ أو إيبوبروفين 200 ملغ عند الحاجة';
            return ['primary_medication' => $med, 'considerations' => 'راقب أي ردود فعل تحسسية.'];
        }
        return ['primary_medication' => 'استشر الإرشادات الصيدلانية.', 'considerations' => 'تحقق من حساسية المريض.'];
    }
    
    if (strpos($diagnosis_lower, 'pain') !== false || strpos($diagnosis_lower, 'fever') !== false) {
        $med = $is_pregnant ? 'Paracetamol 500mg (Safe for pregnancy)' : 'Paracetamol 500mg or Ibuprofen 200mg as needed';
        return ['primary_medication' => $med, 'considerations' => 'Monitor for allergic reactions.'];
    }
    
    return ['primary_medication' => 'Consult pharmaceutical guidelines.', 'considerations' => 'Verify patient allergies.'];
}
?>