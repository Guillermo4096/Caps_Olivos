<?php
// /sesion/padre/tareas.php
// Este archivo es la VISTA, se asume que la sesión y el rol ya fueron validados en index.php.

/* * NOTA IMPORTANTE: 
* Se eliminó toda la lógica PHP que intentaba obtener datos de "estudiantes" o tareas directamente 
* porque esa lógica ahora se maneja de forma segura y correcta en el archivo de la API.
*/
?>

<div class="container mt-4">
    <h2>📋 Tareas Asignadas</h2>
    
    <div id="mensaje-cargando" class="alert alert-info">Cargando tareas...</div>
    <div id="mensaje-error" class="alert alert-danger" style="display:none;"></div>

    <table class="table table-striped table-hover" id="tabla-tareas" style="display:none;">
        <thead class="thead-dark">
            <tr>
                <th>Tarea</th>
                <th>Materia</th>
                <th>Grado</th>
                <th>Docente</th>
                <th>Fecha Límite</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            </tbody>
    </table>
    
    <p id="mensaje-sin-tareas" style="display:none;" class="alert alert-warning">
        🎉 No tienes tareas asignadas en este momento para los grados asociados.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tablaBody = document.querySelector('#tabla-tareas tbody');
    const tabla = document.getElementById('tabla-tareas');
    const msgCargando = document.getElementById('mensaje-cargando');
    const msgError = document.getElementById('mensaje-error');
    const msgSinTareas = document.getElementById('mensaje-sin-tareas');

    function cargarTareas() {
        // Llama al endpoint de API que acabamos de definir
        fetch('../../api/padre/obtener-tareas.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Respuesta de red no satisfactoria: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                msgCargando.style.display = 'none';

                if (data.success) {
                    if (data.tareas && data.tareas.length > 0) {
                        data.tareas.forEach(tarea => {
                            const row = tablaBody.insertRow();
                            
                            // Columna 1: Título y Descripción
                            row.insertCell().innerHTML = `<strong>${tarea.titulo}</strong><br><small>${tarea.descripcion.substring(0, 100)}...</small>`;

                            // Columna 2: Materia
                            row.insertCell().textContent = tarea.materia;
                            
                            // Columna 3: Grado
                            row.insertCell().textContent = `${tarea.grado_nombre} (${tarea.seccion})`;
                            
                            // Columna 4: Docente
                            row.insertCell().textContent = `${tarea.profesor_nombre} ${tarea.profesor_apellidos}`;

                            // Columna 5: Fecha Límite
                            row.insertCell().textContent = new Date(tarea.fecha_entrega).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
                            
                            // Columna 6: Estado (con estilo de badge)
                            let estadoText = tarea.estado;
                            let estadoClass = '';
                            if (tarea.estado === 'Vencida') {
                                estadoClass = 'badge bg-danger text-white';
                            } else if (tarea.estado === 'Pendiente') {
                                estadoClass = 'badge bg-primary text-white';
                            }
                            row.insertCell().innerHTML = `<span class="${estadoClass}">${estadoText}</span>`;
                            
                        });
                        tabla.style.display = 'table';
                    } else {
                        msgSinTareas.style.display = 'block';
                    }
                } else {
                    // Manejo de errores de la API (ej: 'No autorizado')
                    msgError.textContent = 'Error: ' + (data.error || 'Fallo desconocido de la API.');
                    msgError.style.display = 'block';
                }
            })
            .catch(error => {
                // Manejo de errores de red o parsing
                msgCargando.style.display = 'none';
                msgError.textContent = 'Error de conexión o datos: ' + error.message;
                msgError.style.display = 'block';
                console.error('Fetch error:', error);
            });
    }

    cargarTareas();
});
</script>