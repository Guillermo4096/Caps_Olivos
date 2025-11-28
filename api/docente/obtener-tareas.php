<?php
// File: /api/docente/obtener-tareas.php
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

    // 2. Obtener la lista de tareas creadas por el docente
    $stmt = $conn->prepare("
        SELECT 
            t.id, 
            t.titulo, 
            t.descripcion, 
            t.fecha_creacion, 
            t.fecha_entrega,
            g.nivel, 
            g.nombre AS grado_nombre, 
            g.seccion,
            m.nombre AS materia_nombre
        FROM tareas t
        JOIN grados g ON t.grado_id = g.id
        JOIN materias m ON t.materia_id = m.id
        WHERE t.docente_id = :docente_id
        ORDER BY t.fecha_entrega DESC
    ");
    $stmt->execute([':docente_id' => $docente_id]);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // 3. Obtener la carga académica para los dropdowns (solo para que el modal funcione)
    $stmt = $conn->prepare("
        SELECT 
            g.id AS grado_id,
            g.nivel,
            g.nombre AS grado_nombre,
            g.seccion,
            m.id AS materia_id,
            m.nombre AS materia_nombre
        FROM docente_materia_grado dmg
        JOIN grados g ON dmg.grado_id = g.id
        JOIN materias m ON dmg.materia_id = m.id
        WHERE dmg.docente_id = :docente_id
        ORDER BY g.nivel, g.nombre, g.seccion, m.nombre
    ");
    $stmt->execute([':docente_id' => $docente_id]);
    $carga_academica = $stmt->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode([
        'success' => true, 
        'tareas' => $tareas, 
        'carga_academica' => $carga_academica
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error al obtener tareas y carga: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}