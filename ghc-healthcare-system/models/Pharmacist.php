<?php
class Pharmacist {
    private $conn;
    private $table_name = "pharmacists";

    public $id;
    public $user_id;
    public $years_experience;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get pharmacist by user ID
    public function getByUserId($user_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Pharmacist getByUserId error: " . $e->getMessage());
            return false;
        }
    }

    // Get all pharmacists
    public function getAll() {
        try {
            $query = "SELECT p.*, u.full_name, u.email 
                     FROM " . $this->table_name . " p 
                     JOIN users u ON p.user_id = u.id 
                     ORDER BY u.full_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Pharmacist getAll error: " . $e->getMessage());
            return [];
        }
    }
}
?>