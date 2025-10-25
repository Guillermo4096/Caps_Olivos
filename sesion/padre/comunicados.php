<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'No autorizado']));
}

try {
    // CORRECCIÓN: Instanciar la clase Database correctamente
    require_once '../../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener comunicados
    $stmt = $conn->prepare("
        SELECT c.id, c.titulo, c.mensaje, c.fecha_publicacion, c.tipo,
               u.nombres as remitente_nombre, u.apellidos as remitente_apellidos,
               CASE 
                   WHEN c.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 
                   ELSE 0 
               END as es_nuevo
        FROM comunicados c 
        LEFT JOIN usuarios u ON c.remitente_id = u.id 
        WHERE c.activo = 1 
        ORDER BY c.fecha_publicacion DESC
        LIMIT 20
    ");
    $stmt->execute();
    $comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'comunicados' => $comunicados
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>