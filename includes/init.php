<?php
require_once 'database.php';

function initializeApplication() {
    $db = new Database();
    
    // Verificar si la base de datos está inicializada
    if (!$db->checkDatabase()) {
        // Mostrar mensaje de error amigable
        if (file_exists(__DIR__ . '/../database/schema.sql')) {
            die("
                <div style='padding: 20px; font-family: Arial; text-align: center;'>
                    <h2>Base de datos no inicializada</h2>
                    <p>Por favor, ejecuta el script de inicialización o importa el schema.sql en DB Browser</p>
                    <p>Archivo de base de datos esperado: database/plataforma_escolar.db</p>
                </div>
            ");
        } else {
            die("Error: Archivos de base de datos no encontrados");
        }
    }
    
    return true;
}
?>