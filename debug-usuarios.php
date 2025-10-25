<?php
// debug-usuarios.php - Debe estar en la RAIZ del proyecto
echo "<h2>🔍 Debug de Usuarios en Base de Datos</h2>";

try {
    // Ruta CORRECTA desde la raíz
    require_once 'includes/database.php';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Ver todos los usuarios
    $stmt = $conn->query("SELECT username, password_hash, tipo, nombres, apellidos FROM usuarios");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: red;'>❌ No hay usuarios en la base de datos</p>";
        echo "<p><a href='database/reset-completo.php'>¿Quieres cargar datos de prueba?</a></p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Username</th><th>Password Hash</th><th>Tipo</th><th>Nombres</th><th>Longitud Hash</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td style='font-family: monospace; font-size: 10px; word-break: break-all;'>" . 
             htmlspecialchars($user['password_hash']) . "</td>";
        echo "<td>" . htmlspecialchars($user['tipo']) . "</td>";
        echo "<td>" . htmlspecialchars($user['nombres']) . "</td>";
        echo "<td>" . strlen($user['password_hash']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>🧪 Probando contraseñas comunes:</h3>";
    
    $test_passwords = ['123456', 'password123', 'admin', 'test', ''];
    
    foreach ($users as $user) {
        echo "<h4>Usuario: " . $user['username'] . "</h4>";
        $encontrada = false;
        
        foreach ($test_passwords as $test_pwd) {
            $result = password_verify($test_pwd, $user['password_hash']);
            echo "Contraseña: '<strong>$test_pwd</strong>' → " . 
                 ($result ? '✅ CORRECTA' : '❌ incorrecta') . "<br>";
            
            if ($result) {
                $encontrada = true;
            }
        }
        
        if (!$encontrada) {
            echo "<p style='color: orange;'>⚠️ Ninguna contraseña común funcionó</p>";
        }
    }
    
    echo "<h3>🔧 Información del servidor:</h3>";
    echo "<p>PHP version: " . phpversion() . "</p>";
    echo "<p>PDO SQLite disponible: " . (extension_loaded('pdo_sqlite') ? '✅ Sí' : '❌ No') . "</p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
    
    // Información adicional de debugging
    echo "<h3>🔍 Debugging adicional:</h3>";
    echo "<p>Ruta actual: " . __DIR__ . "</p>";
    echo "<p>¿Existe includes/database.php? " . 
         (file_exists(__DIR__ . '/includes/database.php') ? '✅ Sí' : '❌ No') . "</p>";
    echo "<p>¿Existe database/plataforma_escolar.db? " . 
         (file_exists(__DIR__ . '/database/plataforma_escolar.db') ? '✅ Sí' : '❌ No') . "</p>";
}
?>