<?php
session_start();
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'No autorizado']));
}

try {
    $pdo = Database::getConnection();
    $user_id = $_SESSION['user_id'];

    // 1. Obtener información del padre
    $stmt = $pdo->prepare("
        SELECT p.id, p.dni, p.telefono, p.direccion, u.nombres, u.apellidos, u.email 
        FROM padres p 
        INNER JOIN usuarios u ON p.usuario_id = u.id 
        WHERE p.usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $padre = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$padre) {
        throw new Exception("Padre no encontrado");
    }

    // 2. Obtener estudiantes asociados al padre
    $stmt = $pdo->prepare("
        SELECT e.id, e.codigo_estudiante, u.nombres, u.apellidos, g.nombre as grado, g.seccion
        FROM estudiantes e 
        INNER JOIN usuarios u ON e.usuario_id = u.id 
        INNER JOIN grados g ON e.grado_id = g.id 
        INNER JOIN padre_estudiante pe ON e.id = pe.estudiante_id 
        WHERE pe.padre_id = ?
    ");
    $stmt->execute([$padre['id']]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($estudiantes)) {
        throw new Exception("No se encontraron estudiantes asociados");
    }

    // 3. Obtener estadísticas de tareas para el primer estudiante
    $estudiante_id = $estudiantes[0]['id'];
    
    // Tareas pendientes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM tareas t 
        INNER JOIN estudiante_tarea et ON t.id = et.tarea_id 
        WHERE et.estudiante_id = ? AND et.estado = 'pendiente' AND t.fecha_entrega >= CURDATE()
    ");
    $stmt->execute([$estudiante_id]);
    $tareas_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Tareas completadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM tareas t 
        INNER JOIN estudiante_tarea et ON t.id = et.tarea_id 
        WHERE et.estudiante_id = ? AND et.estado = 'completada'
    ");
    $stmt->execute([$estudiante_id]);
    $tareas_completadas = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 4. Obtener próximo evento
    $stmt = $pdo->prepare("
        SELECT titulo, fecha_evento 
        FROM eventos 
        WHERE fecha_evento >= CURDATE() 
        ORDER BY fecha_evento ASC 
        LIMIT 1
    ");
    $stmt->execute();
    $proximo_evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $dias_proximo_evento = $proximo_evento ? 
        floor((strtotime($proximo_evento['fecha_evento']) - time()) / (60 * 60 * 24)) : 0;

    // 5. Mensajes nuevos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM comunicados 
        WHERE fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        AND leido = 0
    ");
    $stmt->execute();
    $mensajes_nuevos = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 6. Tareas recientes
    $stmt = $pdo->prepare("
        SELECT t.id, t.titulo, t.descripcion, t.fecha_entrega, 
               m.nombre as materia, u.nombres as profesor_nombre, u.apellidos as profesor_apellidos,
               et.estado
        FROM tareas t 
        INNER JOIN materias m ON t.materia_id = m.id 
        INNER JOIN usuarios u ON t.profesor_id = u.id 
        INNER JOIN estudiante_tarea et ON t.id = et.tarea_id 
        WHERE et.estudiante_id = ? 
        ORDER BY t.fecha_entrega ASC 
        LIMIT 5
    ");
    $stmt->execute([$estudiante_id]);
    $tareas_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta final
    echo json_encode([
        'success' => true,
        'padre' => $padre,
        'estudiantes' => $estudiantes,
        'estadisticas' => [
            'tareas_pendientes' => $tareas_pendientes,
            'tareas_completadas' => $tareas_completadas,
            'dias_proximo_evento' => $dias_proximo_evento,
            'mensajes_nuevos' => $mensajes_nuevos
        ],
        'tareas_recientes' => $tareas_recientes,
        'estudiante_actual' => $estudiantes[0]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>