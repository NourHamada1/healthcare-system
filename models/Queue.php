<?php
class Queue {
    private $conn;
    private $patients_table = "patients";
    private $users_table = "users";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAllWaitingPatients() {
        $query = "SELECT 
                    p.id,
                    p.user_id,
                    p.priority,
                    p.is_chronic,
                    p.is_pregnant,
                    p.is_critical,
                    p.other_diseases,
                    p.current_conditions,
                    p.suggested_department,
                    p.suggested_doctor,
                    p.created_at,
                    u.full_name,
                    TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age,
                    u.gender,
                    u.national_id
                FROM " . $this->patients_table . " p
                INNER JOIN " . $this->users_table . " u ON p.user_id = u.id
                WHERE p.status = 'waiting'
                ORDER BY p.priority ASC, p.created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getNextPatient() {
        $query = "SELECT 
                    p.id,
                    p.user_id,
                    p.priority,
                    p.is_chronic,
                    p.is_pregnant,
                    p.is_critical,
                    p.other_diseases,
                    p.current_conditions,
                    p.suggested_department,
                    p.suggested_doctor,
                    p.created_at,
                    u.full_name,
                    TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age,
                    u.gender,
                    u.national_id
                FROM " . $this->patients_table . " p
                INNER JOIN " . $this->users_table . " u ON p.user_id = u.id
                WHERE p.status = 'waiting'
                ORDER BY p.priority ASC, p.created_at ASC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function moveToConsultation($patient_id) {
        $query = "UPDATE " . $this->patients_table . " 
                  SET status = 'in_consultation', updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :patient_id AND status = 'waiting'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        return $stmt->execute();
    }
    
    public function getPatientPosition($user_id) {
        $query = "SELECT COUNT(*) as position
                FROM " . $this->patients_table . " p1
                WHERE p1.status = 'waiting'
                AND (
                    p1.priority < (
                        SELECT priority 
                        FROM " . $this->patients_table . " p2
                        WHERE p2.user_id = :user_id 
                        AND p2.status = 'waiting'
                        LIMIT 1
                    )
                    OR (
                        p1.priority = (
                            SELECT priority 
                            FROM " . $this->patients_table . " p2
                            WHERE p2.user_id = :user_id 
                            AND p2.status = 'waiting'
                            LIMIT 1
                        )
                        AND p1.created_at < (
                            SELECT created_at 
                            FROM " . $this->patients_table . " p2
                            WHERE p2.user_id = :user_id 
                            AND p2.status = 'waiting'
                            LIMIT 1
                        )
                    )
                )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['position']) : 0;
    }
    
    public function getQueueStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_waiting,
                    SUM(CASE WHEN priority = 1 THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN priority = 2 THEN 1 ELSE 0 END) as pregnant_count,
                    SUM(CASE WHEN priority = 3 THEN 1 ELSE 0 END) as elderly_chronic_count,
                    SUM(CASE WHEN priority = 4 THEN 1 ELSE 0 END) as chronic_count,
                    SUM(CASE WHEN priority = 5 THEN 1 ELSE 0 END) as regular_count,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as avg_waiting_time
                FROM " . $this->patients_table . "
                WHERE status = 'waiting'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getPatientStatus($user_id) {
        $query = "SELECT 
                    p.id,
                    p.status,
                    p.priority,
                    p.suggested_department,
                    p.suggested_doctor,
                    p.created_at,
                    TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) as waiting_minutes
                FROM " . $this->patients_table . " p
                WHERE p.user_id = :user_id
                AND p.status IN ('waiting', 'in_consultation')
                ORDER BY p.created_at DESC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getEstimatedWaitingTime($user_id) {
        $avg_consultation_minutes = 15;
        $position = $this->getPatientPosition($user_id);
        return $position * $avg_consultation_minutes;
    }
    
    public function hasActiveAppointment($user_id) {
        $query = "SELECT COUNT(*) as count
                FROM " . $this->patients_table . "
                WHERE user_id = :user_id
                AND status IN ('waiting', 'in_consultation')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    public function markAsCompleted($patient_id) {
        $query = "UPDATE " . $this->patients_table . " 
                  SET status = 'completed', updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :patient_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        return $stmt->execute();
    }
}
?>