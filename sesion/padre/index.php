<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('Location: ../../index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Padre</title>
</head>
<body>
    <h1>¡Bienvenido Padre!</h1>
    <p>Usuario: <?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></p>
    <a href="../../api/auth/logout.php">Cerrar Sesión</a>
</body>
</html>