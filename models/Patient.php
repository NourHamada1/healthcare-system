<?php
class Patient {
    private $conn;
    private $table_name = "patients";

    public $id;
    public $user_id;
    public $is_chronic;
    public $other_diseases;
    public $current_conditions;
    public $is_pregnant;
    public $is_critical;
    public $priority;
    public $suggested_department;
    public $suggested_doctor;
    public $queue_position;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id,
                    is_chronic=:is_chronic,
                    other_diseases=:other_diseases,
                    current_conditions=:current_conditions,
                    is_pregnant=:is_pregnant,
                    is_critical=:is_critical,
                    priority=:priority,
                    suggested_department=:suggested_department,
                    suggested_doctor=:suggested_doctor,
                    status='waiting'";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":is_chronic", $this->is_chronic);
        $stmt->bindParam(":other_diseases", $this->other_diseases);
        $stmt->bindParam(":current_conditions", $this->current_conditions);
        $stmt->bindParam(":is_pregnant", $this->is_pregnant);
        $stmt->bindParam(":is_critical", $this->is_critical);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":suggested_department", $this->suggested_department);
        $stmt->bindParam(":suggested_doctor", $this->suggested_doctor);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getPatientById($id) {
        $query = "SELECT p.*, u.full_name, TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age, u.gender, u.national_id 
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.user_id = u.id
                  WHERE p.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
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