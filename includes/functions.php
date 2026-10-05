<?php
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function calculateAge($date_of_birth) {
    if (empty($date_of_birth)) {
        return null;
    }
    try {
        $dob = new DateTime($date_of_birth);
        $now = new DateTime();
        return $dob->diff($now)->y;
    } catch (Exception $e) {
        return null;
    }
}

function calculatePriority($patient) {
    if ($patient['is_critical'] == 1) {
        return 1; // Highest priority for critical/emergencies
    } elseif ($patient['is_pregnant'] == 1) {
        return 2;
    } elseif ($patient['age'] >= 60 && $patient['is_chronic'] == 1) {
        return 3;
    } elseif ($patient['is_chronic'] == 1) {
        return 4; // NEW: Chronic condition priority
    } elseif ($patient['age'] < 18) {
        return 5;
    }
    
    return 5;
}

require_once __DIR__ . '/ai_service.php';
require_once __DIR__ . '/../models/Doctor.php';

function getAISuggestion($db, $symptoms, $conditions) {
    try {
        $doctor_model = new Doctor($db);
        $doctors = $doctor_model->getAllDoctors();
        
        $doctors_list = [];
        foreach ($doctors as $doc) {
            $doctors_list[] = "- Dr. " . $doc['full_name'] . " (Specialization: " . $doc['specialization'] . ")";
        }
        $doctors_text = implode("\n", $doctors_list);

        $system_prompt = "You are a medical triage AI. Your job is to analyze the patient's symptoms and conditions, then suggest the most appropriate medical department and select the exactly matching name of the best doctor from the provided list. Return ONLY a valid JSON object with keys 'department' and 'doctor'. If no doctor fits perfectly, pick the closest one. If the list is empty, default to 'General Practice' and 'Pending Assignment'.\n\nAvailable Doctors:\n" . $doctors_text;
        
        $user_prompt = "Current Symptoms: " . $symptoms . "\nOther Conditions: " . $conditions;
        
        $response = AIService::callOpenAI($system_prompt, $user_prompt, true);
        
        if ($response && isset($response['department']) && isset($response['doctor'])) {
            return [
                'department' => $response['department'],
                'doctor' => $response['doctor']
            ];
        }
    } catch (Exception $e) {
        error_log("AI Suggestion Error: " . $e->getMessage());
    }

    // Fallback if AI fails
    return [
        'department' => 'General Practice',
        'doctor' => 'Pending Assignment'
    ];
}

function getMedicationSuggestion($diagnosis) {
    try {
        $system_prompt = "You are a medical treatment assistant. Based on the patient's symptoms or diagnosis, suggest appropriate medication with dosage and frequency. Be concise (1-2 lines). Return plain text, not JSON.";
        $user_prompt = "Symptoms/Diagnosis: " . $diagnosis;

        $response = AIService::callOpenAI($system_prompt, $user_prompt, false);

        if ($response && isset($response['text']) && !empty(trim($response['text']))) {
            return trim($response['text']);
        }
    } catch (Exception $e) {
        error_log("AI Medication Suggestion Error: " . $e->getMessage());
    }

    // Fallback if AI fails
    $diagnosis_lower = strtolower($diagnosis);
    if (strpos($diagnosis_lower, 'pain') !== false || strpos($diagnosis_lower, 'fever') !== false) {
        return "Paracetamol 500mg, Ibuprofen 200mg as needed";
    } elseif (strpos($diagnosis_lower, 'infection') !== false) {
        return "Amoxicillin 500mg three times daily for 7 days";
    } elseif (strpos($diagnosis_lower, 'rash') !== false || strpos($diagnosis_lower, 'skin') !== false) {
        return "Hydrocortisone cream 1%, Cetirizine 10mg once daily";
    } elseif (strpos($diagnosis_lower, 'cough') !== false) {
        return "Dextromethorphan syrup, take as directed";
    }
    return "Based on diagnosis, consult pharmaceutical guidelines";
}
?>
