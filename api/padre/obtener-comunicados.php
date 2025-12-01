<?php
session_start();

// 1. Verificación de sesión y tipo de usuario
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'No autorizado']));
}

try {
    require_once '../../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // --- 2. Obtener los IDs de los grados asignados a este padre ---
    $stmt_grados = $conn->prepare("
        SELECT GROUP_CONCAT(grado_id) AS grados_ids
        FROM padre_grado 
        WHERE usuario_padre_id = ?
    ");
    $stmt_grados->execute([$user_id]);
    $grados_data = $stmt_grados->fetch(PDO::FETCH_ASSOC);
    
    $grados_padre = !empty($grados_data['grados_ids']) ? array_map('intval', explode(',', $grados_data['grados_ids'])) : [];
    
    if (empty($grados_padre)) {
        // No hay grados asignados, no hay comunicados específicos que mostrar
        echo json_encode([
            'success' => true,
            'comunicados' => []
        ]);
        exit;
    }

    $placeholders = str_repeat('?,', count($grados_padre) - 1) . '?';

    // --- 3. Obtener comunicados: Generales (grado_id IS NULL) o Específicos (grado_id IN (...)) ---
    
    $query = "
    SELECT c.id, c.titulo, c.mensaje, c.fecha_publicacion, c.urgente,
           u.nombres as docente_nombres, u.apellidos as docente_apellidos,
           g.nombre as grado_nombre, g.seccion, g.nivel,
           CASE 
               -- CORRECCIÓN PARA SQLITE: 'NOW()' y 'DATE_SUB()' no son estándar en SQLite.
               -- Usamos DATETIME('now', '-7 day') para obtener la fecha de hace 7 días.
               WHEN c.fecha_publicacion >= DATETIME('now', '-7 day') THEN 1 
               ELSE 0 
           END as es_nuevo
    FROM comunicados c 
    LEFT JOIN grados g ON c.grado_id = g.id
    LEFT JOIN docentes d ON c.docente_id = d.id
    LEFT JOIN usuarios u ON d.usuario_id = u.id
    WHERE c.grado_id IS NULL OR c.grado_id IN ($placeholders)
    ORDER BY c.fecha_publicacion DESC
    LIMIT 50
";
    
    // Los parámetros para la ejecución son solo los IDs de los grados
    $params = $grados_padre;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'comunicados' => $comunicados
    ]);

} catch (Exception $e) {
    error_log("Error al obtener comunicados (Padre): " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor.'
    ]);
}
?>