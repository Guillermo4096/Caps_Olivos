<?php
// File: /api/docente/obtener-comunicados.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'docente') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido.']);
    exit;
}

require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    $usuario_id = $_SESSION['user_id'];
    
    // 1. Obtener el ID del docente
    $stmt = $conn->prepare("SELECT id FROM docentes WHERE usuario_id = :usuario_id");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $docente_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$docente_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Docente no encontrado.']);
        exit;
    }
    
    $docente_id = $docente_data['id'];

    // 2. Obtener la lista de comunicados creados por el docente
    $stmt = $conn->prepare("
        SELECT 
            c.id, 
            c.titulo, 
            c.mensaje, 
            c.fecha_publicacion,
            c.urgente,
            g.nombre AS grado_nombre, 
            g.seccion
        FROM comunicados c
        LEFT JOIN grados g ON c.grado_id = g.id
        WHERE c.docente_id = :docente_id
        ORDER BY c.fecha_publicacion DESC
    ");
    $stmt->execute([':docente_id' => $docente_id]);
    $comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'comunicados' => $comunicados
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>