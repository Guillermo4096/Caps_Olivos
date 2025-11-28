<?php
session_start();
header('Content-Type: application/json');

// Verificar que sea una petición POST
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

// 1. Lectura y Decodificación de JSON, con manejo de errores
$input_json = file_get_contents('php://input');

// Verificar si el contenido está vacío
if (empty($input_json)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON de entrada vacío.']);
    exit;
}

$input = json_decode($input_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

// 2. Extracción y validación de datos
$titulo = trim($input['titulo'] ?? '');
$descripcion = trim($input['descripcion'] ?? '');
$materia_id = intval($input['materia_id'] ?? 0);
$grado_id = intval($input['grado_id'] ?? 0);
$fecha_entrega = trim($input['fecha_entrega'] ?? ''); // Formato YYYY-MM-DD

// Debug: Log de los datos recibidos
error_log("Datos recibidos - Titulo: $titulo, Materia: $materia_id, Grado: $grado_id, Fecha: $fecha_entrega");

if (!$titulo || $materia_id === 0 || $grado_id === 0 || !$fecha_entrega) {
    http_response_code(400);
    
    // Generar un mensaje detallado de los campos que faltan
    $missing_fields = [];
    if (!$titulo) $missing_fields[] = 'título';
    if ($materia_id === 0) $missing_fields[] = 'materia';
    if ($grado_id === 0) $missing_fields[] = 'grado';
    if (!$fecha_entrega) $missing_fields[] = 'fecha de entrega';

    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios: ' . implode(', ', $missing_fields) . '.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $usuario_id = $_SESSION['user_id'];
    
    // 3. Obtener el ID del docente a partir del ID de usuario
    $stmt = $conn->prepare("SELECT id FROM docentes WHERE usuario_id = :usuario_id");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $docente_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$docente_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Docente no encontrado.']);
        exit;
    }
    
    $docente_id = $docente_data['id'];

    // 4. Insertar la nueva tarea
    $stmt = $conn->prepare("
        INSERT INTO tareas (titulo, descripcion, materia_id, grado_id, docente_id, fecha_entrega) 
        VALUES (:titulo, :descripcion, :materia_id, :grado_id, :docente_id, :fecha_entrega)
    ");
    
    $result = $stmt->execute([
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':materia_id' => $materia_id,
        ':grado_id' => $grado_id,
        ':docente_id' => $docente_id,
        ':fecha_entrega' => $fecha_entrega
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Tarea creada exitosamente.']);
    } else {
        throw new Exception('Error en la ejecución de la consulta INSERT');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error al guardar tarea (Excepción): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al crear la tarea: ' . $e->getMessage()]);
}
?>