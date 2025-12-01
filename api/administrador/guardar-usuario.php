<?php
session_start();
require_once '../../includes/database.php';

// Verificar que el usuario sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log los datos recibidos
error_log("Datos recibidos en guardar-usuario.php: " . print_r($input, true));

// Validar que tenemos datos JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

// Validar campos obligatorios
$camposRequeridos = ['tipo', 'username', 'nombres', 'apellidos'];
foreach ($camposRequeridos as $campo) {
    if (!isset($input[$campo]) || empty(trim($input[$campo]))) {
        echo json_encode(['success' => false, 'message' => "El campo $campo es obligatorio"]);
        exit;
    }
}

$usuarioId = isset($input['usuarioId']) ? intval($input['usuarioId']) : 0;
$tipo = trim($input['tipo']);
$username = trim($input['username']);
$nombres = trim($input['nombres']);
$apellidos = trim($input['apellidos']);
$email = isset($input['email']) ? trim($input['email']) : null;
$dni = isset($input['dni']) ? trim($input['dni']) : null;
$telefono = isset($input['telefono']) ? trim($input['telefono']) : null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar si el username ya existe (excepto para el usuario actual en edición)
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
    $stmt->execute([$username, $usuarioId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso']);
        exit;
    }
    
    if ($usuarioId > 0) {
        // Modo edición
        if (isset($input['password']) && !empty(trim($input['password']))) {
            // Actualizar con nueva contraseña
            $password = password_hash(trim($input['password']), PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET tipo = ?, username = ?, password_hash = ?, nombres = ?, apellidos = ?, email = ?, dni = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$tipo, $username, $password, $nombres, $apellidos, $email, $dni, $telefono, $usuarioId]);
        } else {
            // Actualizar sin cambiar contraseña
            $stmt = $conn->prepare("UPDATE usuarios SET tipo = ?, username = ?, nombres = ?, apellidos = ?, email = ?, dni = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$tipo, $username, $nombres, $apellidos, $email, $dni, $telefono, $usuarioId]);
        }
        
        $mensaje = 'Usuario actualizado correctamente';
    } else {
        // Modo creación
        if (!isset($input['password']) || empty(trim($input['password']))) {
            echo json_encode(['success' => false, 'message' => 'La contraseña es obligatoria para crear un usuario']);
            exit;
        }
        
        $password = password_hash(trim($input['password']), PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (tipo, username, password_hash, nombres, apellidos, email, dni, telefono, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$tipo, $username, $password, $nombres, $apellidos, $email, $dni, $telefono]);
        
        $mensaje = 'Usuario creado correctamente';
    }
    
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    error_log("Error en guardar-usuario.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>