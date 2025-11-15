<?php
session_start();
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener todos los estudiantes
    $stmt = $conn->query("
        SELECT id, numero_matricula, nombres, apellidos, dni, email, telefono, 
               grado, seccion, fecha_ingreso, activo, fecha_creacion 
        FROM estudiantes 
        ORDER BY grado, seccion, apellidos, nombres
    ");
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'estudiantes' => $estudiantes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>