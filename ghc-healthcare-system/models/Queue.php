<?php
/**
 * Queue Model - القلب النابض للنظام
 * يدير طابور المرضى باستخدام جدول patients
 */
class Queue {
    private $conn;
    private $table_name = "patients";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * جلب كل المرضى المنتظرين (بدون فلتر)
     */
    public function getAllWaitingPatients() {
        try {
            $query = "SELECT
                p.id, p.user_id, p.priority, p.status,
                p.current_conditions, p.other_diseases,
                p.is_chronic, p.is_pregnant, p.is_critical,
                p.suggested_department, p.suggested_doctor,
                p.created_at,
                u.full_name, u.national_id, u.age, u.gender
            FROM " . $this->table_name . " p
            JOIN users u ON p.user_id = u.id
            WHERE p.status IN ('waiting', 'in_consultation')
            ORDER BY p.priority ASC, p.created_at ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllWaitingPatients error: " . $e->getMessage());
            return [];
        }
    }
/**
 * ✅ جلب المرضى الموجهين لطبيب محدد - النسخة الذكية
 * تحاول المطابقة بكل الأشكال الممكنة (عربي/إنجليزي/مختصر)
 */
public function getAllWaitingPatientsByDoctor($doctor_name, $doctor_specialization) {
    try {
        // تنظيف الأسماء
        $clean_doctor_name = preg_replace('/^(Dr\.|د\.|Doctor)\s*/i', '', trim($doctor_name));
        $clean_specialization = trim($doctor_specialization);
        
        // ✅ قاموس الترجمة (نفس الموجود في functions.php)
        $dept_variants = [];
        
        // إضافة التخصص الأصلي
        $dept_variants[] = $clean_specialization;
        $dept_variants[] = strtolower($clean_specialization);
        
        // إضافة الأسماء العربية المحتملة
        $arabic_map = [
            'Cardiology' => ['طب القلب', 'القلب'],
            'Neurology' => ['طب الأعصاب', 'الأعصاب'],
            'Pediatrics' => ['طب الأطفال', 'الأطفال'],
            'General Practice' => ['الممارسة العامة', 'عام'],
            'Internal Medicine' => ['الطب الباطني', 'باطني'],
            'Dermatology' => ['الأمراض الجلدية', 'الجلدية'],
            'Gynecology' => ['النساء والتوليد', 'نساء'],
            'Orthopedics' => ['جراحة العظام', 'العظام'],
            'Ophthalmology' => ['طب العيون', 'العيون'],
            'ENT' => ['الأنف والأذن والحنجرة'],
            'Urology' => ['المسالك البولية'],
            'Psychiatry' => ['الطب النفسي'],
            'Dentistry' => ['طب الأسنان'],
            'Emergency Medicine' => ['طب الطوارئ', 'طوارئ'],
            'Gastroenterology' => ['الجهاز الهضمي'],
            'Pulmonology' => ['أمراض الصدر', 'الصدر'],
            'Oncology' => ['الأورام'],
            'Surgery' => ['الجراحة العامة', 'جراحة'],
        ];
        
        if (isset($arabic_map[$clean_specialization])) {
            foreach ($arabic_map[$clean_specialization] as $arabic_name) {
                $dept_variants[] = $arabic_name;
            }
        }
        
        // بناء شرط OR ديناميكي لكل الاحتمالات
        $dept_conditions = [];
        $params = [];
        
        foreach (array_unique($dept_variants) as $i => $variant) {
            $param_name = ":dept_{$i}";
            $dept_conditions[] = "LOWER(TRIM(p.suggested_department)) = LOWER({$param_name})";
            $params[$param_name] = $variant;
        }
        
        $dept_where = implode(' OR ', $dept_conditions);
        
        $query = "SELECT
            p.id, p.user_id, p.priority, p.status,
            p.current_conditions, p.other_diseases,
            p.is_chronic, p.is_pregnant, p.is_critical,
            p.suggested_department, p.suggested_doctor,
            p.created_at,
            u.full_name, u.national_id, u.age, u.gender
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.status IN ('waiting', 'in_consultation')
        AND (
            -- مطابقة اسم الطبيب (مرنة)
            REPLACE(REPLACE(REPLACE(LOWER(TRIM(p.suggested_doctor)), 'dr. ', ''), 'د. ', ''), 'doctor ', '') = LOWER(:clean_doctor_name)
            OR LOWER(TRIM(p.suggested_doctor)) = LOWER(:doctor_name)
            OR LOWER(TRIM(p.suggested_doctor)) LIKE LOWER(CONCAT('%', :doctor_name_part, '%'))
            
            -- أو مطابقة التخصص بكل الأشكال الممكنة
            OR ({$dept_where})
            
            -- أو غير موجه
            OR p.suggested_doctor IS NULL
            OR p.suggested_doctor = ''
            OR p.suggested_doctor = 'Pending Assignment'
            OR LOWER(TRIM(p.suggested_department)) = 'general practice'
        )
        ORDER BY p.priority ASC, p.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":doctor_name", $doctor_name);
        $stmt->bindParam(":clean_doctor_name", $clean_doctor_name);
        $doctor_name_part = $clean_doctor_name;
        $stmt->bindParam(":doctor_name_part", $doctor_name_part);
        
        foreach ($params as $param_name => $value) {
            $stmt->bindValue($param_name, $value);
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ✅ إذا لم يُعثر على مرضى، أعد المرضى غير الموجهين (General Practice)
        if (empty($results)) {
            return $this->getUnassignedPatients();
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("getAllWaitingPatientsByDoctor error: " . $e->getMessage());
        return $this->getAllWaitingPatients();
    }
}

/**
 * ✅ جلب المرضى غير الموجهين (يظهرون لكل الأطباء)
 */
private function getUnassignedPatients() {
    try {
        $query = "SELECT
            p.id, p.user_id, p.priority, p.status,
            p.current_conditions, p.other_diseases,
            p.is_chronic, p.is_pregnant, p.is_critical,
            p.suggested_department, p.suggested_doctor,
            p.created_at,
            u.full_name, u.national_id, u.age, u.gender
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.status IN ('waiting', 'in_consultation')
        AND (
            p.suggested_doctor IS NULL
            OR p.suggested_doctor = ''
            OR p.suggested_doctor = 'Pending Assignment'
            OR LOWER(TRIM(p.suggested_department)) = 'general practice'
        )
        ORDER BY p.priority ASC, p.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getUnassignedPatients error: " . $e->getMessage());
        return [];
    }
}
    /**
     * جلب المريض التالي في الطابور
     */
    public function getNextPatient() {
        try {
            $query = "SELECT
                p.id, p.user_id, p.priority,
                p.current_conditions, p.other_diseases,
                p.is_chronic, p.is_pregnant, p.is_critical,
                p.suggested_department, p.suggested_doctor,
                u.full_name, u.national_id, u.age, u.gender
            FROM " . $this->table_name . " p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'waiting'
            ORDER BY p.priority ASC, p.created_at ASC
            LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getNextPatient error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * نقل المريض إلى حالة "في الكشف"
     */
    public function moveToConsultation($patient_id) {
        try {
            $query = "UPDATE " . $this->table_name . "
                      SET status = 'in_consultation',
                          updated_at = CURRENT_TIMESTAMP
                      WHERE id = :patient_id
                        AND status = 'waiting'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":patient_id", $patient_id, PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("moveToConsultation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إنهاء الكشف (بعد إنشاء الوصفة)
     */
    public function markAsCompleted($patient_id) {
        try {
            $query = "UPDATE " . $this->table_name . "
                      SET status = 'completed',
                          updated_at = CURRENT_TIMESTAMP
                      WHERE id = :patient_id
                        AND status IN ('waiting', 'in_consultation')";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":patient_id", $patient_id, PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("markAsCompleted error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إحصائيات الطابور
     */
    public function getQueueStatistics() {
        try {
            $query = "SELECT
                COUNT(*) as total_waiting,
                SUM(CASE WHEN priority = 1 THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN priority <= 3 THEN 1 ELSE 0 END) as high_priority_count,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, CURRENT_TIMESTAMP)) as avg_wait_time
            FROM " . $this->table_name . "
            WHERE status = 'waiting'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [
                'total_waiting' => 0, 'critical_count' => 0,
                'high_priority_count' => 0, 'avg_wait_time' => 0
            ];
        } catch (PDOException $e) {
            error_log("getQueueStatistics error: " . $e->getMessage());
            return ['total_waiting' => 0, 'critical_count' => 0, 'high_priority_count' => 0, 'avg_wait_time' => 0];
        }
    }

    /**
     * ✅ التحقق من وجود موعد نشط للمريض
     * (مطلوبة في patient_handler.php)
     */
    public function hasActiveAppointment($user_id) {
        try {
            $query = "SELECT COUNT(*) as count 
                      FROM " . $this->table_name . " 
                      WHERE user_id = :user_id 
                        AND status IN ('waiting', 'in_consultation')";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("hasActiveAppointment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ جلب حالة المريض الحالية
     * (مطلوبة في dashboard.php و waiting.php)
     */
    public function getPatientStatus($user_id) {
        try {
            $query = "SELECT status, priority, suggested_department, suggested_doctor, created_at
                      FROM " . $this->table_name . "
                      WHERE user_id = :user_id
                      ORDER BY created_at DESC
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPatientStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ حساب موقع المريض في الطابور
     * (مطلوبة في waiting.php)
     */
    public function getPatientPosition($user_id) {
        try {
            $query = "SELECT COUNT(*) as position
                      FROM " . $this->table_name . " p1
                      JOIN " . $this->table_name . " p2 
                        ON p2.user_id = :user_id 
                       AND p2.status IN ('waiting', 'in_consultation')
                      WHERE p1.status IN ('waiting', 'in_consultation')
                        AND (p1.priority < p2.priority 
                             OR (p1.priority = p2.priority 
                                 AND p1.created_at <= p2.created_at))";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return max(0, (int)$result['position'] - 1);
        } catch (PDOException $e) {
            error_log("getPatientPosition error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ حساب وقت الانتظار المقدر (15 دقيقة لكل مريض أمامك)
     * (مطلوبة في waiting.php)
     */
    public function getEstimatedWaitingTime($user_id) {
        $position = $this->getPatientPosition($user_id);
        return $position * 15;
    }

    /**
     * إضافة مريض إلى الطابور
     */
    public function addToQueue($patient_id, $priority = 6) {
        try {
            $query = "UPDATE " . $this->table_name . "
                      SET status = 'waiting', priority = :priority
                      WHERE id = :patient_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":patient_id", $patient_id, PDO::PARAM_INT);
            $stmt->bindParam(":priority", $priority, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("addToQueue error: " . $e->getMessage());
            return false;
        }
    }
}
?>