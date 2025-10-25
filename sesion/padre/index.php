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
    
    // 4. Obtener próximo evento
    $stmt = $conn->prepare("
        SELECT titulo, fecha_evento, lugar 
        FROM eventos 
        WHERE fecha_evento >= CURDATE() 
        AND activo = 1
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
    
    // 5. Obtener comunicados no leídos (últimos 7 días)
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
    
    // 6. Obtener tareas recientes del estudiante principal
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
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Padre - I.E Juan Pablo Vizcardo y Guzmán</title>
    <link rel="stylesheet" href="../../css/styles.css">
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
                        Estudiante: <strong id="studentName">Cargando...</strong> - 
                        <span id="studentGrade">Cargando...</span>
                    </div>
                </div>
            </div>
            
            <div class="content-area">
                <!-- DASHBOARD -->
                <div id="dashboard" class="module-content active">
                    <div class="stats-grid">
                        <div class="stat-card warning">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-number" id="tareasPendientes">0</div>
                            <div class="stat-label">Tareas Pendientes</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number" id="tareasCompletadas">0</div>
                            <div class="stat-label">Tareas Completadas</div>
                        </div>
                        
                        <div class="stat-card purple">
                            <div class="stat-icon">📆</div>
                            <div class="stat-number" id="diasProximoEvento">0</div>
                            <div class="stat-label">Días - Próximo Evento</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📨</div>
                            <div class="stat-number" id="mensajesNuevos">0</div>
                            <div class="stat-label">Mensajes Nuevos</div>
                        </div>
                    </div>
                    
                    <h3 style="color: #2c3e50; margin-bottom: 20px; font-size: 20px;">📌 Tareas Recientes</h3>
                    <div class="task-list" id="tareasRecientes">
                        <div class="loading-message">Cargando tareas...</div>
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
                
                <!-- CALENDARIO -->
                <div id="calendario" class="module-content">
                    <div class="calendar-wrapper">
                        <div class="calendar-box">
                            <div class="calendar-header">
                                <h3 id="mesActual">Cargando...</h3>
                                <div class="calendar-nav">
                                    <button class="calendar-btn" onclick="cambiarMes(-1)">◄</button>
                                    <button class="calendar-btn" onclick="cambiarMes(1)">►</button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid" id="calendarioGrid">
                                <div class="loading-message">Cargando calendario...</div>
                            </div>
                        </div>
                        
                        <div class="calendar-box">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📅 Próximos Eventos</h3>
                            <div class="events-list" id="listaEventos">
                                <div class="loading-message">Cargando eventos...</div>
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
                                    <div class="info-value" id="dniPadre"><?php echo $padre_data['dni']; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Correo Electrónico</div>
                                    <div class="info-value" id="emailPadre">Cargando...</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value" id="telefonoPadre"><?php echo $padre_data['telefono']; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Dirección</div>
                                    <div class="info-value" id="direccionPadre"><?php echo $padre_data['direccion']; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-card">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">👧 Información del Estudiante</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Nombre Completo</div>
                                    <div id="studentName"><?php echo $estudiante_principal; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Grado</div>
                                    <div id="studentGrade"><?php echo $grado_estudiante; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Sección</div>
                                    <div id="tutorEstudiante"><?php echo $tutor_estudiante; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Tutor</div>
                                    <div class="info-value" id="tutorEstudiante">Cargando...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos del usuario desde PHP
        const nombres = "<?php echo $_SESSION['nombres']; ?>";
        const apellidos = "<?php echo $_SESSION['apellidos']; ?>";
        let mesActual = new Date().getMonth() + 1;
        let anoActual = new Date().getFullYear();
        
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
            
            // Cargar datos específicos del módulo
            switch(moduleId) {
                case 'tareas':
                    cargarTareasCompletas();
                    break;
                case 'calendario':
                    cargarCalendario();
                    break;
                case 'comunicados':
                    cargarComunicados();
                    break;
                case 'perfil':
                    // Los datos ya se cargan en inicialización
                    break;
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
            // Actualizar botones activos
            document.querySelectorAll('.calendar-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
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
        
        // Generar calendario con eventos
        function generarCalendarioConEventos(eventos, proximosEventos) {
            const calendarioGrid = document.getElementById('calendarioGrid');
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            
            document.getElementById('mesActual').textContent = `${meses[mesActual-1]} ${anoActual}`;
            
            const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
            let html = '';
            
            // Encabezados de días
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day-label">${dia}</div>`;
            });
            
            // Días del mes
            const diasEnMes = new Date(anoActual, mesActual, 0).getDate();
            const primerDia = new Date(anoActual, mesActual-1, 1).getDay();
            
            // Días vacíos al inicio
            for (let i = 0; i < primerDia; i++) {
                html += `<div class="calendar-day empty"></div>`;
            }
            
            // Días del mes
            for (let i = 1; i <= diasEnMes; i++) {
                let clase = 'calendar-day';
                const hoy = new Date();
                if (i === hoy.getDate() && mesActual === hoy.getMonth()+1 && anoActual === hoy.getFullYear()) {
                    clase += ' today';
                }
                
                // Verificar si hay eventos en este día
                const fechaStr = `${anoActual}-${mesActual.toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`;
                const tieneEvento = eventos.some(evento => evento.fecha_evento.startsWith(fechaStr));
                if (tieneEvento) {
                    clase += ' has-event';
                }
                
                html += `<div class="${clase}" onclick="seleccionarDia(${i})">${i}</div>`;
            }
            
            calendarioGrid.innerHTML = html;
            
            // Actualizar próximos eventos
            actualizarProximosEventos(proximosEventos);
        }
        
        function actualizarProximosEventos(eventos) {
            const container = document.getElementById('listaEventos');
            
            if (eventos.length === 0) {
                container.innerHTML = '<div class="no-data">No hay eventos próximos</div>';
                return;
            }
            
            let html = '';
            eventos.forEach(evento => {
                const fecha = new Date(evento.fecha_evento);
                const fechaStr = fecha.toLocaleDateString('es-ES', { 
                    day: 'numeric', 
                    month: 'long', 
                    year: 'numeric'
                });
                
                html += `
                    <div class="event-item">
                        <div class="event-title">${evento.titulo}</div>
                        <div class="event-detail">📅 ${fechaStr}</div>
                        ${evento.lugar ? `<div class="event-detail">📍 ${evento.lugar}</div>` : ''}
                        ${evento.descripcion ? `<div class="event-detail">📝 ${evento.descripcion}</div>` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function seleccionarDia(dia) {
            alert(`Día ${dia} seleccionado - Funcionalidad en desarrollo`);
        }
        
        function cambiarMes(direccion) {
            mesActual += direccion;
            if (mesActual > 12) {
                mesActual = 1;
                anoActual++;
            } else if (mesActual < 1) {
                mesActual = 12;
                anoActual--;
            }
            cargarCalendario();
        }
        
        // Función para actualizar la lista completa de tareas
        function actualizarListaTareas(tareas) {
            const container = document.getElementById('listaTareas');
            
            if (tareas.length === 0) {
                container.innerHTML = '<div class="no-data">No hay tareas registradas</div>';
                return;
            }

            let html = '';
            tareas.forEach(tarea => {
                const fechaEntrega = new Date(tarea.fecha_entrega).toLocaleDateString('es-ES');
                const fechaCreacion = new Date(tarea.fecha_creacion).toLocaleDateString('es-ES');
                const estado = tarea.estado === 'completada' ? 'COMPLETADA' : 'PENDIENTE';
                const claseEstado = tarea.estado === 'completada' ? 'status-completed' : 'status-pending';
                const claseItem = tarea.estado === 'completada' ? 'completed' : 'pending';

                html += `
                    <div class="task-item ${claseItem}">
                        <div class="task-info">
                            <h3>📚 ${tarea.materia} - ${tarea.titulo}</h3>
                            <p>${tarea.descripcion}</p>
                            <div class="task-meta">
                                📅 Vence: ${fechaEntrega} | 
                                👨‍🏫 Prof. ${tarea.profesor_nombre} ${tarea.profesor_apellidos} | 
                                📝 ${tarea.grado} ${tarea.seccion}
                            </div>
                        </div>
                        <span class="task-status ${claseEstado}">${estado}</span>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Función para actualizar comunicados
        function actualizarComunicados(comunicados) {
            const container = document.getElementById('listaComunicados');
            
            if (comunicados.length === 0) {
                container.innerHTML = '<div class="no-data">No hay comunicados</div>';
                return;
            }

            let html = '';
            comunicados.forEach(comunicado => {
                const fecha = new Date(comunicado.fecha_publicacion);
                const fechaStr = fecha.toLocaleDateString('es-ES', { 
                    day: 'numeric', 
                    month: 'long', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const remitente = comunicado.remitente_nombre ? 
                    `${comunicado.remitente_nombre} ${comunicado.remitente_apellidos}` : 
                    'Dirección';

                html += `
                    <div class="message-card">
                        <div class="message-header">
                            <div>
                                <div class="message-sender">${remitente}</div>
                                <div class="message-date">${fechaStr}</div>
                            </div>
                            ${comunicado.es_nuevo ? '<span class="message-badge">NUEVO</span>' : ''}
                        </div>
                        <h3 class="message-title">${comunicado.titulo}</h3>
                        <p class="message-text">${comunicado.mensaje}</p>
                    </div>
                `;
            });

            container.innerHTML = html;
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Actualizar información del usuario
            document.getElementById('userName').textContent = nombres + ' ' + apellidos;
            
            // Cargar datos iniciales
            cargarDatosIniciales();
        });
        
        // Función para cargar datos desde APIs
        async function cargarDatosIniciales() {
            try {
                const response = await fetch('/dashboard.php');
                const data = await response.json();

                if (data.success) {
                    // Actualizar información del padre
                    document.getElementById('userName').textContent = 
                        data.padre.nombres + ' ' + data.padre.apellidos;
                    document.getElementById('nombrePadre').textContent = 
                        data.padre.nombres + ' ' + data.padre.apellidos;
                    document.getElementById('dniPadre').textContent = data.padre.dni || 'No registrado';
                    document.getElementById('emailPadre').textContent = data.padre.email;
                    document.getElementById('telefonoPadre').textContent = data.padre.telefono || 'No registrado';
                    document.getElementById('direccionPadre').textContent = data.padre.direccion || 'No registrada';

                    // Actualizar información del estudiante
                    if (data.estudiante_actual) {
                        const estudiante = data.estudiante_actual;
                        document.getElementById('studentName').textContent = 
                            estudiante.nombres + ' ' + estudiante.apellidos;
                        document.getElementById('studentGrade').textContent = 
                            estudiante.grado + ' ' + estudiante.seccion;
                        document.getElementById('nombreEstudiante').textContent = 
                            estudiante.nombres + ' ' + estudiante.apellidos;
                        document.getElementById('gradoEstudiante').textContent = estudiante.grado;
                        document.getElementById('seccionEstudiante').textContent = estudiante.seccion;
                    }

                    // Actualizar estadísticas
                    document.getElementById('tareasPendientes').textContent = 
                        data.estadisticas.tareas_pendientes;
                    document.getElementById('tareasCompletadas').textContent = 
                        data.estadisticas.tareas_completadas;
                    document.getElementById('diasProximoEvento').textContent = 
                        data.estadisticas.dias_proximo_evento;
                    document.getElementById('mensajesNuevos').textContent = 
                        data.estadisticas.mensajes_nuevos;

                    // Actualizar tareas recientes
                    actualizarTareasRecientes(data.tareas_recientes);
                    
                } else {
                    console.error('Error del servidor:', data.error);
                    mostrarError('Error al cargar los datos: ' + data.error);
                }

            } catch (error) {
                console.error('Error cargando datos:', error);
                mostrarError('Error de conexión al cargar los datos');
            }
        }

        // Función para actualizar la lista de tareas recientes
        function actualizarTareasRecientes(tareas) {
            const container = document.getElementById('tareasRecientes');
            
            if (!tareas || tareas.length === 0) {
                container.innerHTML = '<div class="no-data">No hay tareas pendientes</div>';
                return;
            }

            let html = '';
            tareas.forEach(tarea => {
                const fechaEntrega = new Date(tarea.fecha_entrega).toLocaleDateString('es-ES');
                const estado = tarea.estado === 'completada' ? 'COMPLETADA' : 'PENDIENTE';
                const claseEstado = tarea.estado === 'completada' ? 'status-completed' : 'status-pending';
                const claseItem = tarea.estado === 'completada' ? 'completed' : 'pending';

                html += `
                    <div class="task-item ${claseItem}">
                        <div class="task-info">
                            <h3>📚 ${tarea.materia} - ${tarea.titulo}</h3>
                            <p>${tarea.descripcion}</p>
                            <div class="task-meta">
                                📅 Vence: ${fechaEntrega} | 
                                👨‍🏫 Prof. ${tarea.profesor_nombre} ${tarea.profesor_apellidos}
                            </div>
                        </div>
                        <span class="task-status ${claseEstado}">${estado}</span>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Función para cargar tareas completas
        async function cargarTareasCompletas() {
            try {
                const container = document.getElementById('listaTareas');
                container.innerHTML = '<div class="loading-message">Cargando tareas...</div>';
                
                const response = await fetch('/tareas.php');
                const data = await response.json();

                if (data.success) {
                    actualizarListaTareas(data.tareas);
                } else {
                    container.innerHTML = '<div class="no-data">Error al cargar tareas</div>';
                console.error('Error:', data.error);
                }
            } catch (error) {
                console.error('Error cargando tareas:', error);
                document.getElementById('listaTareas').innerHTML = '<div class="no-data">Error de conexión</div>';
            }
        }

        // Función para cargar calendario
        async function cargarCalendario() {
            try {
                const container = document.getElementById('calendarioGrid');
                const eventosContainer = document.getElementById('listaEventos');
                container.innerHTML = '<div class="loading-message">Cargando calendario...</div>';
                eventosContainer.innerHTML = '<div class="loading-message">Cargando eventos...</div>';
                
                const response = await fetch(`/calendario.php?mes=${mesActual}&ano=${anoActual}`);
                const data = await response.json();

                if (data.success) {
                    generarCalendarioConEventos(data.eventos, data.proximos_eventos);
                } else {
                    container.innerHTML = '<div class="no-data">Error al cargar calendario</div>';
                    console.error('Error:', data.error);
                }
            } catch (error) {
                console.error('Error cargando calendario:', error);
                document.getElementById('calendarioGrid').innerHTML = '<div class="no-data">Error de conexión</div>';
            }
        }

        // Función para cargar comunicados
        async function cargarComunicados() {
            try {
                const container = document.getElementById('listaComunicados');
                container.innerHTML = '<div class="loading-message">Cargando comunicados...</div>';
                
                const response = await fetch('comunicados.php');
                const data = await response.json();

                if (data.success) {
                    actualizarComunicados(data.comunicados);
                } else {
                    container.innerHTML = '<div class="no-data">Error al cargar comunicados</div>';
                    console.error('Error:', data.error);
                }
            } catch (error) {
                console.error('Error cargando comunicados:', error);
                document.getElementById('listaComunicados').innerHTML = '<div class="no-data">Error de conexión</div>';
            }
        }

        // Función para mostrar errores
        function mostrarError(mensaje) {
            // Podrías implementar un sistema de notificaciones más elegante
            console.error(mensaje);
        }
    </script>
</body>
</html>