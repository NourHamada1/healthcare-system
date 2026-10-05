<?php
class Doctor {
    private $conn;
    private $table_name = "doctors";

    public $id;
    public $user_id;
    public $specialization;
    public $years_experience;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getDoctorInfo($user_id) {
        $query = "SELECT d.*, u.full_name 
                  FROM " . $this->table_name . " d
                  JOIN users u ON d.user_id = u.id
                  WHERE d.user_id = :user_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllDoctors() {
        $query = "SELECT d.id, d.specialization, u.full_name 
                  FROM " . $this->table_name . " d
                  JOIN users u ON d.user_id = u.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
