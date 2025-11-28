<?php
// File: /api/administrador/docentes.php
// Objetivo: Obtener la lista de todos los docentes activos para el select de tutor.

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

// *** LÍNEA CRÍTICA: La ruta debe ser correcta ***
require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Seleccionar el ID del docente (d.id) ya que es el valor que se guarda en grados.tutor_id
    $stmt = $conn->prepare("
        SELECT 
            d.id, 
            u.nombres, 
            u.apellidos,
            u.username
        FROM docentes d
        INNER JOIN usuarios u ON d.usuario_id = u.id
        WHERE u.activo = 1 -- Solo docentes activos
        ORDER BY u.apellidos, u.nombres
    ");
    $stmt->execute();
    $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'docentes' => $docentes]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error al obtener docentes: " . $e->getMessage()); 
    // Muestra un error genérico al frontend en caso de fallo de BD
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al cargar docentes.']);
}