<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('Location: ../../index.html');
    exit;
}

require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 1. Obtener datos completos del padre Y los grados asignados
    // CONSULTA CORREGIDA: Mejorando la relación y asegurando que siempre retorne datos
    $stmt = $conn->prepare("
        SELECT 
            u.dni, 
            u.telefono, 
            u.email, 
            GROUP_CONCAT(g.nombre || ' ' || g.seccion || ' (' || g.nivel || ')', ', ') AS grados_asignados,
            GROUP_CONCAT(g.id) AS grados_ids
        FROM usuarios u 
        LEFT JOIN padres p ON u.id = p.usuario_id 
        LEFT JOIN padre_grado pg ON u.id = pg.usuario_padre_id
        LEFT JOIN grados g ON pg.grado_id = g.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $padre_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($padre_data) {
        $grados_padre = !empty($padre_data['grados_ids']) ? array_map('intval', explode(',', $padre_data['grados_ids'])) : [];
        unset($padre_data['grados_ids']);
        
        // VERIFICACIÓN INDIVIDUAL DE CAMPOS
        if (empty($padre_data['dni']) || $padre_data['dni'] === null) $padre_data['dni'] = 'No disponible';
        if (empty($padre_data['telefono']) || $padre_data['telefono'] === null) $padre_data['telefono'] = 'No disponible';
        if (empty($padre_data['email']) || $padre_data['email'] === null) $padre_data['email'] = $_SESSION['email'] ?? 'No disponible';
        if (empty($padre_data['grados_asignados']) || $padre_data['grados_asignados'] === null) $padre_data['grados_asignados'] = 'No hay grados asignados';
        
    } else {
        // Consulta alternativa - solo datos básicos del usuario
        $stmt_basic = $conn->prepare("
            SELECT dni, telefono, email
            FROM usuarios 
            WHERE id = ?
        ");
        $stmt_basic->execute([$_SESSION['user_id']]);
        $padre_basic = $stmt_basic->fetch(PDO::FETCH_ASSOC);
        
        if ($padre_basic) {
            $padre_data = [
                'dni' => $padre_basic['dni'] ?? 'No disponible',
                'telefono' => $padre_basic['telefono'] ?? 'No disponible',
                'email' => $padre_basic['email'] ?? $_SESSION['email'] ?? 'No disponible',
                'grados_asignados' => 'No hay grados asignados'
            ];
        } else {
            // Fallback final
            $padre_data = [
                'dni' => 'No disponible',
                'telefono' => 'No disponible', 
                'email' => $_SESSION['email'] ?? 'No disponible',
                'grados_asignados' => 'No hay grados asignados'
            ];
        }
        $grados_padre = [];
    }

    // El resto del código para tareas, eventos, etc. permanece igual...
    $tareas_pendientes = 0;
    $tareas_completadas = 0;
    $dias_proximo_evento = 0;
    $comunicados_nuevos = 0;
    $tareas_recientes = [];
    $eventos = [];

    $fechas_tareas = [];
    if (!empty($grados_padre)) {
        $placeholders = str_repeat('?,', count($grados_padre) - 1) . '?';
         $stmt_fechas_tareas = $conn->prepare("
            SELECT DISTINCT fecha_entrega
            FROM tareas 
            WHERE grado_id IN ($placeholders)
            AND fecha_entrega IS NOT NULL
        ");
        $stmt_com_nuevos = $conn->prepare("
            SELECT COUNT(c.id) 
            FROM comunicados c 
            WHERE (c.grado_id IS NULL OR c.grado_id IN ($placeholders)) 
            AND c.fecha_publicacion >= datetime('now', '-2 days')
        ");
        $stmt_com_nuevos->execute($grados_padre);
        $comunicados_nuevos = $stmt_com_nuevos->fetchColumn();
        $stmt_fechas_tareas->execute($grados_padre);
        $fechas_tareas = $stmt_fechas_tareas->fetchAll(PDO::FETCH_COLUMN);

        // Tareas Recientes
        $stmt_recientes = $conn->prepare("
            SELECT t.titulo, t.descripcion, t.fecha_entrega, 
                m.nombre AS materia_nombre, 
                g.nombre AS grado_nombre, g.seccion,
                u.nombres AS docente_nombres, u.apellidos AS docente_apellidos,
                CASE 
                    WHEN t.fecha_entrega < date('now') THEN 'vencida'
                    WHEN t.fecha_entrega = date('now') THEN 'hoy'
                    ELSE 'pendiente'
                END as estado_texto
            FROM tareas t
            INNER JOIN materias m ON t.materia_id = m.id
            INNER JOIN grados g ON t.grado_id = g.id
            INNER JOIN docentes d ON t.docente_id = d.id
            INNER JOIN usuarios u ON d.usuario_id = u.id
            WHERE t.grado_id IN ($placeholders)
            ORDER BY t.fecha_entrega ASC
            LIMIT 5
        ");
        $stmt_recientes->execute($grados_padre);
        $tareas_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular tareas pendientes y completadas
        $stmt_total = $conn->prepare("
            SELECT COUNT(t.id) 
            FROM tareas t 
            WHERE t.grado_id IN ($placeholders)
        ");
        $stmt_total->execute($grados_padre);
        $tareas_totales = $stmt_total->fetchColumn();
        
        // Para simplificar, asumimos que todas las tareas están pendientes
        // En una implementación real, aquí verificarías el estado de entrega
        $tareas_pendientes = $tareas_totales;
        $tareas_completadas = 0; // Por ahora en 0, ya que no tenemos tabla de entregas
        $fechas_tareas_js = [];
        foreach ($fechas_tareas as $fecha) {
            $fechas_tareas_js[] = date('Y-m-d', strtotime($fecha));
        }
    }
    
} catch (Exception $e) {
    error_log("Error de Base de Datos en index.php (Padre): " . $e->getMessage());
    $error_msg = "Error DB: " . htmlspecialchars($e->getMessage());

    $padre_data = [
        'dni' => $error_msg,
        'telefono' => $error_msg, 
        'grados_asignados' => $error_msg,
        'email' => $_SESSION['email'] ?? 'No disponible',
    ];
    $tareas_pendientes = 0;
    $tareas_completadas = 0;
    $dias_proximo_evento = 0;
    $comunicados_nuevos = 0;
    $tareas_recientes = [];
    $eventos = [];
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
    <title>Portal Padre - I.E Juan Pablo Vizcardo y Guzmán</title>
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
        
        .calendar-day.has-event {
            border-color: #e74c3c;
            background: #fff5f5;
        }
        
        .calendar-day.has-event::after {
            content: '';
            width: 6px;
            height: 6px;
            background: #e74c3c;
            border-radius: 50%;
            margin: 2px auto 0;
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

        /* NUEVO: Estilos para días con tareas */
        .calendar-day.has-task {
            border-color: #f39c12;
            background: #fff9e6;
        }

        .calendar-day.has-task::after {
            content: '';
            width: 6px;
            height: 6px;
            background: #f39c12;
            border-radius: 50%;
            margin: 2px auto 0;
        }

        .task-dot {
            width: 6px;
            height: 6px;
            background: #f39c12;
            border-radius: 50%;
            margin: 2px auto 0;
        }

        /* Si un día tiene tanto evento como tarea */
        .calendar-day.has-event.has-task {
            border-color: #e67e22;
            background: #fff4e6;
        }

        .calendar-day.today.has-task {
            background: linear-gradient(135deg, #3498db 0%, #3498db 70%, #f39c12 70%, #f39c12 100%) !important;
        }

        .calendar-day.today.has-event.has-task {
            background: linear-gradient(135deg, #3498db 0%, #3498db 50%, #e74c3c 50%, #e74c3c 70%, #f39c12 70%, #f39c12 100%) !important;
        }
        
        .event-dot {
            width: 6px;
            height: 6px;
            background: #e74c3c;
            border-radius: 50%;
            margin: 2px auto 0;
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
                <p>I.E Juan Pablo Vizcardo y Guzmán.</p>
            </div>
            
            <div class="sidebar-nav" id="sidebarNav">
                <div class="nav-item active" onclick="loadModule('dashboard', this)">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" onclick="loadModule('tareas', this)">
                    <span class="nav-icon">📝</span>
                    <span>Tareas</span>
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
                    <div class="user-avatar">👨</div>
                    <div class="user-details">
                        <div class="user-name" id="userName"><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></div>
                        <div class="user-role" id="userRole">Padre de Familia</div>
                    </div>
                </div>
                <button class="btn-logout" onclick="handleLogout()">🚪 Cerrar Sesión</button>
            </div>
        </div>
        
        <div class="main-content">
            
            <div class="content-area">
                <div id="dashboard" class="module-content active">
                    <div class="stats-grid">
                        <div class="stat-card warning">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-number" id="tareasPendientes"><?php echo $tareas_pendientes; ?></div>
                            <div class="stat-label">Tareas Pendientes</div>
                        </div>
                                                
                        <div class="stat-card purple">
                            <div class="stat-icon">📆</div>
                            <div class="stat-number" id="diasProximoEvento"><?php echo $dias_proximo_evento; ?></div>
                            <div class="stat-label">Días - Próximo Evento</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📨</div>
                            <div class="stat-number" id="mensajesNuevos"><?php echo $comunicados_nuevos; ?></div>
                            <div class="stat-label">Mensajes Nuevos</div>
                        </div>
                    </div>
                    
                    <h3 style="color: #2c3e50; margin-bottom: 20px; font-size: 20px;">📌 Tareas Recientes</h3>
                    <div class="task-list" id="tareasRecientes">
                        <?php if (!empty($tareas_recientes)): ?>
                            <?php foreach($tareas_recientes as $tarea): ?>
                                <div class="task-item <?php echo $tarea['estado_texto'] === 'vencida' ? 'error' : 'pending'; ?>">
                                    <div class="task-info">
                                        <h3>📚 <?php echo htmlspecialchars($tarea['materia_nombre']); ?> - <?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                                        <p><?php echo htmlspecialchars($tarea['descripcion']); ?></p>
                                        <div class="task-meta">
                                            📅 Vence: <?php echo date('d M Y', strtotime($tarea['fecha_entrega'])); ?> | 
                                            👨‍🏫 Prof. <?php echo htmlspecialchars($tarea['docente_nombres'] . ' ' . $tarea['docente_apellidos']); ?> |
                                            🎓 Grado: <?php echo htmlspecialchars($tarea['grado_nombre'] . ' ' . $tarea['seccion']); ?>
                                        </div>
                                    </div>
                                    <span class="task-status <?php echo $tarea['estado_texto'] === 'vencida' ? 'status-error' : 'status-pending'; ?>">
                                        <?php echo strtoupper($tarea['estado_texto']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">No hay tareas pendientes</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="tareas" class="module-content">
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #2c3e50; margin-bottom: 15px;">📋 Tareas de los Grados Asignados</h3>
                        <p style="color: #7f8c8d; font-size: 14px;">Lista de todas las tareas publicadas para los grados de sus hijos.</p>
                    </div>
                    
                    <div class="task-list" id="listaTareas">
                        <div class="loading-message">Cargando tareas...</div>
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
                                        <div class="event-item <?php echo $evento['tipo'] === 'urgente' ? 'event-urgent' : ''; ?>">
                                            <div class="event-title"><?php echo htmlspecialchars($evento['titulo']); ?></div>
                                            <div class="event-detail">📅 <?php echo date('d M Y', strtotime($evento['fecha_evento'])); ?></div>
                                            <?php if (!empty($evento['lugar'])): ?>
                                                <div class="event-detail">📍 <?php echo htmlspecialchars($evento['lugar']); ?></div>
                                            <?php endif; ?>
                                            <div class="event-detail">📝 <?php echo htmlspecialchars($evento['descripcion']); ?></div>
                                        </div>
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
                    <div class="message-list" id="listaComunicados">
                        <div class="loading-message">Cargando comunicados...</div>
                    </div>
                </div>
                
                <div id="perfil" class="module-content">
                    <div class="profile-container">
                        <div class="profile-card">
                            <div class="profile-header">
                                <div class="profile-avatar-large">👨</div>
                                <div class="profile-info">
                                    <h2 id="nombrePadre"><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></h2>
                                    <p>Padre de Familia</p>
                                </div>
                            </div>
                            
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Información Personal</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">DNI</div>
                                    <div class="info-value" id="dniPadre"><?php echo htmlspecialchars($padre_data['dni']); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Correo Electrónico</div>
                                    <div class="info-value" id="emailPadre"><?php echo htmlspecialchars($padre_data['email']); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value" id="telefonoPadre"><?php echo htmlspecialchars($padre_data['telefono']); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Grados Asignados</div>
                                    <div class="info-value" id="gradosAsignados"><?php echo htmlspecialchars($padre_data['grados_asignados']); ?></div>
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
        const fechasTareas = <?php echo json_encode($fechas_tareas_js); ?>; // NUEVO: Fechas de tareas

        // Función para cargar módulos
        function loadModule(moduleId, clickedElement) {
            console.log('Cargando módulo:', moduleId); // Para debug
            
            // Ocultar todos los módulos
            document.querySelectorAll('.module-content').forEach(module => {
                module.classList.remove('active');
            });
            
            // Remover activo de todos los items del menú
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Mostrar módulo seleccionado y activar item del menú
            const targetModule = document.getElementById(moduleId);
            if (targetModule) {
                targetModule.classList.add('active');
            }
            
            if (clickedElement) {
                clickedElement.classList.add('active');
            }
            
            // Actualizar título
            const titles = {
                'dashboard': 'Dashboard',
                'tareas': 'Tareas',
                'calendario': 'Calendario',
                'comunicados': 'Comunicados',
                'perfil': 'Mi Perfil'
            };
            
            const titleElement = document.getElementById('moduleTitle');
            if (titleElement) {
                titleElement.textContent = titles[moduleId] || 'Portal Padre';
            }
            
            // Cargar contenido específico del módulo
            if (moduleId === 'calendario') {
                generarCalendario();
            }
            
            // Asegurar que se carguen las tareas
            if (moduleId === 'tareas') {
                console.log('Iniciando carga de tareas...');
                cargarTareas();
            }
            
            // NUEVO MÓDULO: Cargar comunicados
            if (moduleId === 'comunicados') {
                console.log('Iniciando carga de comunicados...');
                cargarComunicados();
            }
        }
        
        // Función de logout
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../../api/auth/logout.php';
            }
        }
        
        // Generar calendario mejorado con resaltado de tareas
        function generarCalendario() {
            const calendarioGrid = document.getElementById('calendarioGrid');
            const diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
            const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const today = new Date();
            
            // Actualizar título del mes
            document.getElementById('mesActual').textContent = `${monthNames[month]} ${year}`;
            
            let html = '';
            
            // Encabezados de días
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day-label">${dia}</div>`;
            });
            
            // Primer día del mes
            const firstDay = new Date(year, month, 1);
            // Días en el mes
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            // Día de la semana del primer día (0 = Domingo, 1 = Lunes, ...)
            const firstDayOfWeek = (firstDay.getDay() + 6) % 7; // Ajuste para que empiece en Lunes
            
            // Días vacíos al inicio
            for (let i = 0; i < firstDayOfWeek; i++) {
                html += `<div class="calendar-day inactive"></div>`;
            }
            
            // Días del mes
            for (let day = 1; day <= daysInMonth; day++) {
                const currentDay = new Date(year, month, day);
                let clase = 'calendar-day';
                
                // Verificar si es hoy
                if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    clase += ' today';
                }
                
                // Verificar si hay eventos en este día
                const tieneEventos = eventosCalendario.some(evento => {
                    const eventoDate = new Date(evento.fecha_evento);
                    return eventoDate.getDate() === day && 
                        eventoDate.getMonth() === month && 
                        eventoDate.getFullYear() === year;
                });
                
                // NUEVO: Verificar si hay tareas para este día
                const fechaStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const tieneTareas = fechasTareas.includes(fechaStr);
                
                if (tieneEventos) {
                    clase += ' has-event';
                }
                
                if (tieneTareas) {
                    clase += ' has-task'; // Nueva clase para días con tareas
                }
                
                html += `<div class="${clase}" onclick="seleccionarDia(${day}, ${month}, ${year})">
                    ${day}
                    ${tieneEventos ? '<div class="event-dot"></div>' : ''}
                    ${tieneTareas ? '<div class="task-dot"></div>' : ''}
                </div>`;
            }
            
            calendarioGrid.innerHTML = html;
        }
        
        function seleccionarDia(dia, mes, ano) {
            const fecha = new Date(ano, mes, dia);
            const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const fechaFormateada = fecha.toLocaleDateString('es-ES', opciones);
            
            // Buscar eventos para este día
            const eventosDia = eventosCalendario.filter(evento => {
                const eventoDate = new Date(evento.fecha_evento);
                return eventoDate.getDate() === dia && 
                    eventoDate.getMonth() === mes && 
                    eventoDate.getFullYear() === ano;
            });
            
            // NUEVO: Buscar tareas para este día
            const fechaStr = `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
            const tieneTareas = fechasTareas.includes(fechaStr);
            
            let mensaje = `📅 ${fechaFormateada}`;
            
            if (eventosDia.length > 0) {
                mensaje += `\n\n📋 Eventos para este día:\n`;
                eventosDia.forEach((evento, index) => {
                    mensaje += `\n${index + 1}. ${evento.titulo}\n   📝 ${evento.descripcion}\n`;
                });
            }
            
            // NUEVO: Informar sobre tareas
            if (tieneTareas) {
                mensaje += `\n\n📚 Tareas:\n`;
                mensaje += `¡Hay tareas que vencen hoy!\n`;
                mensaje += `Revisa la sección de Tareas para más detalles.`;
            }
            
            if (eventosDia.length === 0 && !tieneTareas) {
                mensaje += `\n\nNo hay eventos ni tareas programados para este día.`;
            }
            
            alert(mensaje);
        }
        
        function cambiarMes(direccion) {
            currentDate.setMonth(currentDate.getMonth() + direccion);
            generarCalendario();
        }

        // NUEVA FUNCIÓN: Cargar tareas haciendo la petición al API
        function cargarTareas() {
            const listaTareas = document.getElementById('listaTareas');
            console.log('Iniciando carga de tareas...', listaTareas); // Debug
            
            if (!listaTareas) {
                console.error('No se encontró el elemento listaTareas');
                return;
            }
            
            listaTareas.innerHTML = '<div class="loading-message">Cargando tareas del grado...</div>';
            
            // Llama al endpoint
            fetch('../../api/padre/obtener-tareas.php')
                .then(response => {
                    console.log('Respuesta recibida:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    if (data.success) {
                        actualizarListaTareas(data.tareas);
                    } else {
                        listaTareas.innerHTML = `<div class="no-data">Error al cargar tareas: ${data.message || data.error}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error al obtener tareas:', error);
                    listaTareas.innerHTML = '<div class="no-data">Error de conexión con el servidor: ' + error.message + '</div>';
                });
        }

        // FUNCIÓN MODIFICADA: Renderizar la lista de tareas sin filtros
        function actualizarListaTareas(tareas) {
            const listaTareas = document.getElementById('listaTareas');
            
            if (tareas.length === 0) {
                listaTareas.innerHTML = '<div class="no-data">No hay tareas publicadas para su(s) grado(s) actualmente.</div>';
                return;
            }

            let html = '';
            tareas.forEach(tarea => {
                let textoEstado = tarea.estado_entrega.toUpperCase();
                let colorEstado = '#7f8c8d';

                switch (tarea.estado_entrega) {
                    case 'vencida':
                        colorEstado = '#e74c3c';
                        break;
                    case 'hoy':
                        colorEstado = '#f39c12';
                        break;
                    case 'pendiente':
                        colorEstado = '#3498db';
                        break;
                }

                html += `
                    <div class="task-item">
                        <div class="task-info">
                            <h3>📚 ${tarea.materia_nombre} - ${tarea.titulo}</h3>
                            <p>${tarea.descripcion}</p>
                            <div class="task-meta">
                                📅 Vence: ${new Date(tarea.fecha_entrega).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })} | 
                                👨‍🏫 Prof. ${tarea.docente_nombres} ${tarea.docente_apellidos} | 
                                🎓 Grado: ${tarea.grado_nombre} ${tarea.seccion}
                            </div>
                        </div>
                        <span class="task-status" style="background: ${colorEstado}">${textoEstado}</span>
                    </div>
                `;
            });
            
            listaTareas.innerHTML = html;
        }

        // NUEVA FUNCIÓN: Cargar comunicados haciendo la petición al API
        function cargarComunicados() {
            const listaComunicados = document.getElementById('listaComunicados');

            if (!listaComunicados) {
                console.error('No se encontró el elemento listaComunicados');
                return;
            }

            listaComunicados.innerHTML = '<div class="loading-message">Cargando comunicados...</div>';

            // Llama al endpoint
            fetch('../../api/padre/obtener-comunicados.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        actualizarListaComunicados(data.comunicados);
                    } else {
                        listaComunicados.innerHTML = `<div class="no-data">Error al cargar comunicados: ${data.error}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error al obtener comunicados:', error);
                    listaComunicados.innerHTML = '<div class="no-data">Error de conexión con el servidor: ' + error.message + '</div>';
                });
        }

        // NUEVA FUNCIÓN: Renderizar la lista de comunicados
        function actualizarListaComunicados(comunicados) {
            const listaComunicados = document.getElementById('listaComunicados');

            if (comunicados.length === 0) {
                listaComunicados.innerHTML = '<div class="no-data">No hay comunicados disponibles actualmente.</div>';
                return;
            }

            let html = '';
            comunicados.forEach(comunicado => {
                let clases = 'message-item';
                let badgesHtml = '';

                if (comunicado.urgente == 1) {
                    clases += ' urgent';
                    badgesHtml += '<span class="message-badge badge-urgent">¡URGENTE!</span>';
                }
                if (comunicado.es_nuevo == 1 && comunicado.urgente != 1) { // No marcar como 'nuevo' si ya es 'urgente'
                    clases += ' new';
                    badgesHtml += '<span class="message-badge badge-new">NUEVO</span>';
                }

                // Determinar origen
                const origen = comunicado.grado_nombre 
                    ? `🎓 Grado: ${comunicado.grado_nombre} ${comunicado.seccion} (${comunicado.nivel})`
                    : '🌍 Comunicado General';

                const remitente = comunicado.docente_nombres
                    ? `👨‍🏫 ${comunicado.docente_nombres} ${comunicado.docente_apellidos}`
                    : '🏫 Administración';

                html += `
                    <div class="${clases}">
                        ${badgesHtml}
                        <h3>📢 ${comunicado.titulo}</h3>
                        <p>${comunicado.mensaje}</p>
                        <div class="message-meta">
                            📅 Publicado: ${new Date(comunicado.fecha_publicacion).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })} | 
                            ${origen} |
                            Remitente: ${remitente}
                        </div>
                    </div>
                `;
            });

            listaComunicados.innerHTML = html;
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página cargada, inicializando...');
            
            // Encuentra el elemento activo del menú
            const activeNavItem = document.querySelector('.nav-item.active');
            if (activeNavItem) {
                // Asegura que el dashboard se cargue correctamente al inicio
                loadModule('dashboard', activeNavItem);
            } else {
                // Fallback: activa el primer elemento del menú
                const firstNavItem = document.querySelector('.nav-item');
                if (firstNavItem) {
                    firstNavItem.classList.add('active');
                    loadModule('dashboard', firstNavItem);
                }
            }
        });
    </script>
</body>
</html>