<?php
// File: /api/administrador/obtener-docentes.php
// Objetivo: Obtener la lista de todos los docentes activos para un select.

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
    
    // Seleccionar el ID del docente, nombres y apellidos del usuario asociado
    // Usamos el ID del docente (d.id) ya que es el valor que se guarda en grados.tutor_id
    $stmt = $conn->prepare("
        SELECT 
            d.id, 
            u.nombres, 
            u.apellidos 
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
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al cargar docentes.']);
}