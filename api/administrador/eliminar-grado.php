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
$id = isset($input['id']) ? intval($input['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Intentar eliminar (si existen dependencias puede fallar)
    $stmt = $conn->prepare("DELETE FROM grados WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Grado eliminado correctamente']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Grado no encontrado']);
    }
} catch (PDOException $e) {
    // Si hay FK u otro error, informar al cliente
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo eliminar el grado. Puede tener dependencias.']);
}
?>