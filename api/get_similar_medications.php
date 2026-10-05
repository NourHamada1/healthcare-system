<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$category = $_GET['category'] ?? '';
$exclude_id = intval($_GET['exclude_id'] ?? 0);

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM medications
          WHERE category = :category
          AND id != :exclude_id
          AND quantity_in_stock > 0
          ORDER BY quantity_in_stock DESC
          LIMIT 3";

$stmt = $db->prepare($query);
$stmt->bindParam(':category', $category);
$stmt->bindParam(':exclude_id', $exclude_id);
$stmt->execute();

$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['medications' => $medications]);
?>
