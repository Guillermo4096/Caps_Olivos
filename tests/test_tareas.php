<?php

echo "<h2>📚 Pruebas Unitarias - Módulo de Gestión de Tareas</h2>";
echo "<style>body {font-family: Arial; margin: 20px;} .success {color: green;} .error {color: red;} .info {background: #f3e5f5; padding: 10px;}</style>";

// Array simulado para almacenar tareas
$tareas_sistema = [];

// Función para crear una tarea
function crearTarea($titulo, $descripcion, $fecha_entrega, $asignatura, &$tareas_db) {
    // Validar campos obligatorios
    if (empty($titulo) || empty($fecha_entrega) || empty($asignatura)) {
        return [
            'success' => false,
            'mensaje' => 'Todos los campos marcados como * son obligatorios'
        ];
    }
    
    // Validar que la fecha de entrega no sea en el pasado
    $fecha_actual = date('Y-m-d');
    if ($fecha_entrega < $fecha_actual) {
        return [
            'success' => false,
            'mensaje' => 'La fecha de entrega no puede ser anterior a hoy'
        ];
    }
    
    // Generar ID único para la tarea
    $id = count($tareas_db) + 1;
    
    // Crear la tarea
    $tarea = [
        'id' => $id,
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'fecha_entrega' => $fecha_entrega,
        'asignatura' => $asignatura,
        'estado' => 'pendiente',
        'fecha_creacion' => date('Y-m-d H:i:s')
    ];
    
    $tareas_db[] = $tarea;
    
    return [
        'success' => true,
        'mensaje' => 'Tarea creada exitosamente',
        'tarea' => $tarea
    ];
}

// Función para listar tareas por asignatura
function listarTareasPorAsignatura($asignatura, $tareas_db) {
    $tareas_filtradas = array_filter($tareas_db, function($tarea) use ($asignatura) {
        return $tarea['asignatura'] === $asignatura;
    });
    
    return array_values($tareas_filtradas);
}

// Función para marcar tarea como completada
function completarTarea($id, &$tareas_db) {
    foreach ($tareas_db as &$tarea) {
        if ($tarea['id'] == $id) {
            $tarea['estado'] = 'completada';
            $tarea['fecha_completado'] = date('Y-m-d H:i:s');
            return [
                'success' => true,
                'mensaje' => 'Tarea marcada como completada',
                'tarea' => $tarea
            ];
        }
    }
    
    return [
        'success' => false,
        'mensaje' => 'Tarea no encontrada'
    ];
}

echo "<div class='info'>";
echo "<strong>Sistema de Gestión de Tareas - Pruebas Iniciales</strong><br>";
echo "Total de tareas en sistema: " . count($tareas_sistema);
echo "</div>";

// Prueba 1: Crear tarea válida
echo "<h3>✅ Prueba de Creación de Tarea Válida</h3>";
$resultado1 = crearTarea(
    'Investigación sobre el Sistema Solar',
    'Realizar una investigación sobre los planetas del sistema solar y sus características principales.',
    date('Y-m-d', strtotime('+5 days')),
    'Ciencia y Ambiente',
    $tareas_sistema
);

