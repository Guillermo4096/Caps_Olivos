<?php
session_start();
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $usuarioId = $data['usuarioId'] ?? '';
    $tipo = $data['tipo'] ?? '';
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $nombres = $data['nombres'] ?? '';
    $apellidos = $data['apellidos'] ?? '';
    $email = $data['email'] ?? '';
    $dni = $data['dni'] ?? '';
    $telefono = $data['telefono'] ?? '';
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Validaciones básicas
        if (empty($username) || empty($password) || empty($tipo) || empty($nombres) || empty($apellidos)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados']);
            exit;
        }
        
        // Verificar si el username ya existe (excepto para edición)
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
        $stmt->execute([$username, $usuarioId]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya existe']);
            exit;
        }
        
        // Generar hash de contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        if (empty($usuarioId)) {
            // CREAR NUEVO USUARIO
            $stmt = $conn->prepare("
                INSERT INTO usuarios (username, password_hash, tipo, dni, nombres, apellidos, telefono, email)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $password_hash, $tipo, $dni, $nombres, $apellidos, $telefono, $email]);
            
            $nuevoUsuarioId = $conn->lastInsertId();
            
            // Si es docente, crear registro en tabla docentes
            if ($tipo === 'docente') {
                $stmt = $conn->prepare("INSERT INTO docentes (usuario_id, especialidad) VALUES (?, ?)");
                $stmt->execute([$nuevoUsuarioId, 'Por definir']);
            }
            
            // Si es padre, crear registro en tabla padres
            if ($tipo === 'padre') {
                $stmt = $conn->prepare("INSERT INTO padres (usuario_id, direccion) VALUES (?, ?)");
                $stmt->execute([$nuevoUsuarioId, 'Por definir']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado exitosamente'
            ]);
            
        } else {
            // ACTUALIZAR USUARIO EXISTENTE
            // (Para implementar después)
            echo json_encode([
                'success' => false,
                'message' => 'La edición de usuarios estará disponible próximamente'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }
}
?>