<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

require_once '../../includes/database.php';

$padre_id = isset($_GET['padre_id']) ? intval($_GET['padre_id']) : 0;

if (!$padre_id) {
    // Si accedes directamente a la URL sin ?padre_id=X, este es el error esperado
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'ID de padre inválido.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 1. Obtener todos los grados disponibles, y si están asignados al padre
    $stmt = $conn->prepare("
        SELECT 
            g.id,
            g.nivel,
            g.nombre,
            g.seccion,
            -- Usamos LEFT JOIN para obtener el ID de la tabla padre_grado si existe.
            CASE WHEN pg.usuario_padre_id IS NOT NULL THEN 1 ELSE 0 END AS asignado
        FROM grados g
        LEFT JOIN padre_grado pg 
            ON g.id = pg.grado_id AND pg.usuario_padre_id = :padre_id
        -- SE ELIMINA el WHERE g.activo = 1 para evitar fallos si la columna no existe.
        ORDER BY 
            CASE g.nivel WHEN 'Primaria' THEN 1 WHEN 'Secundaria' THEN 2 ELSE 3 END, 
            g.nombre,
            g.seccion
    ");
    $stmt->execute([':padre_id' => $padre_id]);
    $grados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener el nombre del padre para la cabecera del modal
    $stmt = $conn->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = :padre_id AND tipo = 'padre'");
    $stmt->execute([':padre_id' => $padre_id]);
    $padre_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$padre_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Padre no encontrado.']);
        exit;
    }

    echo json_encode(['success' => true, 'padre' => $padre_data, 'grados' => $grados]);
    
} catch (Exception $e) {
    http_response_code(500);
    // Registro de error para depuración
    error_log("Error al obtener grados del padre: " . $e->getMessage()); 
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al cargar los grados. Por favor, revise el log de errores.']);
}