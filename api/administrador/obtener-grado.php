<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

require_once '../../includes/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT 
            g.id, 
            g.nivel,             -- AGREGADO
            g.nombre,            -- AGREGADO
            COALESCE(g.seccion, '') AS seccion, 
            g.tutor_id,
            d.id AS docente_id,
            u.nombres AS tutor_nombres, 
            u.apellidos AS tutor_apellidos, 
            u.username AS tutor_username
            -- Columna estudiantes_count ELIMINADA para evitar error 'no such table: estudiantes'
        FROM grados g
        LEFT JOIN docentes d ON g.tutor_id = d.id
        LEFT JOIN usuarios u ON d.usuario_id = u.id
        WHERE g.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $grado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($grado) {
        echo json_encode(['success' => true, 'grado' => $grado]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Grado no encontrado']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>