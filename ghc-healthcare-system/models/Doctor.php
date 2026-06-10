<?php
class Doctor {
    private $conn;
    private $table_name = "doctors";

    public $id;
    public $user_id;
    public $specialization;
    public $years_experience;
    public $description;
    public $is_approved;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getDoctorInfo($user_id) {
        $query = "SELECT d.*, u.full_name, u.age, u.gender, u.national_id
                  FROM " . $this->table_name . " d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.user_id = :user_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllDoctors() {
        $query = "SELECT d.id, d.specialization, d.years_experience, u.full_name, u.id as user_id
                  FROM " . $this->table_name . " d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.is_approved = 1
                  ORDER BY d.specialization ASC, u.full_name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDoctorsBySpecialization($specialization) {
        $query = "SELECT d.id, d.specialization, d.years_experience, u.full_name, u.id as user_id
                  FROM " . $this->table_name . " d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.specialization LIKE :specialization
                  AND d.is_approved = 1
                  ORDER BY d.years_experience DESC";

        $stmt = $this->conn->prepare($query);
        $specialization = "%$specialization%";
        $stmt->bindParam(":specialization", $specialization);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id,
                    specialization=:specialization,
                    years_experience=:years_experience,
                    is_approved=:is_approved";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":specialization", $this->specialization);
        $stmt->bindParam(":years_experience", $this->years_experience);
        $stmt->bindParam(":is_approved", $this->is_approved);

        return $stmt->execute();
    }
}
?>