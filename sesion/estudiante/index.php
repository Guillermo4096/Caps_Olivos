<?php
session_start();

// Verificar que el usuario sea estudiante
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'estudiante') {
    header('Location: ../../index.html');
    exit;
}

// Cargar datos del estudiante desde la base de datos
require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener información del estudiante
    $stmt = $conn->prepare("
        SELECT e.*, u.nombres, u.apellidos, u.email, u.dni 
        FROM estudiantes e 
        INNER JOIN usuarios u ON e.usuario_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$estudiante) {
        throw new Exception("Estudiante no encontrado");
    }
    
    // Obtener estadísticas del estudiante
    $stats = [
        'tareas_pendientes' => 0,
        'tareas_completadas' => 0,
        'mensajes_nuevos' => 0,
        'proximo_evento_dias' => 0
    ];
    
    // Contar tareas pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tareas WHERE estudiante_id = ? AND estado = 'pendiente'");
    $stmt->execute([$estudiante['id']]);
    $stats['tareas_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contar tareas completadas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tareas WHERE estudiante_id = ? AND estado = 'completada'");
    $stmt->execute([$estudiante['id']]);
    $stats['tareas_completadas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contar mensajes nuevos (ejemplo)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mensajes WHERE estudiante_id = ? AND leido = 0");
    $stmt->execute([$estudiante['id']]);
    $stats['mensajes_nuevos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener próximo evento (ejemplo)
    $stmt = $conn->prepare("SELECT fecha_evento FROM eventos WHERE fecha_evento >= CURDATE() ORDER BY fecha_evento ASC LIMIT 1");
    $stmt->execute();
    $proximo_evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($proximo_evento) {
        $fecha_evento = new DateTime($proximo_evento['fecha_evento']);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fecha_evento);
        $stats['proximo_evento_dias'] = $diferencia->days;
    }
    
} catch (Exception $e) {
    // En caso de error, usar datos por defecto
    $estudiante = [
        'nombres' => $_SESSION['nombres'] ?? 'Estudiante',
        'apellidos' => $_SESSION['apellidos'] ?? '',
        'grado' => '3ro',
        'seccion' => 'A',
        'dni' => ''
    ];
    
    $stats = [
        'tareas_pendientes' => 0,
        'tareas_completadas' => 0,
        'mensajes_nuevos' => 0,
        'proximo_evento_dias' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Estudiante - I.E José Faustino Sánchez Carrión</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        /* Estilos específicos para el dashboard de estudiante */
        .student-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }
        
        .student-card {
            background: white;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #3498db;
        }
        
        .student-card.warning {
            border-left-color: #f39c12;
        }
        
        .student-card.success {
            border-left-color: #2ecc71;
        }
        
        .student-card.purple {
            border-left-color: #9b59b6;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 800;
            margin: 8px 0;
        }
        
        .stat-label {
            text-transform: uppercase;
            color: #7f8c8d;
            font-weight: 700;
            font-size: 13px;
        }
        
        .recent-tasks {
            background: white;
            padding: 14px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        
        .task-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #f0f2f5;
        }
        
        .task-item:last-child {
            border-bottom: 0;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 12px;
        }
        
        .calendar-label {
            font-weight: 700;
            text-align: center;
            color: #7f8c8d;
            padding: 8px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .calendar-day.today {
            background: #e74c3c;
            color: white;
        }
        
        .calendar-day.has-event {
            background: #667eea;
            color: white;
        }
        
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .event-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="main-app active">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>🎓 Panel Estudiante</h3>
                <p>I.E José Faustino Sánchez C.</p>
            </div>
            
            <div class="sidebar-nav">
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
                    <div class="user-avatar">👧</div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></div>
                        <div class="user-role">Estudiante - <?php echo $estudiante['grado'] ?? '3ro' ?> <?php echo $estudiante['seccion'] ?? 'A' ?></div>
                    </div>
                </div>
                <button class="btn-logout" onclick="handleLogout()">🚪 Cerrar Sesión</button>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <h2 id="moduleTitle">Dashboard Estudiante</h2>
                    <div class="breadcrumb">Bienvenido al sistema escolar</div>
                </div>
            </div>
            
            <div class="content-area">
                <!-- DASHBOARD PRINCIPAL -->
                <div id="dashboard" class="module-content active">
                    <div class="student-stats-grid">
                        <div class="student-card warning">
                            <div class="stat-number" id="tareasPendientes"><?php echo $stats['tareas_pendientes']; ?></div>
                            <div class="stat-label">Tareas Pendientes</div>
                        </div>
                        
                        <div class="student-card success">
                            <div class="stat-number" id="tareasCompletadas"><?php echo $stats['tareas_completadas']; ?></div>
                            <div class="stat-label">Tareas Completadas</div>
                        </div>
                        
                        <div class="student-card purple">
                            <div class="stat-number" id="proximoEvento"><?php echo $stats['proximo_evento_dias']; ?></div>
                            <div class="stat-label">Días - Próximo Evento</div>
                        </div>
                        
                        <div class="student-card">
                            <div class="stat-number" id="mensajesNuevos"><?php echo $stats['mensajes_nuevos']; ?></div>
                            <div class="stat-label">Mensajes Nuevos</div>
                        </div>
                    </div>

                    <div class="recent-tasks">
                        <h3>📌 Tareas Recientes</h3>
                        <div id="tareasRecientesList">
                            <div class="task-item">
                                <div>📐 Matemática - Suma y Resta</div>
                                <div style="color: #f39c12; font-weight: 700;">PENDIENTE</div>
                            </div>
                            <div class="task-item">
                                <div>📖 Comunicación - Lectura</div>
                                <div style="color: #f39c12; font-weight: 700;">PENDIENTE</div>
                            </div>
                            <div class="task-item">
                                <div>🔬 Ciencia - Los Animales</div>
                                <div style="color: #2ecc71; font-weight: 700;">COMPLETADA</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MÓDULO DE TAREAS -->
                <div id="tareas" class="module-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #2c3e50;">Mis Tareas</h3>
                        <div>
                            <button class="btn-login" onclick="window.print()">🖨️ Imprimir</button>
                            <button class="btn-login" onclick="exportTareasJSON()">📤 Exportar</button>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                        <button class="btn-login" onclick="filterTareas('all')">Todas</button>
                        <button class="btn-login" style="background: #f39c12" onclick="filterTareas('pending')">Pendientes</button>
                        <button class="btn-login" style="background: #2ecc71" onclick="filterTareas('completed')">Completadas</button>
                    </div>

                    <div id="listaTareas" class="task-list">
                        <!-- Las tareas se cargarán dinámicamente -->
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            Cargando tareas...
                        </div>
                    </div>
                </div>

                <!-- MÓDULO DE CALENDARIO -->
                <div id="calendario" class="module-content">
                    <div class="header">
                        <h1>📅 Calendario Escolar</h1>
                        <div>
                            <button class="btn-login" onclick="prevMonth()">◄ Mes Anterior</button>
                            <button class="btn-login" onclick="nextMonth()">Siguiente Mes ►</button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="student-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h3 id="mesActual">Octubre 2025</h3>
                                <div style="color: #7f8c8d;" id="semanaActual">Semana 41</div>
                            </div>

                            <div class="calendar-grid">
                                <div class="calendar-label">D</div>
                                <div class="calendar-label">L</div>
                                <div class="calendar-label">M</div>
                                <div class="calendar-label">M</div>
                                <div class="calendar-label">J</div>
                                <div class="calendar-label">V</div>
                                <div class="calendar-label">S</div>
                                <!-- Los días se generarán dinámicamente con JavaScript -->
                            </div>
                        </div>

                        <div class="student-card">
                            <h3>Próximos Eventos</h3>
                            <div class="events-list" id="listaEventos">
                                <div class="event-item">
                                    <strong>Reunión de Padres</strong>
                                    <div style="font-size: 13px; color: #7f8c8d;">09 Oct 2025 - 3:00 PM | Aula 3ro A</div>
                                </div>
                                <div class="event-item" style="border-left-color: #f39c12;">
                                    <strong>Examen de Matemática</strong>
                                    <div style="font-size: 13px; color: #7f8c8d;">15 Oct 2025</div>
                                </div>
                                <div class="event-item" style="border-left-color: #2ecc71;">
                                    <strong>Día del Deporte</strong>
                                    <div style="font-size: 13px; color: #7f8c8d;">20 Oct 2025</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MÓDULO DE COMUNICADOS -->
                <div id="comunicados" class="module-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <h1>📢 Comunicados</h1>
                        <div>
                            <button class="btn-login" onclick="window.print()">🖨️ Imprimir</button>
                        </div>
                    </div>

                    <div id="listaComunicados">
                        <div class="student-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <div style="font-weight: 700;">🏫 Dirección</div>
                                    <div style="font-size: 13px; color: #7f8c8d;">07 Oct 2025 - 10:30 AM</div>
                                </div>
                                <div style="background: #e74c3c; color: white; padding: 6px 10px; border-radius: 12px; font-weight: 700; font-size: 12px;">
                                    NUEVO
                                </div>
                            </div>
                            <div style="font-size: 16px; margin-bottom: 8px; color: #2c3e50;">Reunión de Padres de Familia</div>
                            <div style="color: #555; line-height: 1.6;">
                                Estimados padres de familia, les recordamos que el día 09 de octubre a las 3:00 PM tendremos la reunión bimestral para tratar temas importantes sobre el avance académico de sus hijos. Su asistencia es importante.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MÓDULO DE PERFIL -->
                <div id="perfil" class="module-content">
                    <div class="student-card">
                        <div style="display: flex; gap: 18px; align-items: center; margin-bottom: 12px;">
                            <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                                👧
                            </div>
                            <div>
                                <h2><?php echo $estudiante['nombres'] . ' ' . $estudiante['apellidos']; ?></h2>
                                <div style="color: #7f8c8d;">
                                    <?php echo ($estudiante['grado'] ?? '3ro') . ' de Primaria - Sección ' . ($estudiante['seccion'] ?? 'A'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="student-card">
                        <h3>📋 Información Personal</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px;">
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                                <div style="font-size: 12px; color: #7f8c8d; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">DNI</div>
                                <div style="font-weight: 700; color: #2c3e50;"><?php echo $estudiante['dni'] ?? '78901234'; ?></div>
                            </div>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                                <div style="font-size: 12px; color: #7f8c8d; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Año Académico</div>
                                <div style="font-weight: 700; color: #2c3e50;">2025</div>
                            </div>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                                <div style="font-size: 12px; color: #7f8c8d; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Tutor</div>
                                <div style="font-weight: 700; color: #2c3e50;">Prof. María García</div>
                            </div>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                                <div style="font-size: 12px; color: #7f8c8d; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Turno</div>
                                <div style="font-weight: 700; color: #2c3e50;">Mañana</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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
                'dashboard': 'Dashboard Estudiante',
                'tareas': 'Mis Tareas',
                'calendario': 'Calendario Escolar',
                'comunicados': 'Comunicados',
                'perfil': 'Mi Perfil'
            };
            document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Panel Estudiante';
            
            // Cargar datos específicos del módulo
            if (moduleId === 'tareas') {
                cargarTareas();
            } else if (moduleId === 'calendario') {
                generarCalendario();
            }
        }
        
        // Función de logout
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../../api/auth/logout.php';
            }
        }
        
        // Funciones para el módulo de tareas
        async function cargarTareas() {
            try {
                // Simular carga de tareas desde API
                setTimeout(() => {
                    const tareas = [
                        {
                            titulo: "📐 Matemática - Geometría",
                            descripcion: "Dibujar 5 figuras geométricas y escribir sus nombres.",
                            meta: "📅 Vence: 12 Oct 2025 | 👨‍🏫 Prof. María García | 📝 3ro A",
                            estado: "pendiente"
                        },
                        {
                            titulo: "🔬 Ciencia - Los Animales", 
                            descripcion: "Clasificar 10 animales según su alimentación.",
                            meta: "📅 Completada: 08 Oct 2025 | 👨‍🏫 Prof. Luis Torres",
                            estado: "completada"
                        },
                        {
                            titulo: "📖 Comunicación - Lectura",
                            descripcion: "Leer el cuento 'La liebre y la tortuga' y responder preguntas.",
                            meta: "📅 Vence: 14 Oct 2025 | 👨‍🏫 Prof. Ana López",
                            estado: "pendiente"
                        }
                    ];
                    
                    actualizarListaTareas(tareas);
                }, 500);
                
            } catch (error) {
                console.error('Error cargando tareas:', error);
                document.getElementById('listaTareas').innerHTML = 
                    '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error al cargar las tareas</div>';
            }
        }
        
        function actualizarListaTareas(tareas) {
            const lista = document.getElementById('listaTareas');
            
            if (!tareas || tareas.length === 0) {
                lista.innerHTML = '<div style="text-align: center; padding: 40px; color: #7f8c8d;">No hay tareas asignadas</div>';
                return;
            }
            
            let html = '';
            tareas.forEach(tarea => {
                const esCompletada = tarea.estado === 'completada';
                const claseEstado = esCompletada ? 'completed' : 'pending';
                const colorEstado = esCompletada ? '#2ecc71' : '#f39c12';
                const textoEstado = esCompletada ? 'COMPLETADA' : 'PENDIENTE';
                
                html += `
                <div class="student-card ${claseEstado}" style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                        <div style="flex: 1;">
                            <h3 style="margin-bottom: 6px; font-size: 16px;">${tarea.titulo}</h3>
                            <p style="margin-bottom: 8px; color: #555;">${tarea.descripcion}</p>
                            <div style="font-size: 13px; color: #7f8c8d;">${tarea.meta}</div>
                        </div>
                        <div>
                            <div style="padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; background: ${esCompletada ? '#d4edda' : '#fff3cd'}; color: ${esCompletada ? '#155724' : '#856404'};">
                                ${textoEstado}
                            </div>
                        </div>
                    </div>
                </div>
                `;
            });
            
            lista.innerHTML = html;
        }
        
        function filterTareas(tipo) {
            const tareas = document.querySelectorAll('#listaTareas .student-card');
            let mostradas = 0;
            
            tareas.forEach(tarea => {
                if (tipo === 'all') {
                    tarea.style.display = 'block';
                    mostradas++;
                } else if (tipo === 'pending' && tarea.classList.contains('pending')) {
                    tarea.style.display = 'block';
                    mostradas++;
                } else if (tipo === 'completed' && tarea.classList.contains('completed')) {
                    tarea.style.display = 'block';
                    mostradas++;
                } else {
                    tarea.style.display = 'none';
                }
            });
        }
        
        function exportTareasJSON() {
            const tareas = [
                {
                    titulo: "Matemática - Geometría",
                    descripcion: "Dibujar 5 figuras geométricas y escribir sus nombres.",
                    estado: "pendiente",
                    vencimiento: "2025-10-12"
                },
                {
                    titulo: "Ciencia - Los Animales",
                    descripcion: "Clasificar 10 animales según su alimentación.", 
                    estado: "completada",
                    completada: "2025-10-08"
                }
            ];
            
            const blob = new Blob([JSON.stringify(tareas, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'mis-tareas.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        // Funciones para el módulo de calendario
        function generarCalendario() {
            // Implementación básica del calendario
            const calendarGrid = document.querySelector('.calendar-grid');
            if (calendarGrid.children.length > 7) return; // Ya está generado
            
            const dias = [];
            // Agregar días del mes (ejemplo para octubre 2025)
            for (let i = 1; i <= 31; i++) {
                let clase = 'calendar-day';
                if (i === 7) clase += ' today';
                if ([9, 15, 20, 22].includes(i)) clase += ' has-event';
                dias.push(`<div class="${clase}">${i}</div>`);
            }
            
            // Insertar después de los labels
            for (let i = 7; i < calendarGrid.children.length; i++) {
                calendarGrid.removeChild(calendarGrid.children[i]);
            }
            
            dias.forEach(dia => {
                calendarGrid.innerHTML += dia;
            });
        }
        
        function prevMonth() {
            alert('Funcionalidad: Ir al mes anterior');
            // Aquí iría la lógica para cambiar al mes anterior
        }
        
        function nextMonth() {
            alert('Funcionalidad: Ir al mes siguiente'); 
            // Aquí iría la lógica para cambiar al mes siguiente
        }
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar datos iniciales
            cargarTareas();
            generarCalendario();
        });
    </script>
</body>
</html>