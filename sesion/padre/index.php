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
    
    // 1. Obtener datos completos del padre
    $stmt = $conn->prepare("
        SELECT p.dni, p.telefono, p.direccion, u.email, u.fecha_registro
        FROM padres p 
        INNER JOIN usuarios u ON p.usuario_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $padre_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Obtener estudiantes asociados al padre
    $stmt = $conn->prepare("
        SELECT e.id as estudiante_id, u.nombres, u.apellidos, 
               g.nombre as grado, g.seccion,
               tu.nombres as tutor_nombre, tu.apellidos as tutor_apellidos
        FROM estudiantes e 
        INNER JOIN usuarios u ON e.usuario_id = u.id 
        INNER JOIN grados g ON e.grado_id = g.id 
        LEFT JOIN profesores pt ON g.tutor_id = pt.id 
        LEFT JOIN usuarios tu ON pt.usuario_id = tu.id 
        INNER JOIN padre_estudiante pe ON e.id = pe.estudiante_id 
        INNER JOIN padres p ON pe.padre_id = p.id 
        WHERE p.usuario_id = ?
        ORDER BY g.nombre, u.nombres
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear información del estudiante principal (primer estudiante)
    $estudiante_principal = '';
    $grado_estudiante = '';
    $tutor_estudiante = '';
    
    if ($estudiantes && count($estudiantes) > 0) {
        $primer_estudiante = $estudiantes[0];
        $estudiante_principal = $primer_estudiante['nombres'] . ' ' . $primer_estudiante['apellidos'];
        $grado_estudiante = $primer_estudiante['grado'] . ' ' . $primer_estudiante['seccion'];
        $tutor_estudiante = $primer_estudiante['tutor_nombre'] . ' ' . $primer_estudiante['tutor_apellidos'];
    } else {
        $estudiante_principal = 'No asignado';
        $grado_estudiante = 'No asignado';
        $tutor_estudiante = 'No asignado';
    }
    
    // 3. Obtener estadísticas de tareas del estudiante principal
    $tareas_pendientes = 0;
    $tareas_completadas = 0;
    
    if ($estudiantes && count($estudiantes) > 0) {
        $estudiante_id = $estudiantes[0]['estudiante_id'];
        
        // Tareas pendientes
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM estudiante_tarea et 
            WHERE et.estudiante_id = ? 
            AND et.estado = 'pendiente'
            AND EXISTS (
                SELECT 1 FROM tareas t 
                WHERE t.id = et.tarea_id 
                AND t.fecha_entrega >= CURDATE()
            )
        ");
        $stmt->execute([$estudiante_id]);
        $tareas_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Tareas completadas
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM estudiante_tarea et 
            WHERE et.estudiante_id = ? 
            AND et.estado = 'completada'
        ");
        $stmt->execute([$estudiante_id]);
        $tareas_completadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    // 4. Obtener eventos del calendario para el padre (todos los eventos públicos)
    $stmt = $conn->prepare("
        SELECT titulo, descripcion, fecha_evento, tipo, lugar 
        FROM eventos 
        WHERE destinatario = 'todos' OR destinatario = 'padres'
        ORDER BY fecha_evento ASC
        LIMIT 10
    ");
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Obtener próximo evento
    $stmt = $conn->prepare("
        SELECT titulo, fecha_evento, lugar 
        FROM eventos 
        WHERE fecha_evento >= CURDATE() 
        AND activo = 1
        AND (destinatario = 'todos' OR destinatario = 'padres')
        ORDER BY fecha_evento ASC 
        LIMIT 1
    ");
    $stmt->execute();
    $proximo_evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $dias_proximo_evento = 0;
    if ($proximo_evento) {
        $fecha_evento = new DateTime($proximo_evento['fecha_evento']);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fecha_evento);
        $dias_proximo_evento = $diferencia->days;
    }
    
    // 6. Obtener comunicados no leídos (últimos 7 días)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM comunicados c 
        WHERE c.fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND c.activo = 1
        AND NOT EXISTS (
            SELECT 1 FROM comunicados_leidos cl 
            WHERE cl.comunicado_id = c.id 
            AND cl.usuario_id = ?
        )
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $comunicados_nuevos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 7. Obtener tareas recientes del estudiante principal
    $tareas_recientes = [];
    if ($estudiantes && count($estudiantes) > 0) {
        $estudiante_id = $estudiantes[0]['estudiante_id'];
        
        $stmt = $conn->prepare("
            SELECT t.titulo, t.descripcion, t.fecha_entrega,
                   m.nombre as materia,
                   u.nombres as profesor_nombres, u.apellidos as profesor_apellidos,
                   et.estado
            FROM tareas t 
            INNER JOIN materias m ON t.materia_id = m.id 
            INNER JOIN usuarios u ON t.profesor_id = u.id 
            INNER JOIN estudiante_tarea et ON t.id = et.tarea_id 
            WHERE et.estudiante_id = ? 
            ORDER BY t.fecha_entrega ASC 
            LIMIT 5
        ");
        $stmt->execute([$estudiante_id]);
        $tareas_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    // En caso de error, usar datos por defecto
    $padre_data = [
        'dni' => 'No disponible',
        'telefono' => 'No disponible', 
        'direccion' => 'No disponible',
        'email' => $_SESSION['email'] ?? 'No disponible'
    ];
    $estudiante_principal = 'No disponible';
    $grado_estudiante = 'No disponible';
    $tutor_estudiante = 'No disponible';
    $tareas_pendientes = 0;
    $tareas_completadas = 0;
    $dias_proximo_evento = 0;
    $comunicados_nuevos = 0;
    $tareas_recientes = [];
    $estudiantes = [];
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
        
        @media (max-width: 768px) {
            .calendar-wrapper {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Aplicación principal -->
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
            <div class="top-bar">
                <div>
                    <h2 id="moduleTitle">Dashboard</h2>
                    <div class="breadcrumb" id="breadcrumb">
                        Estudiante: <strong id="studentName"><?php echo $estudiante_principal; ?></strong> - 
                        <span id="studentGrade"><?php echo $grado_estudiante; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="content-area">
                <!-- DASHBOARD -->
                <div id="dashboard" class="module-content active">
                    <div class="stats-grid">
                        <div class="stat-card warning">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-number" id="tareasPendientes"><?php echo $tareas_pendientes; ?></div>
                            <div class="stat-label">Tareas Pendientes</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number" id="tareasCompletadas"><?php echo $tareas_completadas; ?></div>
                            <div class="stat-label">Tareas Completadas</div>
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
                                <div class="task-item <?php echo $tarea['estado'] === 'completada' ? 'completed' : 'pending'; ?>">
                                    <div class="task-info">
                                        <h3>📚 <?php echo htmlspecialchars($tarea['materia']); ?> - <?php echo htmlspecialchars($tarea['titulo']); ?></h3>
                                        <p><?php echo htmlspecialchars($tarea['descripcion']); ?></p>
                                        <div class="task-meta">
                                            📅 Vence: <?php echo date('d M Y', strtotime($tarea['fecha_entrega'])); ?> | 
                                            👨‍🏫 Prof. <?php echo htmlspecialchars($tarea['profesor_nombres'] . ' ' . $tarea['profesor_apellidos']); ?>
                                        </div>
                                    </div>
                                    <span class="task-status <?php echo $tarea['estado'] === 'completada' ? 'status-completed' : 'status-pending'; ?>">
                                        <?php echo $tarea['estado'] === 'completada' ? 'COMPLETADA' : 'PENDIENTE'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">No hay tareas pendientes</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- TAREAS -->
                <div id="tareas" class="module-content">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
                        <button class="calendar-btn active" onclick="filtrarTareas('todas')">Todas</button>
                        <button class="calendar-btn" style="background: #f39c12;" onclick="filtrarTareas('pendientes')">Pendientes</button>
                        <button class="calendar-btn" style="background: #2ecc71;" onclick="filtrarTareas('completadas')">Completadas</button>
                    </div>
                    
                    <div class="task-list" id="listaTareas">
                        <div class="loading-message">Cargando tareas...</div>
                    </div>
                </div>
                
                <!-- CALENDARIO MEJORADO -->
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
                                <!-- Generado por JavaScript -->
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
                
                <!-- COMUNICADOS -->
                <div id="comunicados" class="module-content">
                    <div class="message-list" id="listaComunicados">
                        <div class="loading-message">Cargando comunicados...</div>
                    </div>
                </div>
                
                <!-- PERFIL -->
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
                                    <div class="info-label">Dirección</div>
                                    <div class="info-value" id="direccionPadre"><?php echo htmlspecialchars($padre_data['direccion']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-card">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">👧 Información del Estudiante</h3>
                            <div class="info-grid">                                
                                <div class="info-item">
                                    <div class="info-label">Grado y Sección</div>
                                    <div class="info-value"><?php echo htmlspecialchars($grado_estudiante); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Tutor</div>
                                    <div class="info-value"><?php echo htmlspecialchars($tutor_estudiante); ?></div>
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

        // Función para cargar módulos
        function loadModule(moduleId, clickedElement) {
            // Ocultar todos los módulos
            document.querySelectorAll('.module-content').forEach(module => {
                module.classList.remove('active');
            });
            
            // Remover activo de todos los items del menú
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Mostrar módulo seleccionado y activar item del menú
            document.getElementById(moduleId).classList.add('active');
            clickedElement.classList.add('active');
            
            // Actualizar título
            const titles = {
                'dashboard': 'Dashboard',
                'tareas': 'Tareas',
                'calendario': 'Calendario',
                'comunicados': 'Comunicados',
                'perfil': 'Mi Perfil'
            };
            document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Portal Padre';
            
            // Si es calendario, generarlo
            if (moduleId === 'calendario') {
                generarCalendario();
            }
        }
        
        // Función de logout
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../../api/auth/logout.php';
            }
        }
        
        // Filtrar tareas
        function filtrarTareas(filtro) {
            const tareas = document.querySelectorAll('#listaTareas .task-item');
            tareas.forEach(tarea => {
                const esCompletada = tarea.classList.contains('completed');
                const esPendiente = tarea.classList.contains('pending');
                
                switch(filtro) {
                    case 'todas':
                        tarea.style.display = 'flex';
                        break;
                    case 'pendientes':
                        tarea.style.display = esPendiente ? 'flex' : 'none';
                        break;
                    case 'completadas':
                        tarea.style.display = esCompletada ? 'flex' : 'none';
                        break;
                }
            });
        }
        
        // Generar calendario mejorado
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
                
                if (tieneEventos) {
                    clase += ' has-event';
                }
                
                html += `<div class="${clase}" onclick="seleccionarDia(${day}, ${month}, ${year})">
                    ${day}
                    ${tieneEventos ? '<div class="event-dot"></div>' : ''}
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
            
            let mensaje = `📅 ${fechaFormateada}`;
            
            if (eventosDia.length > 0) {
                mensaje += `\n\n📋 Eventos para este día:\n`;
                eventosDia.forEach((evento, index) => {
                    mensaje += `\n${index + 1}. ${evento.titulo}\n   📝 ${evento.descripcion}\n`;
                });
            } else {
                mensaje += `\n\nNo hay eventos programados para este día.`;
            }
            
            alert(mensaje);
        }
        
        function cambiarMes(direccion) {
            currentDate.setMonth(currentDate.getMonth() + direccion);
            generarCalendario();
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Asegurar que el dashboard esté activo al inicio
            loadModule('dashboard', document.querySelector('.nav-item.active'));
        });
    </script>
</body>
</html>