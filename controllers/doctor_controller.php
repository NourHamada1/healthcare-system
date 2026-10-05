<?php
/**
 * Doctor Controller
 * Handles doctor consultations, patient management, and prescriptions
 * 
 * @author Healthcare System Team
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Queue.php';
require_once __DIR__ . '/../models/Prescription.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_service.php';

class DoctorController {
    private $db;
    private $patient;
    private $queue;
    private $prescription;
    private $doctor;
    
    /**
     * Constructor - Initialize database connection and models
     */
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->patient = new Patient($this->db);
        $this->queue = new Queue($this->db);
        $this->prescription = new Prescription($this->db);
        $this->doctor = new Doctor($this->db);
    }
    
    /**
     * Get next patient in queue
     * @return array Patient data or empty if no patients
     */
    public function getNextPatient() {
        try {
            if (!isLoggedIn() || getUserType() !== 'doctor') {
                return [
                    'success' => false,
                    'message' => 'Unauthorized access'
                ];
            }
            
            $next_patient = $this->queue->getNextPatient();
            
            if (!$next_patient) {
                return [
                    'success' => true,
                    'has_patient' => false,
                    'message' => 'No patients in queue'
                ];
            }
            
            return [
                'success' => true,
                'has_patient' => true,
                'patient' => $next_patient
            ];
            
        } catch (Exception $e) {
            error_log("Get next patient error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving next patient'
            ];
        }
    }
    
    /**
     * Get all waiting patients in queue
     * @return array List of waiting patients
     */
    public function getWaitingPatients() {
        try {
            $patients = $this->queue->getAllWaitingPatients();
            $statistics = $this->queue->getQueueStatistics();
            
            return [
                'success' => true,
                'patients' => $patients,
                'total_waiting' => count($patients),
                'statistics' => $statistics
            ];
            
        } catch (Exception $e) {
            error_log("Get waiting patients error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving patient queue'
            ];
        }
    }
    
    /**
     * Get patient details for consultation
     * @param int $patient_id Patient record ID
     * @return array Patient details
     */
    public function getPatientDetails($patient_id) {
        try {
            $patient_data = $this->patient->getPatientById($patient_id);
            
            if (!$patient_data) {
                return [
                    'success' => false,
                    'message' => 'Patient not found'
                ];
            }
            
            // Get patient's medical history
            $history_query = "SELECT 
                                p.current_conditions,
                                p.other_diseases,
                                p.created_at,
                                pr.medication,
                                pr.notes
                            FROM patients p
                            LEFT JOIN prescriptions pr ON p.id = pr.patient_id
                            WHERE p.user_id = :user_id
                            AND p.id != :current_id
                            ORDER BY p.created_at DESC
                            LIMIT 5";
            
            $stmt = $this->db->prepare($history_query);
            $stmt->bindParam(':user_id', $patient_data['user_id']);
            $stmt->bindParam(':current_id', $patient_id);
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'patient' => $patient_data,
                'medical_history' => $history
            ];
            
        } catch (Exception $e) {
            error_log("Get patient details error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving patient details'
            ];
        }
    }
    
    /**
     * Start consultation with patient
     * @param int $patient_id Patient record ID
     * @return array Response array
     */
    public function startConsultation($patient_id) {
        try {
            if ($this->queue->moveToConsultation($patient_id)) {
                $this->logDoctorAction(getUserId(), 'CONSULTATION_STARTED', 
                    "Started consultation with patient ID: $patient_id");
                
                return [
                    'success' => true,
                    'message' => 'Consultation started'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to start consultation'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Start consultation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error starting consultation'
            ];
        }
    }
    
    /**
     * Complete consultation and create prescription
     * @param array $data Consultation data
     * @return array Response array
     */
    public function completeConsultation($data) {
        try {
            // Validate required fields
            if (empty($data['patient_id']) || empty($data['medication'])) {
                return [
                    'success' => false,
                    'message' => 'Patient ID and medication are required'
                ];
            }
            
            // Validate consultation duration
            $duration = intval($data['consultation_duration'] ?? 15);
            if ($duration < 1 || $duration > 240) {
                return [
                    'success' => false,
                    'message' => 'Invalid consultation duration'
                ];
            }
            
            // Create prescription
            $this->prescription->patient_id = intval($data['patient_id']);
            $this->prescription->doctor_id = getUserId();
            $this->prescription->medication = sanitizeInput($data['medication']);
            $this->prescription->notes = sanitizeInput($data['notes'] ?? '');
            $this->prescription->consultation_duration = $duration;
            
            if ($this->prescription->create()) {
                // Mark patient as completed
                $this->queue->markAsCompleted($data['patient_id']);
                
                $this->logDoctorAction(getUserId(), 'CONSULTATION_COMPLETED', 
                    "Completed consultation for patient ID: {$data['patient_id']}");
                
                return [
                    'success' => true,
                    'message' => 'Consultation completed and prescription sent to pharmacy',
                    'prescription_id' => $this->prescription->id
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create prescription'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Complete consultation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error completing consultation'
            ];
        }
    }
    
    /**
     * Get AI-powered treatment suggestions
     * @param string $diagnosis Diagnosis or symptoms
     * @param array $patient_info Patient information
     * @return array Treatment suggestions
     */
    public function getTreatmentSuggestions($diagnosis, $patient_info = []) {
        try {
            // Build the system prompt
            $system_prompt = "You are an expert AI diagnosing assistant for a doctor. " .
                             "Your goal is to suggest the optimal treatment, medication, and dosage based on the diagnosis and the patient's specific health profile. " .
                             "You must return ONLY a JSON object with two keys: " .
                             "1) 'primary_medication' (string: detailed medication name, dosage, and frequency instructions) " .
                             "2) 'considerations' (array of strings: any potential side effects, contraindications, or warnings specific to this patient's profile).\n" .
                             "Consider age, gender, pregnancy, and chronic conditions to ensure no negative side effects.";

            // Build the user prompt
            $user_prompt = "Diagnosis/Symptoms: " . $diagnosis . "\n";
            $user_prompt .= "Patient Age: " . ($patient_info['age'] ?? 'Unknown') . "\n";
            $user_prompt .= "Patient Gender: " . ($patient_info['gender'] ?? 'Unknown') . "\n";
            if (!empty($patient_info['is_pregnant'])) {
                $user_prompt .= "Condition: PREGNANT\n";
            }
            if (!empty($patient_info['is_chronic'])) {
                $user_prompt .= "Condition: CHRONIC DISEASES PRESENT\n";
            }
            $user_prompt .= "Patient Medical History/Other Conditions: " . ($patient_info['other_diseases'] ?? 'None identified') . "\n";

            $response = AIService::callOpenAI($system_prompt, $user_prompt, true);

            if ($response && isset($response['primary_medication'])) {
                $suggestions = [
                    'primary_medication' => $response['primary_medication'],
                    'considerations' => $response['considerations'] ?? []
                ];
            } else {
                throw new Exception("Invalid AI response format.");
            }
            
            return [
                'success' => true,
                'suggestions' => $suggestions
            ];
            
        } catch (Exception $e) {
            error_log("Get treatment suggestions error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error generating treatment suggestions'
            ];
        }
    }
    
    /**
     * Get doctor's consultation statistics
     * @param int $doctor_id Doctor user ID
     * @param string $period Period (today, week, month, all)
     * @return array Statistics
     */
    public function getConsultationStatistics($doctor_id, $period = 'today') {
        try {
            $date_condition = '';
            
            switch ($period) {
                case 'today':
                    $date_condition = "AND DATE(pr.created_at) = CURDATE()";
                    break;
                case 'week':
                    $date_condition = "AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $date_condition = "AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'all':
                default:
                    $date_condition = "";
            }
            
            $query = "SELECT 
                        COUNT(*) as total_consultations,
                        AVG(pr.consultation_duration) as avg_duration,
                        SUM(pr.consultation_duration) as total_minutes,
                        COUNT(DISTINCT p.user_id) as unique_patients
                    FROM prescriptions pr
                    INNER JOIN patients p ON pr.patient_id = p.id
                    WHERE pr.doctor_id = :doctor_id
                    $date_condition";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':doctor_id', $doctor_id);
            $stmt->execute();
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'period' => $period,
                'statistics' => [
                    'total_consultations' => intval($stats['total_consultations'] ?? 0),
                    'average_duration' => round($stats['avg_duration'] ?? 0, 1),
                    'total_hours' => round(($stats['total_minutes'] ?? 0) / 60, 1),
                    'unique_patients' => intval($stats['unique_patients'] ?? 0)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving statistics'
            ];
        }
    }
    
    /**
     * Get doctor's recent consultations
     * @param int $doctor_id Doctor user ID
     * @param int $limit Number of records
     * @return array Recent consultations
     */
    public function getRecentConsultations($doctor_id, $limit = 10) {
        try {
            $query = "SELECT 
                        pr.id,
                        pr.medication,
                        pr.notes,
                        pr.consultation_duration,
                        pr.created_at,
                        u.full_name as patient_name,
                        TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as patient_age,
                        p.current_conditions
                    FROM prescriptions pr
                    INNER JOIN patients p ON pr.patient_id = p.id
                    INNER JOIN users u ON p.user_id = u.id
                    WHERE pr.doctor_id = :doctor_id
                    ORDER BY pr.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'consultations' => $consultations
            ];
            
        } catch (Exception $e) {
            error_log("Get recent consultations error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving recent consultations'
            ];
        }
    }
    
    /**
     * Update doctor professional information
     * @param int $user_id User ID
     * @param array $data Professional data
     * @return array Response array
     */
    public function updateProfessionalInfo($user_id, $data) {
        try {
            $query = "UPDATE doctors 
                    SET specialization = :specialization,
                        years_experience = :years_experience,
                        description = :description,
                        previous_work = :previous_work,
                        previous_institution = :previous_institution,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':specialization', $data['specialization']);
            $stmt->bindParam(':years_experience', $data['years_experience']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':previous_work', $data['previous_work']);
            $stmt->bindParam(':previous_institution', $data['previous_institution']);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Professional information updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update information'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Update professional info error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating professional information'
            ];
        }
    }
    
    /**
     * Get doctor dashboard data
     * @param int $user_id User ID
     * @return array Dashboard data
     */
    public function getDashboardData($user_id) {
        try {
            $doctor_info = $this->doctor->getDoctorInfo($user_id);
            $next_patient = $this->getNextPatient();
            $queue_data = $this->getWaitingPatients();
            $statistics = $this->getConsultationStatistics($user_id, 'today');
            
            return [
                'success' => true,
                'doctor' => $doctor_info,
                'next_patient' => $next_patient,
                'queue' => $queue_data,
                'today_stats' => $statistics['statistics']
            ];
            
        } catch (Exception $e) {
            error_log("Dashboard data error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error loading dashboard data'
            ];
        }
    }
    
    /**
     * Log doctor actions
     * @param int $user_id User ID
     * @param string $action Action type
     * @param string $description Description
     */
    private function logDoctorAction($user_id, $action, $description) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            $query = "INSERT INTO system_logs (user_id, action, description, ip_address)
                    VALUES (:user_id, :action, :description, :ip_address)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Logging error: " . $e->getMessage());
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $controller = new DoctorController();
    $response = [];
    
    switch ($_POST['action']) {
        case 'get_next_patient':
            $response = $controller->getNextPatient();
            break;
            
        case 'get_waiting_patients':
            $response = $controller->getWaitingPatients();
            break;
            
        case 'get_patient_details':
            $response = $controller->getPatientDetails($_POST['patient_id'] ?? 0);
            break;
            
        case 'start_consultation':
            $response = $controller->startConsultation($_POST['patient_id'] ?? 0);
            break;
            
        case 'complete_consultation':
            $response = $controller->completeConsultation($_POST);
            break;
            
        case 'get_treatment_suggestions':
            $response = $controller->getTreatmentSuggestions(
                $_POST['diagnosis'] ?? '',
                $_POST['patient_info'] ?? []
            );
            break;
            
        case 'get_statistics':
            $response = $controller->getConsultationStatistics(
                getUserId(),
                $_POST['period'] ?? 'today'
            );
            break;
            
        case 'get_dashboard':
            $response = $controller->getDashboardData(getUserId());
            break;
            
        default:
            $response = [
                'success' => false,
                'message' => 'Invalid action'
            ];
    }
    
    echo json_encode($response);
    exit();
}
?>