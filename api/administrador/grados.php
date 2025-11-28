<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT 
            g.id,
            g.nivel,                 -- Nivel (Primaria/Secundaria)
            g.nombre,                -- Número del grado (1ro, 2do, etc.)
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
        ORDER BY 
            CASE g.nivel WHEN 'Primaria' THEN 1 WHEN 'Secundaria' THEN 2 ELSE 3 END, 
            CAST(REPLACE(g.nombre, 'ro', '') AS INT), -- Ordena por número
            g.seccion
    ");
    $stmt->execute();
    $grados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'grados' => $grados]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>