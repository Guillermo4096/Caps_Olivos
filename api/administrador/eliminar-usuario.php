<?php
session_start();
require_once '../../includes/database.php';

// Headers para JSON
header('Content-Type: application/json');

// Verificar que el usuario sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Obtener datos JSON
$json = file_get_contents('php://input');
$input = json_decode($json, true);

if (!isset($input['id']) || empty($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
    exit;
}

$usuarioId = intval($input['id']);

// No permitir que un administrador se elimine a sí mismo
if ($usuarioId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No puedes desactivar tu propio usuario']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar si el usuario existe
    $stmtCheck = $conn->prepare("SELECT id, activo FROM usuarios WHERE id = ?");
    $stmtCheck->execute([$usuarioId]);
    $usuario = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }
    
    // Cambiar estado activo/inactivo
    $nuevoEstado = $usuario['activo'] ? 0 : 1;
    $accion = $usuario['activo'] ? 'desactivado' : 'activado';
    
    $stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $stmt->execute([$nuevoEstado, $usuarioId]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Usuario {$accion} correctamente",
        'nuevoEstado' => $nuevoEstado
    ]);
    
} catch (PDOException $e) {
    error_log("Error PDO en eliminar-usuario.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error general en eliminar-usuario.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>