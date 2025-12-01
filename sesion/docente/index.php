<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'docente') {
    header('Location: ../../index.html');
    exit;
}

require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    $usuario_id = $_SESSION['user_id'];
    
    // 1. OBTENER DATOS PERSONALES COMPLETOS DEL DOCENTE (Primer bloque de consulta)
    // ... (El código de la Consulta 1 es correcto y funciona) ...
    $stmt = $conn->prepare("
        SELECT
            IFNULL(u.dni, 'Sin datos') AS dni,
            IFNULL(u.telefono, 'Sin datos') AS telefono,
            IFNULL(u.email, 'Sin datos') AS email,
            u.nombres, 
            u.apellidos,
            IFNULL(
                GROUP_CONCAT(g.nombre || ' ' || g.seccion || ' (' || g.nivel || ')', ', '),\n                'No es tutor de ningún grado'\n            ) AS grados_a_cargo\n        FROM\n            usuarios AS u\n        LEFT JOIN docentes AS d ON u.id = d.usuario_id \n        LEFT JOIN grados AS g ON d.id = g.tutor_id\n        WHERE u.id = ?\n        GROUP BY u.id\n    ");
    $stmt->execute([$_SESSION['user_id']]);
    $docente_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // ... (Lógica de Fallback de $docente_data, se mantiene por seguridad) ...

    // 2. OBTENER GRADOS (Lógica corregida: Tutor O Profesor de materia)
    $stmt = $conn->prepare("
        SELECT DISTINCT g.id, g.nombre, g.seccion 
        FROM grados g 
        LEFT JOIN docente_materia_grado dmg ON g.id = dmg.grado_id
        WHERE 
            g.tutor_id = (SELECT id FROM docentes WHERE usuario_id = ?)
            OR 
            dmg.docente_id = (SELECT id FROM docentes WHERE usuario_id = ?)
        ORDER BY g.nombre, g.seccion ASC
    ");
    
    // Pasamos el ID dos veces (una para cada ?)
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $grados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener lista de materias para el formulario de tareas
    $stmt = $conn->prepare("SELECT id, nombre FROM materias ORDER BY nombre ASC");
    $stmt->execute();
    $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear grados para mostrar en texto (ej: "5to A, 4to B")
    $grados_texto = '';
    if ($grados) {
        $grados_array = array_map(function($grado) {
            return $grado['nombre'] . ' ' . $grado['seccion'];
        }, $grados);
        $grados_texto = implode(', ', $grados_array);
    } else {
        $grados_texto = 'Sin asignación académica';
    }
        
    // ------------------------------------------------------------------
    // ⚠️ INICIO DE BLOQUE AISLADO PARA LA CONSULTA DE EVENTOS
    // Si la tabla 'eventos' no existe, esto inicializará $eventos a [] sin fallar el script.
    $eventos = []; 
    try {
        $stmt = $conn->prepare("
            SELECT titulo, descripcion, fecha_evento, tipo 
            FROM eventos 
            WHERE docente_id = (SELECT id FROM docentes WHERE usuario_id = ?) OR destinatario = 'todos'
            ORDER BY fecha_evento ASC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignoramos el error de la tabla "eventos" (no existe) y procedemos.
        // Si el error es otro, se capturará en el catch principal.
        error_log("Error al cargar eventos (tabla eventos): " . $e->getMessage());
        $eventos = []; 
    }
    // FIN DE BLOQUE AISLADO
    // ------------------------------------------------------------------
    
    // Lógica de tareas para el calendario
    $stmt_docente = $conn->prepare("SELECT id FROM docentes WHERE usuario_id = ?");
    $stmt_docente->execute([$_SESSION['user_id']]);
    $docente_data_id = $stmt_docente->fetch(PDO::FETCH_ASSOC);
    $docente_id = $docente_data_id['id'] ?? 0;

    $fechas_tareas_js = [];
    if ($docente_id) {
        $stmt_fechas_tareas = $conn->prepare("
            SELECT DISTINCT fecha_entrega
            FROM tareas 
            WHERE docente_id = ?
            AND fecha_entrega IS NOT NULL
        ");
        $stmt_fechas_tareas->execute([$docente_id]);
        $fechas_tareas = $stmt_fechas_tareas->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($fechas_tareas as $fecha) {
            $fechas_tareas_js[] = date('Y-m-d', strtotime($fecha));
        }
        
        // Unir tareas al calendario de eventos
        $stmt_tareas = $conn->prepare("
            SELECT 
                t.titulo, 
                t.descripcion, 
                t.fecha_entrega AS fecha_evento, 
                m.nombre AS materia_nombre,
                g.nombre AS grado_nombre,
                g.seccion
            FROM tareas t
            JOIN materias m ON t.materia_id = m.id
            JOIN grados g ON t.grado_id = g.id
            WHERE t.docente_id = ?
            ORDER BY t.fecha_entrega ASC
        ");
        $stmt_tareas->execute([$docente_id]);
        $tareas_calendario = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
        
        $eventos = array_merge($eventos, array_map(function($tarea) {
            $tarea['titulo'] = "TAREA: " . $tarea['titulo'];
            $tarea['descripcion'] = $tarea['descripcion'] . " (Grado: {$tarea['grado_nombre']} {$tarea['seccion']} - Materia: {$tarea['materia_nombre']})";
            $tarea['tipo'] = 'evento_tarea';
            unset($tarea['materia_nombre'], $tarea['grado_nombre'], $tarea['seccion']);
            return $tarea;
        }, $tareas_calendario));
    }
    
} catch (Exception $e) {
    // ------------------------------------------------------------------
    // ⚠️ RE-ACTIVA EL FALLBACK DE DATOS AQUÍ
    // Si la excepción es por la conexión a DB o cualquier otra cosa (no 'eventos'),
    // los datos del perfil seguirán siendo el fallback.

    $docente_data = [
        'dni' => 'No disponible (Error de DB)',
        'telefono' => 'No disponible (Error de DB)', 
        'email' => $_SESSION['email'] ?? 'No disponible (Error de DB)',
        'nombres' => $_SESSION['nombres'] ?? '',
        'apellidos' => $_SESSION['apellidos'] ?? '',
        'grados_a_cargo' => 'No disponible (Error de DB)'
    ];
    $grados_texto = 'No disponible';
    $eventos = [];
    $fechas_tareas_js = [];
}

// Obtener fecha actual para el calendario
$fecha_actual = new DateTime();
$mes_actual = $fecha_actual->format('n');
$ano_actual = $fecha_actual->format('Y');
$dia_actual = $fecha_actual->format('j');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Docente - I.E Juan Pablo Vizcardo y Guzmán</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .calendar-wrapper {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 20px;
        }
        
        .calendar-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-nav {
            display: flex;
            gap: 10px;
        }
        
        .calendar-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .calendar-btn:hover {
            background: #2980b9;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        
        .calendar-day-label {
            text-align: center;
            font-weight: 600;
            color: #7f8c8d;
            padding: 10px;
            font-size: 14px;
        }
        
        .calendar-day {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendar-day:hover {
            background: #e3f2fd;
            border-color: #3498db;
        }
        
        .calendar-day.today {
            background: #3498db !important;
            color: white !important;
            border-color: #3498db !important;
            font-weight: bold;
        }
        
        /* Estilos para días con eventos */
        .calendar-day.has-event {
            border-color: #e74c3c;
            background: #fff5f5;
        }
        
        
        .calendar-day.has-task {
            border-color: #3498db; 
            background: #f0f8ff;
        }
        
        .calendar-day.has-event.has-task {
            border-color: #9b59b6;
            background: #f8f0ff;
        }
        
        .calendar-day.inactive {
            background: #f8f9fa;
            color: #bdc3c7;
            cursor: not-allowed;
        }
        
        .calendar-day.inactive:hover {
            background: #f8f9fa;
            border-color: #e9ecef;
        }
        
        /* Contenedor de puntos para múltiples indicadores */
        .dot-container {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: 5px;
        }

        /* Puntos de Evento */
        .event-dot {
            width: 8px;
            height: 8px;
            background: #e74c3c;
            border-radius: 50%;
            display: block;
        }
        
        .task-dot {
            width: 8px;
            height: 8px;
            background: #3498db;
            border-radius: 50%;
            display: block;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .event-item {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.2s ease;
        }
        
        .event-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .event-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .event-detail {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .event-urgent {
            border-left-color: #e74c3c;
        }
        
        /* Estilos para mensajes y comunicados */
        .message-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-item {
            background: white;
            border-left: 4px solid #3498db;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            position: relative;
        }

        .message-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .message-item.urgent {
            border-left-color: #e74c3c;
        }

        .message-item.new {
            border-left-color: #2ecc71;
        }

        .message-item h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .message-meta {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 10px;
            border-top: 1px dashed #ecf0f1;
            padding-top: 10px;
        }

        .message-badge {
            position: absolute;
            top: 10px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .badge-new { background: #2ecc71; }
        .badge-urgent { background: #e74c3c; }
        
        @media (max-width: 768px) {
            .calendar-wrapper {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-app active" id="mainApp">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>📚 Portal Educativo</h3>
                <p>I.E Juan Pablo Vizcardo y Guzmán</p>
            </div>
            
            <div class="sidebar-nav" id="sidebarNav">
                <div class="nav-item active" onclick="loadModule('tareas', this)">
                    <span class="nav-icon">📝</span>
                    <span>Tareas</span>
                </div>
                <div class="nav-item" onclick="loadModule('crear-tarea', this)">
                    <span class="nav-icon">➕</span>
                    <span>Crear Tarea</span>
                </div>
                <div class="nav-item" onclick="loadModule('calendario', this)">
                    <span class="nav-icon">📅</span>
                    <span>Calendario</span>
                </div>
                <div class="nav-item" onclick="loadModule('comunicados', this)">
                    <span class="nav-icon">📢</span>
                    <span>Comunicados</span>
                </div>
                <div class="nav-item" onclick="loadModule('perfil', this)">
                    <span class="nav-icon">👤</span>
                    <span>Mi Perfil</span>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">👩‍🏫</div>
                    <div class="user-details">
                        <div class="user-name" id="userName"><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></div>
                        <div class="user-role" id="userRole">Docente</div>
                    </div>
                </div>
                <button class="btn-logout" onclick="handleLogout()">🚪 Cerrar Sesión</button>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <h2 id="moduleTitle">Dashboard</h2>
                    <div class="breadcrumb" id="breadcrumb">Docente activo - <?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></div>
                </div>
            </div>
            
            <div class="content-area">
                
                <div id="tareas" class="module-content active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap;">
                        <h3 style="color: #2c3e50; margin: 0;">📝 Mis Tareas Creadas</h3>
                        <button class="btn-login" onclick="cargarTareas()">🔄 Actualizar Lista</button>
                    </div>
                    
                    <div id="loadingTareas" style="text-align: center; padding: 20px; display: none;">
                        <div style="color: #3498db; font-size: 16px;">Cargando tareas...</div>
                    </div>
                    
                    <div id="listaTareasReal" class="task-list">
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📚</div>
                            <p>No hay tareas creadas aún.</p>
                        </div>
                    </div>
                </div>
                
                <div id="crear-tarea" class="module-content">
                    <div class="profile-card" style="max-width: 800px;">
                        <h3 style="color: #2c3e50; margin-bottom: 25px;">➕ Crear Nueva Tarea</h3>
                        <form id="formCrearTarea">
                            <div class="form-group">
                                <label>Grado y Sección</label>
                                <select id="gradoTarea" required>
                                    <option value="">Seleccionar grado...</option>
                                    <?php if (!empty($grados)): ?>
                                        <?php foreach ($grados as $grado): ?>
                                            <option value="<?php echo htmlspecialchars($grado['id'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($grado['nombre'] . ' ' . $grado['seccion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay grados asignados</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Materia/Curso</label>
                                <select id="materiaTarea" required>
                                    <option value="">Seleccionar materia...</option>
                                    <?php if (!empty($materias)): ?>
                                        <?php foreach ($materias as $materia): ?>
                                            <option value="<?php echo htmlspecialchars($materia['id'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($materia['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay materias disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Título de la Tarea</label>
                                <input type="text" id="tituloTarea" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea id="descripcionTarea" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; min-height: 120px; font-family: inherit;" placeholder="Describe las instrucciones de la tarea..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de Entrega</label>
                                <input type="date" id="fechaTarea" required>
                            </div>
                            
                            <button id="btnPublicarTarea" type="button" class="btn-login" style="margin-top: 20px;" onclick="crearTarea()">📤 Publicar Tarea</button>
                        </form>
                    </div>
                </div>
                
                <div id="calendario" class="module-content">
                    <div class="calendar-wrapper">
                        <div class="calendar-box">
                            <div class="calendar-header">
                                <h3 id="mesActual"><?php echo date('F Y'); ?></h3>
                                <div class="calendar-nav">
                                    <button class="calendar-btn" onclick="cambiarMes(-1)">◄</button>
                                    <button class="calendar-btn" onclick="cambiarMes(1)">►</button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid" id="calendarioGrid">
                                </div>
                        </div>
                        
                        <div class="calendar-box">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📅 Próximos Eventos</h3>
                            <div class="events-list" id="listaEventos">
                                <?php if (!empty($eventos)): ?>
                                    <?php foreach($eventos as $evento): ?>
                                        <?php if (!isset($evento['tipo']) || $evento['tipo'] !== 'evento_tarea'): ?>
                                            <div class="event-item <?php echo $evento['tipo'] === 'urgente' ? 'event-urgent' : ''; ?>">
                                                <div class="event-title"><?php echo htmlspecialchars($evento['titulo']); ?></div>
                                                <div class="event-detail">📅 <?php echo date('d M Y', strtotime($evento['fecha_evento'])); ?></div>
                                                <div class="event-detail">📝 <?php echo htmlspecialchars($evento['descripcion']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="event-item">
                                        <div class="event-title">No hay eventos próximos</div>
                                        <div class="event-detail">No hay eventos programados para los próximos días</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="comunicados" class="module-content">
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #2c3e50; margin-bottom: 15px;">📢 Comunicados</h3>
                        <p style="color: #7f8c8d; font-size: 14px;">Gestión de comunicados para padres de familia.</p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <button class="calendar-btn" onclick="showComunicadoView('listado')">📋 Ver Comunicados Enviados</button>
                        <button class="calendar-btn" onclick="showComunicadoView('enviar')">📤 Enviar Nuevo Comunicado</button>
                    </div>
                    
                    <div id="vista-listado-comunicados" style="display: block;">
                        <h4 style="color: #2c3e50; margin-bottom: 15px;">Lista de Comunicados Enviados</h4>
                        <div class="message-list" id="listaComunicadosEnviados">
                            <div style="text-align: center; padding: 20px; color: #7f8c8d;">
                                <p>Cargando comunicados...</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="vista-enviar-comunicado" style="display: none;">
                        <div class="profile-card" style="max-width: 800px;">
                            <h4 style="color: #2c3e50; margin-bottom: 25px;">📨 Enviar Nuevo Comunicado</h4>
                            <form id="formComunicado">
                                <div class="form-group">
                                    <label>Destinatarios</label>
                                    <select id="destinatariosComunicado" required>
                                        <option value="0">Todos mis grados (General)</option>
                                        <?php 
                                        if (!empty($grados)) {
                                            foreach ($grados as $grado) {
                                                echo "<option value='{$grado['id']}'>Todos los Padres de {$grado['nombre']} {$grado['seccion']}</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Asunto del Comunicado</label>
                                    <input type="text" id="asuntoComunicado" placeholder="Ej: Reunión de padres - 3er bimestre" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Mensaje</label>
                                    <textarea id="mensajeComunicado" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; min-height: 180px; font-family: inherit;" placeholder="Escribe el contenido del comunicado..." required></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                        <input type="checkbox" id="urgenteComunicado">
                                        <span>Marcar como urgente</span>
                                    </label>
                                </div>
                            
                                <button type="button" class="btn-login" style="margin-top: 20px;" onclick="enviarComunicado()">📤 Enviar Comunicado</button>
                                <div id="comunicado-message" class="alert-message mt-2"></div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="perfil" class="module-content">
                    <div class="profile-container">
                        <div class="profile-card" style="width: 100%; max-width: 100%;">
                            <div class="profile-header">
                                <div class="profile-avatar-large">👩‍🏫</div>
                                <div class="profile-info">
                                    <h2 id="nombreDocente">
                                        <?php 
                                        // Usa los datos obtenidos o el fallback si no estaban en DB
                                        $nombre_completo = ($docente_data['nombres'] ?? $_SESSION['nombres']) . ' ' . ($docente_data['apellidos'] ?? $_SESSION['apellidos']);
                                        echo htmlspecialchars(trim($nombre_completo));
                                        ?>
                                    </h2>
                                    <p>Docente</p>
                                </div>
                            </div>
                            
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Información del Docente</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">DNI</div>
                                    <div class="info-value" id="dniDocente">
                                        <?php echo htmlspecialchars($docente_data['dni'] ?? 'No registrado'); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Correo Electrónico</div>
                                    <div class="info-value" id="emailDocente">
                                        <?php 
                                        // Prioriza el email de la DB, si no existe usa el de sesión (el ?? es redundante si $docente_data existe, pero es seguro)
                                        echo htmlspecialchars($docente_data['email'] ?? $_SESSION['email'] ?? 'No disponible'); 
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value" id="telefonoDocente">
                                        <?php echo htmlspecialchars($docente_data['telefono'] ?? 'No registrado'); ?>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Grados a Cargo</div>
                                    <div class="info-value" id="gradosDocente">
                                        <?php 
                                        echo htmlspecialchars($docente_data['grados_a_cargo'] ?? 'No disponible');
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // Variables globales para el calendario
    let currentDate = new Date(<?php echo $ano_actual; ?>, <?php echo $mes_actual - 1; ?>, 1);
    const eventosCalendario = <?php echo json_encode($eventos); ?>;
    const fechasTareas = <?php echo json_encode($fechas_tareas_js); ?>;

    // Función para cambiar entre vistas de comunicados
    function showComunicadoView(view) {
        const listado = document.getElementById('vista-listado-comunicados');
        const enviar = document.getElementById('vista-enviar-comunicado');
        
        if (view === 'listado') {
            listado.style.display = 'block';
            enviar.style.display = 'none';
            cargarComunicados();
        } else if (view === 'enviar') {
            listado.style.display = 'none';
            enviar.style.display = 'block';
            document.getElementById('comunicado-message').innerHTML = '';
        }
    }

    // Función para cargar módulos
    function loadModule(moduleId, clickedElement) {
        document.querySelectorAll('.module-content').forEach(module => {
            module.classList.remove('active');
        });
        
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        document.getElementById(moduleId).classList.add('active');
        clickedElement.classList.add('active');
        
        const titles = {
            'tareas': 'Tareas',
            'crear-tarea': 'Crear Nueva Tarea',
            'calendario': 'Calendario',
            'comunicados': 'Comunicados',
            'perfil': 'Mi Perfil'
        };
        document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Portal Docente';
        
        if (moduleId === 'calendario') {
            generarCalendario();
        }
        
        if (moduleId === 'tareas') {
            cargarTareas();
        }
        
        if (moduleId === 'comunicados') {
            showComunicadoView('listado');
        }
    }

    // Función de logout
    function handleLogout() {
        if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
            window.location.href = '../../api/auth/logout.php';
        }
    }

    function generarCalendario() {
        const calendarioGrid = document.getElementById('calendarioGrid');
        const diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        console.log('Array de Fechas de Tareas (fechasTareas):', fechasTareas);

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        document.getElementById('mesActual').textContent = `${monthNames[month]} ${year}`;
        
        let html = '';
        
        diasSemana.forEach(dia => {
            html += `<div class="calendar-day-label">${dia}</div>`;
        });
        
        const firstDay = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDayOfWeek = (firstDay.getDay() + 6) % 7;
        
        for (let i = 0; i < firstDayOfWeek; i++) {
            html += `<div class="calendar-day inactive"></div>`;
        }
        
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDay = new Date(year, month, day);
            currentDay.setHours(0, 0, 0, 0);
            let clase = 'calendar-day';
            
            if (currentDay.getTime() === today.getTime()) {
                clase += ' today';
            }
            
            const tieneEventos = eventosCalendario.some(evento => {
                const eventoDate = new Date(evento.fecha_evento);
                eventoDate.setHours(0, 0, 0, 0);
                return eventoDate.getTime() === currentDay.getTime() && evento.tipo !== 'evento_tarea';
            });

            const fechaStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const tieneTareas = fechasTareas.includes(fechaStr);

            if (tieneTareas) {
                console.log(`✅ TAREA ENCONTRADA EN: ${fechaStr}`);
                clase += ' has-task'; 
            }
            
            if (tieneEventos) {
                clase += ' has-event';
            }
            
            if (tieneTareas && tieneEventos) {
                clase += ' has-event has-task';
            }
            
            let dotsHtml = '';
            if (tieneTareas) {
                dotsHtml += '<div class="task-dot"></div>'; 
            }
            if (tieneEventos) {
                dotsHtml += '<div class="event-dot"></div>'; 
            }
            
            html += `<div class="${clase}" onclick="seleccionarDia(${day}, ${month}, ${year})">
                ${day}
                <div class="dot-container">
                    ${dotsHtml}
                </div>
            </div>`;
        }
        
        calendarioGrid.innerHTML = html;
    }

    function seleccionarDia(dia, mes, ano) {
        // Establecer la fecha del día clicado a medianoche (00:00:00)
        const fecha = new Date(ano, mes, dia);
        fecha.setHours(0, 0, 0, 0);
        
        const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const fechaFormateada = fecha.toLocaleDateString('es-ES', opciones);
        
        // Filtrar eventos y tareas para el día seleccionado
        const eventosDia = eventosCalendario.filter(evento => {
            const eventoDate = new Date(evento.fecha_evento);
            // También establecer la fecha del evento a medianoche para una comparación precisa
            eventoDate.setHours(0, 0, 0, 0); 
            return eventoDate.getTime() === fecha.getTime();
        });
        
        let mensaje = `📅 ${fechaFormateada}\n\n`;
        
        if (eventosDia.length > 0) {
            mensaje += `📋 Programación para este día:\n`;
            
            eventosDia.forEach((evento, index) => {
                let tipoLabel = '';
                let descripcionDetallada = evento.descripcion;

                if (evento.tipo === 'evento_tarea') {
                    tipoLabel = '📚 TAREA (Entrega)';
                    // La descripción de la tarea ya tiene el Grado/Materia en el PHP, es solo mostrarla
                } else if (evento.tipo === 'urgente') {
                    tipoLabel = '🚨 EVENTO URGENTE';
                } else {
                    tipoLabel = '📢 EVENTO';
                }
                
                mensaje += `\n- ${tipoLabel}: ${evento.titulo}\n  📝 Detalle: ${descripcionDetallada}\n`;
            });
        } else {
            mensaje += `No hay eventos ni tareas programados para este día.`;
        }
        
        alert(mensaje);
    }

    function cambiarMes(direccion) {
        currentDate.setMonth(currentDate.getMonth() + direccion);
        generarCalendario();
    }

    // Función para crear tarea
    async function crearTarea() {
        const form = document.getElementById('formCrearTarea');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const gradoId = document.getElementById('gradoTarea').value;
        const materiaId = document.getElementById('materiaTarea').value;
        const titulo = document.getElementById('tituloTarea').value;
        const descripcion = document.getElementById('descripcionTarea').value;
        const fechaEntrega = document.getElementById('fechaTarea').value;

        if (!gradoId || !materiaId) {
            alert('Por favor seleccione un grado y una materia');
            return;
        }

        const today = new Date().toISOString().split('T')[0];
        if (fechaEntrega < today) {
            alert('La fecha de entrega no puede ser en el pasado');
            return;
        }

        const payload = {
            titulo: titulo,
            descripcion: descripcion,
            grado_id: parseInt(gradoId),
            materia_id: parseInt(materiaId),
            fecha_entrega: fechaEntrega
        };

        const btn = document.getElementById('btnPublicarTarea');
        
        try {
            btn.disabled = true;
            btn.textContent = 'Publicando...';

            const response = await fetch('../../api/docente/guardar-tarea.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('✅ Tarea creada exitosamente');
                form.reset();
                
                // Recargar las tareas si el módulo está activo
                if (document.getElementById('tareas').classList.contains('active')) {
                    cargarTareas();
                }
                
                location.reload(); // Recarga para actualizar el calendario y evitar problemas
                
            } else {
                alert('❌ Error: ' + (data.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión con el servidor');
        } finally {
            btn.disabled = false;
            btn.textContent = '📤 Publicar Tarea';
        }
    }

    // Función para cargar tareas
    async function cargarTareas() {
        const loadingDiv = document.getElementById('loadingTareas');
        const listaTareas = document.getElementById('listaTareasReal');
        
        loadingDiv.style.display = 'block';
        listaTareas.innerHTML = '<div style="text-align: center; padding: 20px; color: #7f8c8d;">Cargando tareas...</div>';
        
        try {
            const response = await fetch('../../api/docente/obtener-tareas.php');
            const data = await response.json();
            
            if (data.success) {
                actualizarListaTareas(data.tareas);
            } else {
                listaTareas.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #e74c3c;">
                        <div style="font-size: 48px; margin-bottom: 10px;">❌</div>
                        <p>Error al cargar las tareas: ${data.message || 'Error desconocido'}</p>
                        <button class="calendar-btn" onclick="cargarTareas()">🔄 Reintentar</button>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error:', error);
            listaTareas.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #e74c3c;">
                    <div style="font-size: 48px; margin-bottom: 10px;">🔌</div>
                    <p>Error de conexión con el servidor</p>
                    <button class="calendar-btn" onclick="cargarTareas()">🔄 Reintentar</button>
                </div>
            `;
        } finally {
            loadingDiv.style.display = 'none';
        }
    }

    // Función para actualizar la lista de tareas
    function actualizarListaTareas(tareas) {
        const listaTareas = document.getElementById('listaTareasReal');
        
        if (!tareas || tareas.length === 0) {
            listaTareas.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📚</div>
                    <p>No hay tareas creadas aún.</p>
                    <button class="calendar-btn" onclick="loadModule('crear-tarea', document.querySelector('[onclick*=\"crear-tarea\"]'))">
                        ➕ Crear Mi Primera Tarea
                    </button>
                </div>
            `;
            return;
        }
        
        let html = '';
        tareas.forEach(tarea => {
            const fechaCreacion = new Date(tarea.fecha_creacion).toLocaleDateString('es-ES');
            const fechaEntrega = new Date(tarea.fecha_entrega).toLocaleDateString('es-ES');
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const fechaEntregaObj = new Date(tarea.fecha_entrega);
            fechaEntregaObj.setHours(0, 0, 0, 0);
            
            const oneDay = 1000 * 60 * 60 * 24;
            const diffTime = fechaEntregaObj.getTime() - hoy.getTime();
            const diasRestantes = Math.ceil(diffTime / oneDay);
            
            let estado = 'pending';
            let textoEstado = 'PENDIENTE';
            let colorEstado = '#f39c12';
            
            if (diasRestantes < 0) {
                estado = 'expired';
                textoEstado = 'VENCIDA';
                colorEstado = '#e74c3c';
            } else if (diasRestantes === 0) {
                textoEstado = 'HOY';
                colorEstado = '#e67e22';
            } else if (diasRestantes <= 3) {
                textoEstado = `URGENTE (${diasRestantes}d)`;
                colorEstado = '#e74c3c';
            } else {
                textoEstado = `Faltan ${diasRestantes} días`;
                colorEstado = '#3498db';
            }
            
            html += `
            <div class="task-item ${estado}">
                <div class="task-info">
                    <h3>${tarea.materia_nombre} - ${tarea.titulo}</h3>
                    <p>${tarea.descripcion || 'Sin descripción adicional'}</p>
                    <div class="task-meta">
                        📅 Creada: ${fechaCreacion} | 
                        🎯 Entrega: ${fechaEntrega} | 
                        🏫 ${tarea.grado_nombre} ${tarea.seccion} | 
                        📚 ${tarea.materia_nombre}
                    </div>
                </div>
                <span class="task-status" style="background: ${colorEstado}">${textoEstado}</span>
            </div>
            `;
        });
        
        listaTareas.innerHTML = html;
    }

    // Funciones para comunicados
    async function enviarComunicado() {
        const titulo = document.getElementById('asuntoComunicado').value.trim();
        const mensaje = document.getElementById('mensajeComunicado').value.trim();
        const grado_id = document.getElementById('destinatariosComunicado').value;
        const urgente = document.getElementById('urgenteComunicado').checked;
        
        let messageElement = document.getElementById('comunicado-message');

        if (!titulo || !mensaje) {
            messageElement.innerHTML = '<span style="color: #c0392b;">El asunto y el mensaje son obligatorios.</span>';
            return;
        }

        messageElement.innerHTML = '<span style="color: #2980b9;">Enviando comunicado...</span>';
        
        try {
            const response = await fetch('/api/docente/guardar-comunicado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    titulo: titulo,
                    mensaje: mensaje,
                    grado_id: parseInt(grado_id),
                    urgente: urgente ? 1 : 0
                })
            });

            const data = await response.json();

            if (data.success) {
                messageElement.innerHTML = `<span style="color: #27ae60;">${data.message}</span>`;
                document.getElementById('formComunicado').reset();
                setTimeout(() => {
                    showComunicadoView('listado'); 
                }, 1500);
            } else {
                messageElement.innerHTML = `<span style="color: #c0392b;">Error al enviar: ${data.message}</span>`;
            }

        } catch (error) {
            console.error('Error:', error);
            messageElement.innerHTML = '<span style="color: #c0392b;">Error de conexión con el servidor.</span>';
        }
    }

    async function cargarComunicados() {
        const messageListContainer = document.getElementById('listaComunicadosEnviados');
        messageListContainer.innerHTML = '<p style="text-align: center; color: #7f8c8d;">Cargando lista de comunicados...</p>';

        try {
            const response = await fetch('/api/docente/obtener-comunicados.php');
            const data = await response.json();

            if (!data.success) {
                messageListContainer.innerHTML = `<p style="color: #c0392b; text-align: center;">Error al cargar: ${data.message}</p>`;
                return;
            }

            if (data.comunicados.length === 0) {
                messageListContainer.innerHTML = '<p style="text-align: center; color: #7f8c8d;">No ha enviado comunicados aún.</p>';
                return;
            }

            let html = '';
            data.comunicados.forEach(comunicado => {
                const fechaPublicacion = new Date(comunicado.fecha_publicacion).toLocaleDateString('es-ES', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric', 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                
                let destinatario = 'Todos mis grados';
                if (comunicado.grado_nombre) {
                    destinatario = `Padres de ${comunicado.grado_nombre} ${comunicado.seccion}`;
                }

                const urgenteTag = comunicado.urgente == 1 
                    ? '<span class="message-badge badge-urgent">¡URGENTE!</span>'
                    : '';

                html += `
                    <div class="message-item ${comunicado.urgente == 1 ? 'urgent' : ''}">
                        ${urgenteTag}
                        <h3>${comunicado.titulo}</h3>
                        <p>${comunicado.mensaje}</p>
                        <div class="message-meta">
                            📅 Enviado: ${fechaPublicacion} | 
                            👥 ${destinatario}
                        </div>
                    </div>
                `;
            });
            
            messageListContainer.innerHTML = html;

        } catch (error) {
            console.error('Error al obtener comunicados:', error);
            messageListContainer.innerHTML = '<p style="color: #c0392b; text-align: center;">No se pudo conectar con la API de comunicados.</p>';
        }
    }

    // Inicialización al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.nav-item').classList.add('active');
        loadModule('tareas', document.querySelector('.nav-item.active'));
        
        const fechaInput = document.getElementById('fechaTarea');
        if (fechaInput) {
            const today = new Date().toISOString().split('T')[0];
            fechaInput.min = today;
            fechaInput.value = today;
        }
    });
</script>
</body>
</html>