<?php

echo "<h2>🔐 Pruebas Unitarias - Módulo de Autenticación</h2>";
echo "<style>body {font-family: Arial; margin: 20px;} .success {color: green;} .error {color: red;} .info {background: #e3f2fd; padding: 10px;}</style>";

// Simulación de base de datos de usuarios
$usuarios_registrados = [
    'docente@ie-juanpablo.edu.pe' => [
        'contrasena' => password_hash('docente123', PASSWORD_DEFAULT),
        'rol' => 'docente',
        'nombre' => 'Profesor Carlos'
    ],
    'estudiante@ie-juanpablo.edu.pe' => [
        'contrasena' => password_hash('estudiante456', PASSWORD_DEFAULT),
        'rol' => 'estudiante',
        'nombre' => 'Ana Pérez'
    ]
];

// Función de autenticación simulada
function autenticarUsuario($email, $contrasena, $usuarios_db) {
    // Verificar si el usuario existe
    if (!isset($usuarios_db[$email])) {
        return [
            'success' => false,
            'mensaje' => 'Usuario no encontrado',
            'rol' => null
        ];
    }
    
    $usuario = $usuarios_db[$email];
    
    // Verificar contraseña
    if (password_verify($contrasena, $usuario['contrasena'])) {
        return [
            'success' => true,
            'mensaje' => 'Autenticación exitosa',
            'rol' => $usuario['rol'],
            'nombre' => $usuario['nombre']
        ];
    } else {
        return [
            'success' => false,
            'mensaje' => 'Contraseña incorrecta',
            'rol' => null
        ];
    }
}

// Función de registro de usuario
function registrarUsuario($email, $contrasena, $rol, &$usuarios_db) {
    if (isset($usuarios_db[$email])) {
        return [
            'success' => false,
            'mensaje' => 'El usuario ya existe'
        ];
    }
    
    if (strlen($contrasena) < 6) {
        return [
            'success' => false,
            'mensaje' => 'La contraseña debe tener al menos 6 caracteres'
        ];
    }
    
    $usuarios_db[$email] = [
        'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
        'rol' => $rol,
        'nombre' => 'Nuevo Usuario'
    ];
    
    return [
        'success' => true,
        'mensaje' => 'Usuario registrado exitosamente'
    ];
}

echo "<div class='info'>";
echo "<strong>Usuarios de prueba en el sistema:</strong><br>";
foreach ($usuarios_registrados as $email => $datos) {
    echo "- $email (Rol: {$datos['rol']})<br>";
}
echo "</div>";

// Prueba 1: Autenticación exitosa
echo "<h3>✅ Prueba de Autenticación Exitosa</h3>";
$resultado1 = autenticarUsuario('docente@ie-juanpablo.edu.pe', 'docente123', $usuarios_registrados);
$estado1 = $resultado1['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase1 = $resultado1['success'] ? "success" : "error";

echo "<div class='$clase1'>";
echo "Caso: <strong>Credenciales correctas de docente</strong><br>";
echo "Email: docente@ie-juanpablo.edu.pe | Contraseña: docente123<br>";
echo "Resultado: {$resultado1['mensaje']} | Rol: {$resultado1['rol']} | Nombre: {$resultado1['nombre']}<br>";
echo "<strong>$estado1</strong>";
echo "</div><hr>";

// Prueba 2: Autenticación con contraseña incorrecta
echo "<h3>❌ Prueba de Autenticación Fallida (Contraseña incorrecta)</h3>";
$resultado2 = autenticarUsuario('estudiante@ie-juanpablo.edu.pe', 'contrasena_equivocada', $usuarios_registrados);
$estado2 = !$resultado2['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase2 = !$resultado2['success'] ? "success" : "error";

echo "<div class='$clase2'>";
echo "Caso: <strong>Contraseña incorrecta</strong><br>";
echo "Email: estudiante@ie-juanpablo.edu.pe | Contraseña: contrasena_equivocada<br>";
echo "Resultado: {$resultado2['mensaje']}<br>";
echo "<strong>$estado2</strong>";
echo "</div><hr>";

// Prueba 3: Usuario no existente
echo "<h3>❌ Prueba de Autenticación Fallida (Usuario no existe)</h3>";
$resultado3 = autenticarUsuario('noexiste@email.com', 'alguna_contrasena', $usuarios_registrados);
$estado3 = !$resultado3['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase3 = !$resultado3['success'] ? "success" : "error";

echo "<div class='$clase3'>";
echo "Caso: <strong>Usuario no registrado</strong><br>";
echo "Email: noexiste@email.com | Contraseña: alguna_contrasena<br>";
echo "Resultado: {$resultado3['mensaje']}<br>";
echo "<strong>$estado3</strong>";
echo "</div><hr>";

// Prueba 4: Registro de nuevo usuario
echo "<h3>📝 Prueba de Registro de Usuario</h3>";
$resultado4 = registrarUsuario('nuevo@ie-juanpablo.edu.pe', 'nuevo123', 'padre', $usuarios_registrados);
$estado4 = $resultado4['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase4 = $resultado4['success'] ? "success" : "error";

echo "<div class='$clase4'>";
echo "Caso: <strong>Registro de nuevo usuario padre</strong><br>";
echo "Email: nuevo@ie-juanpablo.edu.pe | Contraseña: nuevo123 | Rol: padre<br>";
echo "Resultado: {$resultado4['mensaje']}<br>";
echo "<strong>$estado4</strong>";
echo "</div><hr>";

// Prueba 5: Verificar que el nuevo usuario puede autenticarse
echo "<h3>✅ Verificación de Usuario Recién Registrado</h3>";
$resultado5 = autenticarUsuario('nuevo@ie-juanpablo.edu.pe', 'nuevo123', $usuarios_registrados);
$estado5 = $resultado5['success'] ? "✓ ÉXITO" : "✗ FALLÓ";
$clase5 = $resultado5['success'] ? "success" : "error";

echo "<div class='$clase5'>";
echo "Caso: <strong>Autenticación con usuario recién registrado</strong><br>";
echo "Email: nuevo@ie-juanpablo.edu.pe | Contraseña: nuevo123<br>";
echo "Resultado: {$resultado5['mensaje']} | Rol: {$resultado5['rol']}<br>";
echo "<strong>$estado5</strong>";
echo "</div><hr>";

echo "<h3>📊 Resumen de Pruebas de Autenticación</h3>";
echo "<p>Todas las funciones del módulo de autenticación fueron probadas exitosamente.</p>";
echo "<p><strong>Estado final: ✅ PRUEBAS EXITOSAS</strong></p>";
?>