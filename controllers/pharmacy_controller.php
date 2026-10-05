<?php
/**
 * Pharmacy Controller
 * Handles medication dispensing, inventory management, and payment processing
 * 
 * @author Healthcare System Team
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Prescription.php';
require_once __DIR__ . '/../models/Pharmacist.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

class PharmacyController {
    private $db;
    private $prescription;
    private $pharmacist;
    
    /**
     * Constructor - Initialize database connection and models
     */
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->prescription = new Prescription($this->db);
        $this->pharmacist = new Pharmacist($this->db);
    }
    
    /**
     * Get all pending prescriptions
     * @return array List of pending prescriptions
     */
    public function getPendingPrescriptions() {
        try {
            if (!isLoggedIn() || getUserType() !== 'pharmacist') {
                return [
                    'success' => false,
                    'message' => 'Unauthorized access'
                ];
            }
            
            $prescriptions = $this->prescription->getPendingPrescriptions();
            
            return [
                'success' => true,
                'prescriptions' => $prescriptions,
                'total_pending' => count($prescriptions)
            ];
            
        } catch (Exception $e) {
            error_log("Get pending prescriptions error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving pending prescriptions'
            ];
        }
    }
    
    /**
     * Get prescription details
     * @param int $prescription_id Prescription ID
     * @return array Prescription details
     */
    public function getPrescriptionDetails($prescription_id) {
        try {
            $query = "SELECT 
                        pr.id,
                        pr.medication,
                        pr.notes,
                        pr.consultation_duration,
                        pr.created_at,
                        p.id as patient_record_id,
                        p.current_conditions,
                        p.other_diseases,
                        p.is_chronic,
                        u.id as patient_user_id,
                        u.full_name as patient_name,
                        TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as patient_age,
                        u.gender as patient_gender,
                        d.full_name as doctor_name,
                        doc.specialization as doctor_specialization
                    FROM prescriptions pr
                    INNER JOIN patients p ON pr.patient_id = p.id
                    INNER JOIN users u ON p.user_id = u.id
                    INNER JOIN users d ON pr.doctor_id = d.id
                    LEFT JOIN doctors doc ON d.id = doc.user_id
                    WHERE pr.id = :prescription_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':prescription_id', $prescription_id);
            $stmt->execute();
            
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prescription) {
                return [
                    'success' => false,
                    'message' => 'Prescription not found'
                ];
            }
            
            return [
                'success' => true,
                'prescription' => $prescription
            ];
            
        } catch (Exception $e) {
            error_log("Get prescription details error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving prescription details'
            ];
        }
    }
    
    /**
     * Dispense medication and process payment
     * @param array $data Dispensing data
     * @return array Response array
     */
    public function dispenseMedication($data) {
        try {
            // Validate required fields
            if (empty($data['prescription_id']) || empty($data['patient_id'])) {
                return [
                    'success' => false,
                    'message' => 'Prescription ID and Patient ID are required'
                ];
            }
            
            $prescription_id = intval($data['prescription_id']);
            $patient_id = intval($data['patient_id']);
            $institution_type = $data['institution_type'] ?? 'government';
            
            // Start transaction
            $this->db->beginTransaction();
            
            try {
                // Record dispensed medication
                $medication_id = $data['medication_id'] ?? null;
                $quantity = intval($data['quantity'] ?? 1);
                $dosage_times = intval($data['dosage_times_per_day'] ?? 3);
                $dosage_instructions = sanitizeInput($data['dosage_instructions'] ?? '');
                
                if ($medication_id) {
                    $dispense_query = "INSERT INTO dispensed_medications 
                                    (prescription_id, medication_id, quantity, dosage_times_per_day, 
                                     dosage_instructions, dispensed_by)
                                    VALUES (:prescription_id, :medication_id, :quantity, :dosage_times, 
                                            :instructions, :pharmacist_id)";
                    
                    $stmt = $this->db->prepare($dispense_query);
                    $stmt->bindParam(':prescription_id', $prescription_id);
                    $stmt->bindParam(':medication_id', $medication_id);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':dosage_times', $dosage_times);
                    $stmt->bindParam(':instructions', $dosage_instructions);
                    $pharmacist_id = getUserId();
                    $stmt->bindParam(':pharmacist_id', $pharmacist_id);
                    $stmt->execute();
                }
                
                // Process payment
                $amount = 0;
                $payment_method = 'cash';
                
                if ($institution_type !== 'charity') {
                    $amount = floatval($data['amount'] ?? 0);
                    $payment_method = $data['payment_method'] ?? 'cash';
                    
                    if ($amount < 0) {
                        throw new Exception('Invalid payment amount');
                    }
                }
                
                $payment_query = "INSERT INTO payments 
                                (prescription_id, patient_id, amount, payment_method, 
                                 institution_type, status)
                                VALUES (:prescription_id, :patient_id, :amount, :payment_method, 
                                        :institution_type, 'completed')";
                
                $stmt = $this->db->prepare($payment_query);
                $stmt->bindParam(':prescription_id', $prescription_id);
                $stmt->bindParam(':patient_id', $patient_id);
                $stmt->bindParam(':amount', $amount);
                $stmt->bindParam(':payment_method', $payment_method);
                $stmt->bindParam(':institution_type', $institution_type);
                $stmt->execute();
                
                $payment_id = $this->db->lastInsertId();
                
                // Update prescription status
                $this->prescription->updateStatus($prescription_id, 'completed');
                
                // Commit transaction
                $this->db->commit();
                
                // Log action
                $this->logPharmacyAction(getUserId(), 'MEDICATION_DISPENSED', 
                    "Dispensed medication for prescription ID: $prescription_id, Amount: $amount");
                
                return [
                    'success' => true,
                    'message' => 'Medication dispensed successfully',
                    'payment_id' => $payment_id,
                    'amount' => $amount,
                    'is_free' => $institution_type === 'charity'
                ];
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Dispense medication error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error dispensing medication: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Search for medications in inventory
     * @param string $search_term Search term
     * @return array List of medications
     */
    public function searchMedications($search_term = '') {
        try {
            $query = "SELECT 
                        id,
                        name,
                        category,
                        quantity_in_stock,
                        unit_price,
                        requires_prescription
                    FROM medications
                    WHERE 1=1";
            
            if (!empty($search_term)) {
                $query .= " AND (name LIKE :search OR category LIKE :search)";
            }
            
            $query .= " ORDER BY name ASC";
            
            $stmt = $this->db->prepare($query);
            
            if (!empty($search_term)) {
                $search_param = "%$search_term%";
                $stmt->bindParam(':search', $search_param);
            }
            
            $stmt->execute();
            $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'medications' => $medications
            ];
            
        } catch (Exception $e) {
            error_log("Search medications error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error searching medications'
            ];
        }
    }
    
    /**
     * Get alternative medications for out-of-stock items
     * @param int $medication_id Original medication ID
     * @return array Alternative medications
     */
    public function getAlternativeMedications($medication_id) {
        try {
            $query = "SELECT alternative_medications FROM medications WHERE id = :medication_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':medication_id', $medication_id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || empty($result['alternative_medications'])) {
                return [
                    'success' => true,
                    'alternatives' => [],
                    'message' => 'No alternatives found'
                ];
            }
            
            $alternative_ids = explode(',', $result['alternative_medications']);
            $placeholders = str_repeat('?,', count($alternative_ids) - 1) . '?';
            
            $query = "SELECT 
                        id,
                        name,
                        category,
                        quantity_in_stock,
                        unit_price
                    FROM medications
                    WHERE id IN ($placeholders)
                    AND quantity_in_stock > 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($alternative_ids);
            $alternatives = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'alternatives' => $alternatives
            ];
            
        } catch (Exception $e) {
            error_log("Get alternatives error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving alternative medications'
            ];
        }
    }
    
    /**
     * Check medication availability
     * @param int $medication_id Medication ID
     * @param int $quantity Requested quantity
     * @return array Availability status
     */
    public function checkAvailability($medication_id, $quantity = 1) {
        try {
            $query = "SELECT 
                        name,
                        quantity_in_stock,
                        unit_price
                    FROM medications
                    WHERE id = :medication_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':medication_id', $medication_id);
            $stmt->execute();
            
            $medication = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$medication) {
                return [
                    'success' => false,
                    'message' => 'Medication not found'
                ];
            }
            
            $available = $medication['quantity_in_stock'] >= $quantity;
            
            return [
                'success' => true,
                'available' => $available,
                'in_stock' => intval($medication['quantity_in_stock']),
                'requested' => $quantity,
                'name' => $medication['name'],
                'price' => floatval($medication['unit_price'])
            ];
            
        } catch (Exception $e) {
            error_log("Check availability error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error checking availability'
            ];
        }
    }
    
    /**
     * Get low stock medications
     * @param int $threshold Stock threshold
     * @return array List of low stock items
     */
    public function getLowStockMedications($threshold = 100) {
        try {
            $query = "SELECT 
                        id,
                        name,
                        category,
                        quantity_in_stock,
                        unit_price
                    FROM medications
                    WHERE quantity_in_stock < :threshold
                    ORDER BY quantity_in_stock ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':threshold', $threshold);
            $stmt->execute();
            
            $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'low_stock_items' => $medications,
                'count' => count($medications)
            ];
            
        } catch (Exception $e) {
            error_log("Get low stock error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving low stock medications'
            ];
        }
    }
    
    /**
     * Get pharmacy statistics
     * @param string $period Period (today, week, month, all)
     * @return array Statistics
     */
    public function getPharmacyStatistics($period = 'today') {
        try {
            $date_condition = '';
            
            switch ($period) {
                case 'today':
                    $date_condition = "AND DATE(paid_at) = CURDATE()";
                    break;
                case 'week':
                    $date_condition = "AND paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $date_condition = "AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
            
            $query = "SELECT 
                        COUNT(*) as total_transactions,
                        SUM(amount) as total_revenue,
                        AVG(amount) as average_transaction,
                        SUM(CASE WHEN institution_type = 'charity' THEN 1 ELSE 0 END) as free_services,
                        SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_payments,
                        SUM(CASE WHEN payment_method IN ('bank', 'palPay', 'jawalPay') THEN 1 ELSE 0 END) as digital_payments
                    FROM payments
                    WHERE status = 'completed'
                    $date_condition";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'period' => $period,
                'statistics' => [
                    'total_transactions' => intval($stats['total_transactions'] ?? 0),
                    'total_revenue' => floatval($stats['total_revenue'] ?? 0),
                    'average_transaction' => round($stats['average_transaction'] ?? 0, 2),
                    'free_services' => intval($stats['free_services'] ?? 0),
                    'cash_payments' => intval($stats['cash_payments'] ?? 0),
                    'digital_payments' => intval($stats['digital_payments'] ?? 0)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get pharmacy statistics error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving pharmacy statistics'
            ];
        }
    }
    
    /**
     * Get pharmacy dashboard data
     * @return array Dashboard data
     */
    public function getDashboardData() {
        try {
            $pending = $this->getPendingPrescriptions();
            $low_stock = $this->getLowStockMedications();
            $stats = $this->getPharmacyStatistics('today');
            
            return [
                'success' => true,
                'pending_prescriptions' => $pending['prescriptions'] ?? [],
                'low_stock_items' => $low_stock['low_stock_items'] ?? [],
                'today_stats' => $stats['statistics'] ?? []
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
     * Log pharmacy actions
     * @param int $user_id User ID
     * @param string $action Action type
     * @param string $description Description
     */
    private function logPharmacyAction($user_id, $action, $description) {
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
    
    $controller = new PharmacyController();
    $response = [];
    
    switch ($_POST['action']) {
        case 'get_pending':
            $response = $controller->getPendingPrescriptions();
            break;
            
        case 'get_prescription_details':
            $response = $controller->getPrescriptionDetails($_POST['prescription_id'] ?? 0);
            break;
            
        case 'dispense_medication':
            $response = $controller->dispenseMedication($_POST);
            break;
            
        case 'search_medications':
            $response = $controller->searchMedications($_POST['search_term'] ?? '');
            break;
            
        case 'get_alternatives':
            $response = $controller->getAlternativeMedications($_POST['medication_id'] ?? 0);
            break;
            
        case 'check_availability':
            $response = $controller->checkAvailability(
                $_POST['medication_id'] ?? 0,
                $_POST['quantity'] ?? 1
            );
            break;
            
        case 'get_low_stock':
            $response = $controller->getLowStockMedications($_POST['threshold'] ?? 100);
            break;
            
        case 'get_statistics':
            $response = $controller->getPharmacyStatistics($_POST['period'] ?? 'today');
            break;
            
        case 'get_dashboard':
            $response = $controller->getDashboardData();
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