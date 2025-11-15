<?php
session_start();
require_once '../../includes/database.php';

header('Content-Type: application/json');

// Verificar que el usuario sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos obligatorios
$camposRequeridos = ['nombres', 'apellidos', 'dni', 'grado', 'seccion'];
foreach ($camposRequeridos as $campo) {
    if (!isset($input[$campo]) || empty(trim($input[$campo]))) {
        echo json_encode(['success' => false, 'message' => "El campo $campo es obligatorio"]);
        exit;
    }
}

$estudianteId = isset($input['estudianteId']) ? intval($input['estudianteId']) : 0;
$nombres = trim($input['nombres']);
$apellidos = trim($input['apellidos']);
$dni = trim($input['dni']);
$email = isset($input['email']) ? trim($input['email']) : null;
$telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
$grado = trim($input['grado']);
$seccion = trim($input['seccion']);
$numeroMatricula = isset($input['numero_matricula']) ? trim($input['numero_matricula']) : null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar si el DNI ya existe (excepto para el estudiante actual en edición)
    $stmt = $conn->prepare("SELECT id FROM estudiantes WHERE dni = ? AND id != ?");
    $stmt->execute([$dni, $estudianteId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El DNI ya está registrado']);
        exit;
    }
    
    if ($estudianteId > 0) {
        // Modo edición
        $stmt = $conn->prepare("
            UPDATE estudiantes 
            SET nombres = ?, apellidos = ?, dni = ?, email = ?, telefono = ?, 
                grado = ?, seccion = ?, numero_matricula = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombres, $apellidos, $dni, $email, $telefono, $grado, $seccion, $numeroMatricula, $estudianteId]);
        $mensaje = 'Estudiante actualizado correctamente';
    } else {
        // Modo creación
        $stmt = $conn->prepare("
            INSERT INTO estudiantes (numero_matricula, nombres, apellidos, dni, email, telefono, grado, seccion, activo, fecha_ingreso, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$numeroMatricula, $nombres, $apellidos, $dni, $email, $telefono, $grado, $seccion]);
        $mensaje = 'Estudiante creado correctamente';
    }
    
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    error_log("Error en guardar-estudiante.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>