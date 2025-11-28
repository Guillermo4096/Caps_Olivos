<?php
// File: /api/docente/guardar-comunicado.php
session_start();
header('Content-Type: application/json');

// 1. Verificar método y sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'docente') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido.']);
    exit;
}

require_once '../../includes/database.php';

// 2. Lectura y Decodificación de JSON
$input_json = file_get_contents('php://input');
$input = json_decode($input_json, true);

// 3. Extracción y validación de datos
$titulo = trim($input['titulo'] ?? ''); // Viene como 'asuntoComunicado'
$mensaje = trim($input['mensaje'] ?? ''); // Viene como 'mensajeComunicado'
$grado_id = intval($input['grado_id'] ?? 0); // Viene como 'destinatariosComunicado'
$urgente = (bool)($input['urgente'] ?? 0);

if (empty($titulo) || empty($mensaje)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El título (asunto) y el mensaje son obligatorios.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $usuario_id = $_SESSION['user_id'];
    
    // 4. Obtener el ID del docente
    $stmt = $conn->prepare("SELECT id FROM docentes WHERE usuario_id = :usuario_id");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $docente_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$docente_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Docente no encontrado.']);
        exit;
    }
    
    $docente_id = $docente_data['id'];

    // Determinar valor de grado_id a insertar (NULL si es para "Todos mis grados" - value 0)
    $grado_id_to_insert = ($grado_id > 0) ? $grado_id : NULL;

    // 5. Insertar el nuevo comunicado
    $stmt = $conn->prepare("
        INSERT INTO comunicados (titulo, mensaje, docente_id, grado_id, urgente) 
        VALUES (:titulo, :mensaje, :docente_id, :grado_id, :urgente)
    ");
    
    $result = $stmt->execute([
        ':titulo' => $titulo,
        ':mensaje' => $mensaje,
        ':docente_id' => $docente_id,
        ':grado_id' => $grado_id_to_insert,
        ':urgente' => $urgente
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Comunicado enviado exitosamente.']);
    } else {
        throw new Exception('Error al guardar el comunicado en la base de datos.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>