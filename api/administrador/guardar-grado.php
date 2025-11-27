<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

require_once '../../includes/database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

$nombre = trim($input['nombre'] ?? '');
$tutor_id = isset($input['tutor_id']) ? intval($input['tutor_id']) : null;
$id = isset($input['id']) && $input['id'] !== '' ? intval($input['id']) : null;

if ($nombre === '' || !$tutor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nombre y tutor son requeridos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($id) {
        $stmt = $conn->prepare("UPDATE grados SET nombre = :nombre, tutor_id = :tutor_id WHERE id = :id");
        $stmt->execute([':nombre' => $nombre, ':tutor_id' => $tutor_id, ':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Grado actualizado correctamente']);
    } else {
        $stmt = $conn->prepare("INSERT INTO grados (nombre, tutor_id) VALUES (:nombre, :tutor_id)");
        $stmt->execute([':nombre' => $nombre, ':tutor_id' => $tutor_id]);
        echo json_encode(['success' => true, 'message' => 'Grado creado correctamente']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar grado']);
}
?>