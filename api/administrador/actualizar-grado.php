<?php
// File: /api/administrador/actualizar-grado.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

require_once '../../includes/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
// Si tutor_id es 0, se mapea a NULL en la BD para "Sin Tutor"
$tutor_id_input = isset($input['tutor_id']) ? intval($input['tutor_id']) : null; 
// El estado activo se manda como 1 o 0
$activo = isset($input['activo']) ? intval($input['activo']) : null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grado inválido']);
    exit;
}

// Validar que al menos se está intentando actualizar tutor_id o activo
if ($tutor_id_input === null && $activo === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron campos para actualizar (tutor_id o activo).']);
    exit;
}

$set_clauses = [];
$params = [':id' => $id];

if ($tutor_id_input !== null) {
    $set_clauses[] = "tutor_id = :tutor_id";
    // Mapear el '0' del frontend (Sin tutor) a NULL en la BD
    $params[':tutor_id'] = ($tutor_id_input === 0) ? null : $tutor_id_input;
}

if ($activo !== null) {
    $set_clauses[] = "activo = :activo";
    $params[':activo'] = $activo;
}

$sql = "UPDATE grados SET " . implode(', ', $set_clauses) . " WHERE id = :id";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        $message = "Grado actualizado correctamente.";
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        http_response_code(200); // Se encontró el ID pero no hubo cambios
        echo json_encode(['success' => true, 'message' => 'No se encontraron cambios o el grado no existe.']);
    }
} catch (Exception $e) {
    // Error en la base de datos (ej: tutor_id no existe)
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el grado: ' . $e->getMessage()]);
}
?>