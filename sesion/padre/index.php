<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'padre') {
    header('Location: ../../index.html');
    exit;
}

// Aquí luego puedes agregar consultas a la BD para obtener datos del padre y estudiante
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
                        Estudiante: <strong id="studentName">María Pérez López</strong> - 
                        <span id="studentGrade">3ro Primaria A</span>
                    </div>
                </div>
            </div>
            
            <div class="content-area">
                <!-- DASHBOARD -->
                <div id="dashboard" class="module-content active">
                    <div class="stats-grid">
                        <div class="stat-card warning">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-number" id="tareasPendientes">3</div>
                            <div class="stat-label">Tareas Pendientes</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number" id="tareasCompletadas">12</div>
                            <div class="stat-label">Tareas Completadas</div>
                        </div>
                        
                        <div class="stat-card purple">
                            <div class="stat-icon">📆</div>
                            <div class="stat-number" id="diasProximoEvento">2</div>
                            <div class="stat-label">Días - Próximo Evento</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📨</div>
                            <div class="stat-number" id="mensajesNuevos">1</div>
                            <div class="stat-label">Mensajes Nuevos</div>
                        </div>
                    </div>
                    
                    <h3 style="color: #2c3e50; margin-bottom: 20px; font-size: 20px;">📌 Tareas Recientes</h3>
                    <div class="task-list" id="tareasRecientes">
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📐 Matemática - Suma y Resta</h3>
                                <p>Resolver los ejercicios de la página 45 del libro</p>
                                <div class="task-meta">📅 Vence: 12 Oct 2025 | 👨‍🏫 Prof. María García</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                        
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📖 Comunicación - Lectura</h3>
                                <p>Leer el cuento "El león y el ratón" y responder preguntas</p>
                                <div class="task-meta">📅 Vence: 13 Oct 2025 | 👨‍🏫 Prof. Carlos Ramos</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                    </div>
                </div>
                
                <!-- TAREAS -->
                <div id="tareas" class="module-content">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
                        <button class="calendar-btn" onclick="filtrarTareas('todas')">Todas</button>
                        <button class="calendar-btn" style="background: #f39c12;" onclick="filtrarTareas('pendientes')">Pendientes</button>
                        <button class="calendar-btn" style="background: #2ecc71;" onclick="filtrarTareas('completadas')">Completadas</button>
                    </div>
                    
                    <div class="task-list" id="listaTareas">
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📐 Matemática - Geometría</h3>
                                <p>Dibujar 5 figuras geométricas y escribir sus nombres</p>
                                <div class="task-meta">📅 Vence: 12 Oct 2025 | 👨‍🏫 Prof. María García | 📝 3ro A</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                        
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📖 Comunicación - Comprensión Lectora</h3>
                                <p>Leer "El patito feo" y responder preguntas de la página 20</p>
                                <div class="task-meta">📅 Vence: 13 Oct 2025 | 👨‍🏫 Prof. Carlos Ramos | 📝 3ro A</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                        
                        <div class="task-item completed">
                            <div class="task-info">
                                <h3>🔬 Ciencia - Los Animales</h3>
                                <p>Clasificar 10 animales según su alimentación</p>
                                <div class="task-meta">📅 Completada: 08 Oct 2025 | 👨‍🏫 Prof. Luis Torres | 📝 3ro A</div>
                            </div>
                            <span class="task-status status-completed">COMPLETADA</span>
                        </div>
                    </div>
                </div>
                
                <!-- CALENDARIO -->
                <div id="calendario" class="module-content">
                    <div class="calendar-wrapper">
                        <div class="calendar-box">
                            <div class="calendar-header">
                                <h3 id="mesActual">Octubre 2025</h3>
                                <div class="calendar-nav">
                                    <button class="calendar-btn" onclick="cambiarMes(-1)">◄</button>
                                    <button class="calendar-btn" onclick="cambiarMes(1)">►</button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid" id="calendarioGrid">
                                <!-- El calendario se generará con JavaScript -->
                            </div>
                        </div>
                        
                        <div class="calendar-box">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📅 Próximos Eventos</h3>
                            <div class="events-list" id="listaEventos">
                                <div class="event-item">
                                    <div class="event-title">Reunión de Padres</div>
                                    <div class="event-detail">📅 09 Oct 2025 - 3:00 PM</div>
                                    <div class="event-detail">📍 Aula 3ro A</div>
                                </div>
                                
                                <div class="event-item" style="border-left-color: #f39c12;">
                                    <div class="event-title">Examen de Matemática</div>
                                    <div class="event-detail">📅 15 Oct 2025</div>
                                    <div class="event-detail">📚 Unidad 3</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- COMUNICADOS -->
                <div id="comunicados" class="module-content">
                    <div class="message-list" id="listaComunicados">
                        <div class="message-card">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">🏫 Dirección</div>
                                    <div class="message-date">07 Oct 2025 - 10:30 AM</div>
                                </div>
                                <span class="message-badge">NUEVO</span>
                            </div>
                            <h3 class="message-title">Reunión de Padres de Familia</h3>
                            <p class="message-text">
                                Estimados padres de familia, les recordamos que el día 09 de octubre a las 3:00 PM 
                                tendremos la reunión bimestral para tratar temas importantes sobre el avance académico 
                                de sus hijos. Su asistencia es muy importante.
                            </p>
                        </div>
                        
                        <div class="message-card">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">👨‍🏫 Prof. María García - Matemática</div>
                                    <div class="message-date">05 Oct 2025 - 2:15 PM</div>
                                </div>
                            </div>
                            <h3 class="message-title">Examen de Unidad 3</h3>
                            <p class="message-text">
                                Padres de familia del 3ro A, les informo que el día 15 de octubre tendremos el examen 
                                de la unidad 3 de matemática. Por favor, apoyar a sus hijos en el repaso de los temas: 
                                suma, resta, multiplicación y figuras geométricas.
                            </p>
                        </div>
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
                                    <div class="info-value" id="dniPadre">45678912</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Correo Electrónico</div>
                                    <div class="info-value" id="emailPadre">juan.perez@email.com</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value" id="telefonoPadre">987 654 321</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Dirección</div>
                                    <div class="info-value" id="direccionPadre">Av. Lima 456, Lima</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-card">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">👧 Información del Estudiante</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Nombre Completo</div>
                                    <div class="info-value" id="nombreEstudiante">María Pérez López</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Grado</div>
                                    <div class="info-value" id="gradoEstudiante">3ro de Primaria</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Sección</div>
                                    <div class="info-value" id="seccionEstudiante">A</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Tutor</div>
                                    <div class="info-value" id="tutorEstudiante">Prof. María García</div>
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
        
        // Generar calendario
        function generarCalendario() {
            const calendarioGrid = document.getElementById('calendarioGrid');
            const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
            
            let html = '';
            
            // Encabezados de días
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day-label">${dia}</div>`;
            });
            
            // Días del mes (ejemplo simplificado)
            for (let i = 1; i <= 31; i++) {
                let clase = 'calendar-day';
                if (i === 7) clase += ' today';
                if ([9, 15, 20].includes(i)) clase += ' has-event';
                
                html += `<div class="${clase}" onclick="seleccionarDia(${i})">${i}</div>`;
            }
            
            calendarioGrid.innerHTML = html;
        }
        
        function seleccionarDia(dia) {
            alert(`Día ${dia} seleccionado - Aquí podrías mostrar eventos específicos`);
        }
        
        function cambiarMes(direccion) {
            // Aquí implementarías la lógica para cambiar de mes
            alert('Funcionalidad de cambio de mes - Conectar con backend');
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Actualizar información del usuario
            document.getElementById('userName').textContent = nombres + ' ' + apellidos;
            
            // Cargar datos iniciales (aquí luego harías llamadas a APIs)
            cargarDatosIniciales();
        });
        
        // Función para cargar datos desde APIs
        async function cargarDatosIniciales() {
            try {
                // Aquí harías llamadas a tus APIs
                // const response = await fetch('api/padre/dashboard.php');
                // const data = await response.json();
                
                // Por ahora usamos datos de ejemplo
                const datosEjemplo = {
                    tareasPendientes: 3,
                    tareasCompletadas: 12,
                    diasProximoEvento: 2,
                    mensajesNuevos: 1,
                    estudiante: {
                        nombre: 'María Pérez López',
                        grado: '3ro Primaria A'
                    }
                };
                
                // Actualizar UI con datos
                document.getElementById('tareasPendientes').textContent = datosEjemplo.tareasPendientes;
                document.getElementById('tareasCompletadas').textContent = datosEjemplo.tareasCompletadas;
                document.getElementById('diasProximoEvento').textContent = datosEjemplo.diasProximoEvento;
                document.getElementById('mensajesNuevos').textContent = datosEjemplo.mensajesNuevos;
                document.getElementById('studentName').textContent = datosEjemplo.estudiante.nombre;
                document.getElementById('studentGrade').textContent = datosEjemplo.estudiante.grado;
                
            } catch (error) {
                console.error('Error cargando datos:', error);
            }
        }
    </script>
</body>
</html>