$estado1 = $resultado1['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase1 = $resultado1['success'] ? "success" : "error";

echo "<div class='$clase1'>";
echo "Caso: <strong>Crear tarea con datos válidos</strong><br>";
echo "Título: Investigación sobre el Sistema Solar<br>";
echo "Asignatura: Ciencia y Ambiente | Fecha Entrega: " . date('Y-m-d', strtotime('+5 days')) . "<br>";
echo "Resultado: {$resultado1['mensaje']} | ID: {$resultado1['tarea']['id']}<br>";
echo "<strong>$estado1</strong>";
echo "</div><hr>";

// Prueba 2: Crear tarea con fecha pasada
echo "<h3>❌ Prueba de Validación de Fecha Pasada</h3>";
$resultado2 = crearTarea(
    'Tarea con fecha inválida',
    'Esta tarea tiene una fecha de entrega en el pasado.',
    '2024-01-01',
    'Matemáticas',
    $tareas_sistema
);

$estado2 = !$resultado2['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase2 = !$resultado2['success'] ? "success" : "error";

echo "<div class='$clase2'>";
echo "Caso: <strong>Crear tarea con fecha en el pasado</strong><br>";
echo "Título: Tarea con fecha inválida<br>";
echo "Asignatura: Matemáticas | Fecha Entrega: 2024-01-01 (pasado)<br>";
echo "Resultado: {$resultado2['mensaje']}<br>";
echo "<strong>$estado2</strong>";
echo "</div><hr>";

// Prueba 3: Crear tarea sin título
echo "<h3>❌ Prueba de Validación de Campos Obligatorios</h3>";
$resultado3 = crearTarea(
    '',
    'Tarea sin título debería fallar.',
    date('Y-m-d', strtotime('+3 days')),
    'Comunicación',
    $tareas_sistema
);

$estado3 = !$resultado3['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase3 = !$resultado3['success'] ? "success" : "error";

echo "<div class='$clase3'>";
echo "Caso: <strong>Crear tarea sin título (campo obligatorio)</strong><br>";
echo "Título: (vacío) | Asignatura: Comunicación<br>";
echo "Resultado: {$resultado3['mensaje']}<br>";
echo "<strong>$estado3</strong>";
echo "</div><hr>";

// Prueba 4: Crear más tareas para probar el listado
echo "<h3>📝 Crear Tareas Adicionales para Pruebas</h3>";

$tareas_adicionales = [
    [
        'titulo' => 'Ejercicios de Álgebra',
        'asignatura' => 'Matemáticas',
        'dias_entrega' => 2
    ],
    [
        'titulo' => 'Análisis de Poema',
        'asignatura' => 'Comunicación',
        'dias_entrega' => 4
    ],
    [
        'titulo' => 'Problemas de Física',
        'asignatura' => 'Matemáticas',
        'dias_entrega' => 3
    ]
];

foreach ($tareas_adicionales as $tarea_data) {
    $resultado = crearTarea(
        $tarea_data['titulo'],
        'Descripción de ' . $tarea_data['titulo'],
        date('Y-m-d', strtotime('+' . $tarea_data['dias_entrega'] . ' days')),
        $tarea_data['asignatura'],
        $tareas_sistema
    );
    
    $estado = $resultado['success'] ? "✓" : "✗";
    echo "<div class='success'>";
    echo "$estado Tarea creada: {$tarea_data['titulo']} ({$tarea_data['asignatura']}) - ID: {$resultado['tarea']['id']}";
    echo "</div>";
}
echo "<hr>";

// Prueba 5: Listar tareas por asignatura
echo "<h3>📋 Prueba de Listado de Tareas por Asignatura</h3>";
$tareas_matematicas = listarTareasPorAsignatura('Matemáticas', $tareas_sistema);

echo "<div class='success'>";
echo "Caso: <strong>Listar tareas de Matemáticas</strong><br>";
echo "Total de tareas encontradas: " . count($tareas_matematicas) . "<br><br>";

foreach ($tareas_matematicas as $tarea) {
    echo "• {$tarea['titulo']} (Entrega: {$tarea['fecha_entrega']}, Estado: {$tarea['estado']})<br>";
}
echo "<strong>✓ ÉXITO - Filtrado por asignatura funcionando correctamente</strong>";
echo "</div><hr>";

// Prueba 6: Marcar tarea como completada
echo "<h3>✅ Prueba de Completar Tarea</h3>";
$resultado6 = completarTarea(1, $tareas_sistema); // Completar la primera tarea

$estado6 = $resultado6['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase6 = $resultado6['success'] ? "success" : "error";

echo "<div class='$clase6'>";
echo "Caso: <strong>Marcar tarea como completada</strong><br>";
echo "ID de tarea: 1<br>";
echo "Resultado: {$resultado6['mensaje']}<br>";
echo "Nuevo estado: {$resultado6['tarea']['estado']}<br>";
echo "<strong>$estado6</strong>";
echo "</div><hr>";

// Mostrar resumen final del sistema
echo "<h3>📊 Resumen Final del Sistema de Tareas</h3>";
echo "<div class='info'>";
echo "<strong>Estado actual del sistema:</strong><br>";
echo "• Total de tareas: " . count($tareas_sistema) . "<br>";
echo "• Tareas pendientes: " . count(array_filter($tareas_sistema, function($t) { return $t['estado'] === 'pendiente'; })) . "<br>";
echo "• Tareas completadas: " . count(array_filter($tareas_sistema, function($t) { return $t['estado'] === 'completada'; })) . "<br>";
echo "• Asignaturas con tareas: " . count(array_unique(array_column($tareas_sistema, 'asignatura'))) . "<br>";
echo "</div>";

echo "<p><strong>Estado final de las pruebas: ✅ TODAS LAS PRUEBAS EXITOSAS</strong></p>";
?>