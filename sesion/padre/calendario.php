<?php
session_start();
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'No autorizado']));
}

try {
    $pdo = Database::getConnection();

    // Obtener eventos del mes actual
    $mes = $_GET['mes'] ?? date('n');
    $ano = $_GET['ano'] ?? date('Y');

    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, fecha_evento, tipo_evento, lugar 
        FROM eventos 
        WHERE MONTH(fecha_evento) = ? AND YEAR(fecha_evento) = ? 
        ORDER BY fecha_evento ASC
    ");
    $stmt->execute([$mes, $ano]);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Próximos eventos (7 días)
    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, fecha_evento, tipo_evento, lugar 
        FROM eventos 
        WHERE fecha_evento >= CURDATE() AND fecha_evento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY fecha_evento ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $proximos_eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'eventos' => $eventos,
        'proximos_eventos' => $proximos_eventos,
        'mes_actual' => $mes,
        'ano_actual' => $ano
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>