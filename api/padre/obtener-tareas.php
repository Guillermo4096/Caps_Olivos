<?php
session_start();
header('Content-Type: application/json');
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $user_id = $_SESSION['user_id'];

    // 1. OBTENER LOS GRADOS ASIGNADOS AL PADRE
    $stmt = $pdo->prepare("
        SELECT g.id as grado_id
        FROM padre_grado pg
        INNER JOIN grados g ON pg.grado_id = g.id
        WHERE pg.usuario_padre_id = ?
    ");
    $stmt->execute([$user_id]);
    $grados_padre = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($grados_padre)) {
        echo json_encode([
            'success' => true, 
            'tareas' => []
        ]);
        exit;
    }
    
    // Preparar placeholders para la consulta IN
    $placeholders = str_repeat('?,', count($grados_padre) - 1) . '?';

    // 2. OBTENER TODAS LAS TAREAS PARA LOS GRADOS ASIGNADOS
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.titulo,
            t.descripcion, 
            t.fecha_entrega,
            t.fecha_creacion,
            m.nombre AS materia_nombre,
            g.nombre AS grado_nombre, 
            g.seccion,
            u.nombres AS docente_nombres, 
            u.apellidos AS docente_apellidos,
            CASE 
                WHEN t.fecha_entrega < DATE('now') THEN 'vencida'
                WHEN t.fecha_entrega = DATE('now') THEN 'hoy'
                ELSE 'pendiente'
            END as estado_entrega
        FROM tareas t
        INNER JOIN materias m ON t.materia_id = m.id
        INNER JOIN grados g ON t.grado_id = g.id
        INNER JOIN docentes d ON t.docente_id = d.id
        INNER JOIN usuarios u ON d.usuario_id = u.id
        WHERE t.grado_id IN ($placeholders)
        ORDER BY t.fecha_entrega ASC
    ");
    
    $stmt->execute($grados_padre);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tareas' => $tareas
    ]);

} catch (Exception $e) {
    error_log("Error en obtener-tareas.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>