<?php
// File: /api/administrador/guardar-accesos-padre.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso restringido']);
    exit;
}

require_once '../../includes/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$padre_id = isset($input['padre_id']) ? intval($input['padre_id']) : 0;
// grado_ids es un array de IDs de grados seleccionados (puede estar vacío)
$grado_ids = isset($input['grado_ids']) && is_array($input['grado_ids']) ? $input['grado_ids'] : []; 

if (!$padre_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de padre inválido.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->beginTransaction();

    // 1. Verificar que el usuario es un padre activo
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = :id AND tipo = 'padre' AND activo = 1");
    $stmt->execute([':id' => $padre_id]);
    if (!$stmt->fetch()) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Padre no encontrado o inactivo.']);
        exit;
    }

    // 2. Eliminar asignaciones existentes para este padre
    $stmt = $conn->prepare("DELETE FROM padre_grado WHERE usuario_padre_id = :padre_id");
    $stmt->execute([':padre_id' => $padre_id]);

    // 3. Insertar nuevas asignaciones (si hay alguna)
    if (count($grado_ids) > 0) {
        $sql = "INSERT INTO padre_grado (usuario_padre_id, grado_id) VALUES ";
        $placeholders = [];
        $insertParams = [];

        foreach ($grado_ids as $index => $grado_id) {
            $keyPadre = ":padre_id_" . $index;
            $keyGrado = ":grado_id_" . $index;
            
            $placeholders[] = "({$keyPadre}, {$keyGrado})";
            $insertParams[$keyPadre] = $padre_id;
            $insertParams[$keyGrado] = intval($grado_id);
        }
        
        $sql .= implode(', ', $placeholders);
        $stmt = $conn->prepare($sql);
        $stmt->execute($insertParams);
    }
    
    $conn->commit();
    
    $message = "Accesos del padre actualizados correctamente.";
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    error_log("Error al guardar accesos del padre: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al actualizar accesos.']);
}