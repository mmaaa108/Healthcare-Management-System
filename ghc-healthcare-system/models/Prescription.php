<?php
/**
 * Prescription Model
 * Handles prescription creation and management
 */
class Prescription {
    private $conn;
    private $table_name = "prescriptions";
    
    public $id;
    public $patient_id;
    public $doctor_id;
    public $medication;
    public $notes;
    public $consultation_duration;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new prescription
     * @return bool Success status
     */
    public function create() {
        try {
            if (empty($this->patient_id) || empty($this->doctor_id) || empty($this->medication)) {
                error_log("Prescription create failed: Missing required fields");
                return false;
            }

            $query = "INSERT INTO " . $this->table_name . " 
                      (patient_id, doctor_id, medication, notes, consultation_duration, created_at) 
                      VALUES 
                      (:patient_id, :doctor_id, :medication, :notes, :consultation_duration, CURRENT_TIMESTAMP)";
            
            $stmt = $this->conn->prepare($query);
            
            $this->medication = htmlspecialchars(strip_tags($this->medication));
            $this->notes = htmlspecialchars(strip_tags($this->notes));
            
            $stmt->bindParam(":patient_id", $this->patient_id, PDO::PARAM_INT);
            $stmt->bindParam(":doctor_id", $this->doctor_id, PDO::PARAM_INT);
            $stmt->bindParam(":medication", $this->medication);
            $stmt->bindParam(":notes", $this->notes);
            $stmt->bindParam(":consultation_duration", $this->consultation_duration, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                error_log("Prescription created successfully. ID: " . $this->id);
                return true;
            }
            
            error_log("Prescription create failed: " . implode(", ", $stmt->errorInfo()));
            return false;
        } catch (PDOException $e) {
            error_log("Prescription create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get prescription by ID
     */
    public function getById($id) {
        try {
            $query = "SELECT 
                        pr.*,
                        u.full_name as doctor_name,
                        p.current_conditions
                      FROM " . $this->table_name . " pr
                      JOIN users u ON pr.doctor_id = u.id
                      JOIN patients p ON pr.patient_id = p.id
                      WHERE pr.id = :id
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get prescription error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all prescriptions for a patient
     */
    public function getByPatient($patient_id) {
        try {
            $query = "SELECT 
                        pr.*,
                        u.full_name as doctor_name
                      FROM " . $this->table_name . " pr
                      JOIN users u ON pr.doctor_id = u.id
                      WHERE pr.patient_id = :patient_id
                      ORDER BY pr.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":patient_id", $patient_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get patient prescriptions error: " . $e->getMessage());
            return [];
        }
    }

          /**
     * Get prescriptions for pharmacy (pending to be prepared)
     * @return array List of pending prescriptions with full patient and doctor details
     */
    public function getPendingForPharmacy() {
        try {
            $query = "SELECT 
                        pr.id as prescription_id,
                        pr.patient_id,
                        pr.doctor_id,
                        pr.medication,
                        pr.notes,
                        pr.status,
                        pr.created_at,
                        u.full_name as patient_name,
                        u.national_id as patient_national_id,
                        u.age as patient_age,
                        u.gender as patient_gender,
                        d.full_name as doctor_name,
                        p.current_conditions,
                        p.is_pregnant,
                        p.is_chronic
                      FROM " . $this->table_name . " pr
                      JOIN patients p ON pr.patient_id = p.id
                      JOIN users u ON p.user_id = u.id
                      JOIN users d ON pr.doctor_id = d.id
                      WHERE pr.status = 'pending'
                      ORDER BY pr.created_at ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get pending prescriptions error: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Mark prescription as dispensed
     */
    public function markAsDispensed($id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET status = 'dispensed' 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Mark as dispensed error: " . $e->getMessage());
            return false;
        }
    }
}
?>