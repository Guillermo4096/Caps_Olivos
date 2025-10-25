<?php
// reset-all-passwords.php - Resetear TODAS las contraseñas a '123456'
require_once 'includes/database.php';

echo "<h2>🔄 Reseteando TODAS las contraseñas</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Contraseña nueva
    $new_password = "123456";
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "<p>Nueva contraseña para TODOS: <strong>$new_password</strong></p>";
    echo "<p>Hash generado: <code style='word-break: break-all;'>$password_hash</code></p>";
    echo "<p>Longitud del hash: " . strlen($password_hash) . " caracteres</p>";
    
    // Actualizar TODOS los usuarios
    $stmt = $conn->prepare("UPDATE usuarios SET password_hash = ?");
    $stmt->execute([$password_hash]);
    
    $affected = $stmt->rowCount();
    echo "<p style='color: green;'>✅ $affected usuarios actualizados</p>";
    
    // Verificar que el hash funciona
    $verify_test = password_verify($new_password, $password_hash);
    echo "<p>Verificación del nuevo hash: " . ($verify_test ? '✅ FUNCIONA' : '❌ NO FUNCIONA') . "</p>";
    
    // Mostrar algunos usuarios
    $stmt = $conn->query("SELECT username, tipo, nombres FROM usuarios ORDER BY tipo LIMIT 15");
    $users = $stmt->fetchAll();
    
    echo "<h3>👥 Usuarios actualizados (contraseña: 123456):</h3>";
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 10px;'>";
    
    foreach ($users as $user) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; border-radius: 5px;'>";
        echo "<strong>Usuario:</strong> " . $user['username'] . "<br>";
        echo "<strong>Nombre:</strong> " . $user['nombres'] . "<br>";
        echo "<strong>Tipo:</strong> " . $user['tipo'] . "<br>";
        echo "<strong>Contraseña:</strong> 123456";
        echo "</div>";
    }
    echo "</div>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3>🎉 ¡Listo! Ahora prueba el login con:</h3>";
    echo "<ul>";
    echo "<li><strong>Docente:</strong> jorge.gutierrezr / 123456</li>";
    echo "<li><strong>Docente:</strong> ana.rodriguezg / 123456</li>";
    echo "<li><strong>Padre:</strong> juan.perezm / 123456</li>";
    echo "</ul>";
    echo "<p><a href='index.html'>Ir al Login</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}
?>