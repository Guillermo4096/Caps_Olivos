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
    
    // Obtener todos los usuarios
    $stmt = $conn->query("
        SELECT id, username, nombres, apellidos, tipo, email, dni, telefono, activo, fecha_creacion 
        FROM usuarios 
        ORDER BY tipo, nombres
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'usuarios' => $usuarios
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>