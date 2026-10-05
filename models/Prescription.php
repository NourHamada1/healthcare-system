<?php
class Prescription {
    private $conn;
    private $table_name = "prescriptions";

    public $id;
    public $patient_id;
    public $doctor_id;
    public $medication;
    public $notes;
    public $consultation_duration;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET patient_id=:patient_id,
                    doctor_id=:doctor_id,
                    medication=:medication,
                    notes=:notes,
                    consultation_duration=:consultation_duration,
                    status='pending'";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":patient_id", $this->patient_id);
        $stmt->bindParam(":doctor_id", $this->doctor_id);
        $stmt->bindParam(":medication", $this->medication);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":consultation_duration", $this->consultation_duration);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getPendingPrescriptions() {
        $query = "SELECT 
                    pr.id as prescription_id,
                    pr.medication,
                    pr.notes,
                    pr.consultation_duration,
                    pr.created_at,
                    p.id as patient_id,
                    p.current_conditions,
                    p.other_diseases,
                    u.full_name,
                    TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age,
                    u.gender,
                    u.national_id,
                    d.full_name as doctor_name
                FROM " . $this->table_name . " pr
                INNER JOIN patients p ON pr.patient_id = p.id
                INNER JOIN users u ON p.user_id = u.id
                INNER JOIN users d ON pr.doctor_id = d.id
                WHERE pr.status = 'pending'
                ORDER BY pr.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}

?>