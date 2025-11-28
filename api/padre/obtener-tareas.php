<?php
session_start();
header('Content-Type: application/json'); // Asegurar la salida JSON
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['success' => false, 'error' => 'No autorizado']));
}

try {
    $db = new Database();
    $pdo = $db->getConnection(); // O $conn = $db->getConnection();
    $user_id = $_SESSION['user_id'];

    // 1. OBTENER LOS GRADOS ASIGNADOS AL PADRE (Usando padre_grado)
    $stmt = $pdo->prepare("
        SELECT 
            g.id as grado_id
        FROM padre_grado pg
        INNER JOIN grados g ON pg.grado_id = g.id
        WHERE pg.usuario_padre_id = :usuario_padre_id
    ");
    $stmt->execute([':usuario_padre_id' => $user_id]);
    $grados_padre = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($grados_padre)) {
        // Si no hay grados asignados, no hay tareas que buscar.
        echo json_encode([
            'success' => true, 
            'tareas' => [],
            'message' => 'No tiene grados asignados, no hay tareas para mostrar.'
        ]);
        exit;
    }
    
    // Preparar los IDs de grado para la cláusula IN
    $grado_ids = array_column($grados_padre, 'grado_id');
    $placeholders = str_repeat('?,', count($grado_ids) - 1) . '?';

    // 2. OBTENER TODAS LAS TAREAS PARA LOS GRADOS ASIGNADOS
    $stmt = $pdo->prepare("
        SELECT t.id, t.titulo, t.descripcion, t.fecha_entrega, t.fecha_creacion,
               m.nombre as materia, 
               g.nombre as grado_nombre, g.seccion,
               u.nombres as profesor_nombre, u.apellidos as profesor_apellidos,
               
               -- Lógica para determinar el estado por fecha (similar a obtener-tareas.php)
               CASE 
                   WHEN t.fecha_entrega < DATE('now') THEN 'Vencida'
                   ELSE 'Pendiente'
               END as estado
               
        FROM tareas t 
        INNER JOIN materias m ON t.materia_id = m.id 
        INNER JOIN grados g ON t.grado_id = g.id
        
        /* CORRECCIÓN DE UNIÓN DEL DOCENTE */
        INNER JOIN docentes d ON t.docente_id = d.id 
        INNER JOIN usuarios u ON d.usuario_id = u.id 
        
        WHERE t.grado_id IN ($placeholders) 
        ORDER BY t.fecha_entrega DESC
    ");
    
    // Ejecutar la consulta con los IDs de grado
    $stmt->execute($grado_ids);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tareas' => $tareas
    ]);

} catch (Exception $e) {
    // Es buena práctica no revelar detalles internos en producción
    error_log("Error en tareas.php (Padre): " . $e->getMessage()); 
    echo json_encode([
        'success' => false,
        'error' => 'Error interno al cargar las tareas.'
    ]);
}
?>