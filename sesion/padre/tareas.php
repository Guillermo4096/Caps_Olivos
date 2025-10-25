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

    // Obtener el estudiante asociado al padre
    $stmt = $pdo->prepare("
        SELECT e.id 
        FROM estudiantes e 
        INNER JOIN padre_estudiante pe ON e.id = pe.estudiante_id 
        INNER JOIN padres p ON pe.padre_id = p.id 
        WHERE p.usuario_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {
        throw new Exception("No se encontró estudiante asociado");
    }

    // Obtener todas las tareas del estudiante
    $stmt = $pdo->prepare("
        SELECT t.id, t.titulo, t.descripcion, t.fecha_entrega, t.fecha_creacion,
               m.nombre as materia, u.nombres as profesor_nombre, u.apellidos as profesor_apellidos,
               g.nombre as grado, g.seccion,
               et.estado, et.fecha_entrega as fecha_entrega_estudiante
        FROM tareas t 
        INNER JOIN materias m ON t.materia_id = m.id 
        INNER JOIN usuarios u ON t.profesor_id = u.id 
        INNER JOIN grados g ON t.grado_id = g.id 
        INNER JOIN estudiante_tarea et ON t.id = et.tarea_id 
        WHERE et.estudiante_id = ? 
        ORDER BY t.fecha_entrega DESC
    ");
    $stmt->execute([$estudiante['id']]);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tareas' => $tareas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>