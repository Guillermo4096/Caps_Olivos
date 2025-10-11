<?php
header('Content-Type: application/json');
session_start();

// Para desarrollo - mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    // DEBUG: Log los datos recibidos
    error_log("Login attempt - Usuario: " . $username . ", Password: " . $password);
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos']);
        exit;
    }
    
    try {
        $auth = new Auth();
        $result = $auth->login($username, $password);
        
        // DEBUG: Log el resultado
        error_log("Login result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['user_type'] = $result['user_type'];
            $_SESSION['nombres'] = $result['nombres'];
            $_SESSION['apellidos'] = $result['apellidos'];
            
            echo json_encode([
                'success' => true,
                'user_type' => $result['user_type'],
                'user_data' => [
                    'nombres' => $result['nombres'],
                    'apellidos' => $result['apellidos']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => $result['message'],
                'debug' => 'Usuario o contraseña incorrectos'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error en login: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error del servidor',
            'debug' => $e->getMessage()
        ]);
    }
}
?>