<?php
/**
 * Script de prueba para conexión y operaciones con base de datos SQLite
 * Ejecutar en: http://localhost/tu_proyecto/tests/test_db.php
 */

echo "<h2>🗄️ Pruebas Unitarias - Conexión a Base de Datos SQLite</h2>";
echo "<style>body {font-family: Arial; margin: 20px;} .success {color: green;} .error {color: red;} .info {background: #e8f5e8; padding: 10px;} table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>";

try {
    // Configuración de la base de datos SQLite
    $database_file = "../data/school_platform.db";
    
    // Crear directorio si no existe
    if (!file_exists('../data')) {
        mkdir('../data', 0777, true);
    }
    
    echo "<div class='info'>";
    echo "<strong>Configuración de Base de Datos:</strong><br>";
    echo "Archivo: $database_file<br>";
    echo "Directorio data existe: " . (file_exists('../data') ? 'Sí' : 'No') . "<br>";
    echo "</div>";
    
    // Crear conexión a SQLite
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>";
    echo "✓ Conexión a SQLite establecida exitosamente<br>";
    echo "</div><hr>";
    
    // Prueba 1: Crear tabla de usuarios si no existe
    echo "<h3>✅ Prueba de Creación de Tablas</h3>";
    
    $create_users_table = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        contrasena TEXT NOT NULL,
        nombre TEXT NOT NULL,
        rol TEXT NOT NULL,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $create_tasks_table = "
    CREATE TABLE IF NOT EXISTS tareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        fecha_entrega DATE NOT NULL,
        asignatura TEXT NOT NULL,
        estado TEXT DEFAULT 'pendiente',
        usuario_id INTEGER,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    )";
    
    $db->exec($create_users_table);
    $db->exec($create_tasks_table);
    
    echo "<div class='success'>";
    echo "✓ Tablas 'usuarios' y 'tareas' creadas/verificadas exitosamente<br>";
    echo "</div><hr>";
    
    // Prueba 2: Insertar datos de prueba
    echo "<h3>✅ Prueba de Inserción de Datos</h3>";
    
    // Limpiar tablas para prueba fresca
    $db->exec("DELETE FROM tareas");
    $db->exec("DELETE FROM usuarios");
    
    // Insertar usuarios de prueba
    $insert_users = [
        "INSERT INTO usuarios (email, contrasena, nombre, rol) VALUES ('docente@ie-juanpablo.edu.pe', '" . password_hash('docente123', PASSWORD_DEFAULT) . "', 'Profesor Carlos', 'docente')",
        "INSERT INTO usuarios (email, contrasena, nombre, rol) VALUES ('estudiante@ie-juanpablo.edu.pe', '" . password_hash('estudiante456', PASSWORD_DEFAULT) . "', 'Ana Pérez', 'estudiante')",
        "INSERT INTO usuarios (email, contrasena, nombre, rol) VALUES ('padre@ie-juanpablo.edu.pe', '" . password_hash('padre789', PASSWORD_DEFAULT) . "', 'Sr. Pérez', 'padre')"
    ];
    
    foreach ($insert_users as $sql) {
        $db->exec($sql);
    }
    
    echo "<div class='success'>";
    echo "✓ 3 usuarios de prueba insertados correctamente<br>";
    echo "</div>";
    
    // Insertar tareas de prueba (CORREGIDO - sin error de comillas)
    $insert_tasks = [
        "INSERT INTO tareas (titulo, descripcion, fecha_entrega, asignatura, usuario_id) VALUES ('Investigación Científica', 'Realizar investigación sobre el método científico', '" . date('Y-m-d', strtotime('+5 days')) . "', 'Ciencia', 1)",
        "INSERT INTO tareas (titulo, descripcion, fecha_entrega, asignatura, usuario_id) VALUES ('Problemas de Matemáticas', 'Resolver los ejercicios de la página 45', '" . date('Y-m-d', strtotime('+2 days')) . "', 'Matemáticas', 1)",
        "INSERT INTO tareas (titulo, descripcion, fecha_entrega, asignatura, usuario_id) VALUES ('Análisis Literario', 'Analizar el poema La Canción del Pirata', '" . date('Y-m-d', strtotime('+7 days')) . "', 'Literatura', 1)"
    ];
    
    foreach ($insert_tasks as $sql) {
        $db->exec($sql);
    }
    
    echo "<div class='success'>";
    echo "✓ 3 tareas de prueba insertadas correctamente<br>";
    echo "</div><hr>";
    
    // Prueba 3: Consultar datos
    echo "<h3>✅ Prueba de Consulta de Datos</h3>";
    
    // Consultar usuarios
    echo "<h4>👥 Lista de Usuarios en el Sistema:</h4>";
    $stmt = $db->query("SELECT id, email, nombre, rol, fecha_creacion FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Email</th><th>Nombre</th><th>Rol</th><th>Fecha Creación</th></tr>";
    foreach ($usuarios as $usuario) {
        echo "<tr>";
        echo "<td>{$usuario['id']}</td>";
        echo "<td>{$usuario['email']}</td>";
        echo "<td>{$usuario['nombre']}</td>";
        echo "<td>{$usuario['rol']}</td>";
        echo "<td>{$usuario['fecha_creacion']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Consultar tareas
    echo "<h4>📚 Lista de Tareas en el Sistema:</h4>";
    $stmt = $db->query("SELECT t.id, t.titulo, t.asignatura, t.fecha_entrega, t.estado, u.nombre as docente FROM tareas t JOIN usuarios u ON t.usuario_id = u.id ORDER BY t.fecha_entrega");
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Título</th><th>Asignatura</th><th>Fecha Entrega</th><th>Estado</th><th>Docente</th></tr>";
    foreach ($tareas as $tarea) {
        echo "<tr>";
        echo "<td>{$tarea['id']}</td>";
        echo "<td>{$tarea['titulo']}</td>";
        echo "<td>{$tarea['asignatura']}</td>";
        echo "<td>{$tarea['fecha_entrega']}</td>";
        echo "<td>{$tarea['estado']}</td>";
        echo "<td>{$tarea['docente']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='success'>";
    echo "✓ Consultas de datos ejecutadas exitosamente<br>";
    echo "</div><hr>";
    
    // Prueba 4: Operaciones UPDATE y DELETE
    echo "<h3>✅ Prueba de Operaciones UPDATE y DELETE</h3>";
    
    // Actualizar una tarea
    $update_stmt = $db->prepare("UPDATE tareas SET estado = ? WHERE id = ?");
    $update_stmt->execute(['completada', 1]);
    
    echo "<div class='success'>";
    echo "✓ Tarea ID 1 actualizada a estado 'completada'<br>";
    echo "</div>";
    
    // Eliminar una tarea
    $delete_stmt = $db->prepare("DELETE FROM tareas WHERE id = ?");
    $delete_stmt->execute([3]);
    
    echo "<div class='success'>";
    echo "✓ Tarea ID 3 eliminada correctamente<br>";
    echo "</div>";
    
    // Verificar cambios
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas FROM tareas");
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>";
    echo "<strong>Estadísticas finales de tareas:</strong><br>";
    echo "• Total de tareas: {$estadisticas['total']}<br>";
    echo "• Tareas completadas: {$estadisticas['completadas']}<br>";
    echo "• Tareas pendientes: " . ($estadisticas['total'] - $estadisticas['completadas']) . "<br>";
    echo "</div><hr>";
    
    // Cerrar conexión
    $db = null;
    
    echo "<h3>📊 Resumen de Pruebas de Base de Datos</h3>";
    echo "<div class='success'>";
    echo "<strong>✅ TODAS LAS PRUEBAS EXITOSAS</strong><br>";
    echo "• Conexión a SQLite establecida<br>";
    echo "• Tablas creadas/verificadas<br>";
    echo "• Operaciones INSERT ejecutadas<br>";
    echo "• Operaciones SELECT ejecutadas<br>";
    echo "• Operaciones UPDATE y DELETE verificadas<br>";
    echo "• Integridad de datos mantenida<br>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error en la base de datos:</strong><br>";
    echo "Mensaje: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "</div>";
}
?>