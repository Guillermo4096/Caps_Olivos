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

    // 2. Obtener IDs de los Grados asignados al padre (LOGICA CORREGIDA)
    $stmt = $conn->prepare("
        SELECT g.id as grado_id
        FROM padre_grado pg
        INNER JOIN grados g ON pg.grado_id = g.id
        WHERE pg.usuario_padre_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $grados_padre = $stmt->fetchAll(PDO::FETCH_COLUMN); // Solo necesitamos los IDs

    $tareas_pendientes = 0;
    $tareas_recientes = [];
    $tareas_totales = 0;

    if (!empty($grados_padre)) {
        $placeholders = str_repeat('?,', count($grados_padre) - 1) . '?';

        // 3. Obtener TAREAS RECIENTES, TOTALES Y PENDIENTES (LOGICA CORREGIDA)
        
        // A. Tareas Pendientes (COUNT): No vencidas
        $stmt_count = $conn->prepare("
            SELECT COUNT(t.id) 
            FROM tareas t 
            WHERE t.grado_id IN ($placeholders) AND t.fecha_entrega >= DATE('now')
        ");
        $stmt_count->execute($grados_padre);
        $tareas_pendientes = $stmt_count->fetchColumn();

        // B. Tareas Totales (COUNT)
        $stmt_total = $conn->prepare("
            SELECT COUNT(t.id) 
            FROM tareas t 
            WHERE t.grado_id IN ($placeholders)
        ");
        $stmt_total->execute($grados_padre);
        $tareas_totales = $stmt_total->fetchColumn();


        // C. Tareas Recientes (para mostrar en el dashboard)
        $stmt_recientes = $conn->prepare("
            SELECT t.titulo, t.descripcion, t.fecha_entrega, 
                m.nombre AS materia_nombre, 
                g.nombre AS grado_nombre, g.seccion,
                u.nombres AS docente_nombres, u.apellidos AS docente_apellidos,
                CASE 
                    WHEN t.fecha_entrega < DATE('now') THEN 'vencida'
                    WHEN t.fecha_entrega = DATE('now') THEN 'hoy'
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
    }
    
} catch (Exception $e) {
    // En caso de error, usar datos por defecto
    $padre_data = [
        'dni' => 'No disponible',
        'telefono' => 'No disponible', 
        'direccion' => 'No disponible',
        'email' => $_SESSION['email'] ?? 'No disponible'
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
                
                <!-- TAREAS -->
                <div id="tareas" class="module-content">
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #2c3e50; margin-bottom: 15px;">📋 Tareas de los Grados Asignados</h3>
                        <p style="color: #7f8c8d; font-size: 14px;">Lista de todas las tareas publicadas para los grados de sus hijos.</p>
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
            
            // CORRECCIÓN: Asegurar que se carguen las tareas
            if (moduleId === 'tareas') {
                console.log('Iniciando carga de tareas...');
                cargarTareas();
            }
        }
        
        // Función de logout
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../../api/auth/logout.php';
            }
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

        function renderTareasRecientes(tareas) {
            const listaTareas = document.getElementById('lista-tareas-recientes'); // Asegúrate de tener este ID
            let html = '';

            if (tareas.length === 0) {
                listaTareas.innerHTML = '<p class="text-center">No hay tareas recientes asignadas.</p>';
                return;
            }

            tareas.forEach(tarea => {
                let colorEstado = '';
                let textoEstado = tarea.estado_texto.charAt(0).toUpperCase() + tarea.estado_texto.slice(1);
                
                switch (tarea.estado_texto) {
                    case 'vencida':
                        colorEstado = '#e74c3c'; // Rojo
                        break;
                    case 'hoy':
                        colorEstado = '#f39c12'; // Naranja
                        break;
                    case 'pendiente':
                        colorEstado = '#3498db'; // Azul
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