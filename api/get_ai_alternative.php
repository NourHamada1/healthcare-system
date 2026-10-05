<?php
// api/get_ai_alternative.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$patient_id = $_POST['patient_id'] ?? 0;
$prescribed_medication = $_POST['prescribed_medication'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$patient_id || empty($prescribed_medication)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get patient info
    $patient_query = "SELECT p.*, TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age, u.gender 
                      FROM patients p 
                      JOIN users u ON p.user_id = u.id 
                      WHERE p.id = :patient_id";
    $stmt = $db->prepare($patient_query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all in-stock medications
    $meds_query = "SELECT id, name, category, description FROM medications WHERE quantity_in_stock > 0";
    $stmt = $db->prepare($meds_query);
    $stmt->execute();
    $available_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($available_medications)) {
        echo json_encode(['success' => false, 'message' => 'No medications available in stock to suggest.']);
        exit();
    }

    $meds_list = [];
    foreach ($available_medications as $med) {
        $meds_list[] = "- ID " . $med['id'] . ": " . $med['name'] . " (" . $med['category'] . ")";
    }
    $meds_text = implode("\n", $meds_list);

    // Build the prompt for OpenAI
    $system_prompt = "You are a highly intelligent and safe pharmacy assistant AI. Your task is to find the best alternative medication for a given prescription that is out of stock, choosing strictly from the provided 'In-Stock Medications' list. " .
                     "You MUST consider the patient's age, gender, pregnancy status, and chronic conditions to ensure the alternative does not cause adverse side effects or conflicts. " .
                     "Return ONLY a JSON object with keys: " .
                     "1) 'suggested_medication_id' (integer: the ID of the matched alternative medication from the list), " .
                     "2) 'suggested_medication_name' (string: the name), " .
                     "3) 'reasoning' (string: detailed explanation of why it is safe for the patient and a good alternative, and any dosage modifications).";

    $user_prompt = "Prescribed (Out of Stock): " . $prescribed_medication . "\n";
    $user_prompt .= "Doctor's Notes: " . $notes . "\n\n";

    $user_prompt .= "Patient Age: " . ($patient['age'] ?? 'Unknown') . "\n";
    $user_prompt .= "Patient Gender: " . ($patient['gender'] ?? 'Unknown') . "\n";
    if (!empty($patient['is_pregnant'])) {
        $user_prompt .= "Condition: PREGNANT\n";
    }
    if (!empty($patient['is_chronic'])) {
        $user_prompt .= "Condition: CHRONIC DISEASES PRESENT\n";
    }
    $user_prompt .= "Patient Medical History/Other Conditions: " . ($patient['other_diseases'] ?? 'None identified') . "\n\n";

    $user_prompt .= "In-Stock Medications List:\n" . $meds_text;

    $response = AIService::callOpenAI($system_prompt, $user_prompt, true);

    if ($response && isset($response['suggested_medication_id'])) {
        echo json_encode([
            'success' => true,
            'suggestion' => $response
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'AI could not find a suitable alternative.']);
    }
} catch (Exception $e) {
    error_log("AI Alternative Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>
