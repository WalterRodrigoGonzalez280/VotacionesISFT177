// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo = 'success') {
    const messageContainer = document.getElementById('messageContainer');
    messageContainer.innerHTML = `<div class="message ${tipo}">${mensaje}</div>`;
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 3000);
}

// Función para cerrar sesión
function cerrarSesion() {
    // Redirigir directamente a logout.php que llevará a despedida.php
    window.location.href = 'logout.php';
}

// Función para ver la página de votos
function verPaginaVotos() {
    // Redirigir a la página de votos (index.php para administradores)
    window.location.href = 'index.php';
}

// Función para cambiar entre pestañas de administración
function cambiarTabAdmin(tab) {
    document.querySelectorAll('.admin-tab').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.admin-tab-content').forEach(content => content.style.display = 'none');
    
    document.querySelector(`.admin-tab[onclick="cambiarTabAdmin('${tab}')"]`).classList.add('active');
    document.getElementById(`${tab}Tab`).style.display = 'block';
    
    if (tab === 'usuarios') cargarUsuarios();
    if (tab === 'subgrupos') cargarSubgrupos();
    if (tab === 'ganadores') cargarGanadores();
    if (tab === 'configuracion') cargarConfiguracion();
}

// Función para cargar usuarios
function cargarUsuarios() {
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=obtener_usuarios'
    })
    .then(response => response.json())
    .then(data => {
        const usuariosList = document.getElementById('usuariosList');
        usuariosList.innerHTML = '';
        
        if (data.success) {
            if (data.usuarios.length === 0) {
                usuariosList.innerHTML = '<p style="text-align: center; padding: 20px;">No hay usuarios registrados</p>';
                return;
            }
            
            // Crear tabla
            const table = document.createElement('table');
            table.className = 'usuarios-table';
            
            // Crear encabezado
            const thead = document.createElement('thead');
            thead.innerHTML = `
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Votos</th>
                    <th>Detalles de Votos</th>
                    <th>Acciones</th>
                </tr>
            `;
            table.appendChild(thead);
            
            // Crear cuerpo de la tabla
            const tbody = document.createElement('tbody');
            data.usuarios.forEach(usuario => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${usuario.id}</td>
                    <td><strong>${usuario.nombre}</strong></td>
                    <td>${usuario.email}</td>
                    <td><span class="vote-count-badge">${usuario.total_votos}</span></td>
                    <td class="vote-details">${usuario.votos_detalle || 'Sin votos'}</td>
                    <td class="table-actions">
                        <button class="admin-btn-small" onclick="resetearVotosUsuario(${usuario.id})" title="Resetear Votos">🔄</button>
                        <button class="admin-btn-small delete-btn" onclick="eliminarUsuario(${usuario.id})" title="Eliminar">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            usuariosList.appendChild(table);
        } else {
            usuariosList.innerHTML = '<p>Error al cargar usuarios</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        usuariosList.innerHTML = '<p>Error al cargar usuarios</p>';
    });
}

// Función para cargar subgrupos
function cargarSubgrupos() {
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=obtener_subgrupos_admin'
    })
    .then(response => response.json())
    .then(data => {
        const subgruposList = document.getElementById('subgruposList');
        subgruposList.innerHTML = '';
        
        if (data.success) {
            // Agrupar subgrupos por grupo
            const grupos = {};
            data.subgrupos.forEach(subgrupo => {
                const grupoId = subgrupo.grupo_id;
                if (!grupos[grupoId]) {
                    grupos[grupoId] = {
                        nombre: subgrupo.grupo_nombre,
                        subgrupos: []
                    };
                }
                grupos[grupoId].subgrupos.push(subgrupo);
            });
            
            // Crear una sección para cada grupo
            Object.keys(grupos).sort().forEach(grupoId => {
                const grupo = grupos[grupoId];
                
                // Crear contenedor del grupo
                const grupoContainer = document.createElement('div');
                grupoContainer.className = 'grupo-admin-container';
                
                // Header del grupo
                const grupoHeader = document.createElement('div');
                grupoHeader.className = 'grupo-admin-header';
                grupoHeader.innerHTML = `
                    <h3>${grupo.nombre}</h3>
                    <span class="grupo-count">${grupo.subgrupos.length} subgrupo(s)</span>
                `;
                grupoContainer.appendChild(grupoHeader);
                
                // Subgrupos del grupo
                const subgruposGrid = document.createElement('div');
                subgruposGrid.className = 'subgrupos-admin-grid';
                
                grupo.subgrupos.forEach(subgrupo => {
                    const subgrupoDiv = document.createElement('div');
                    subgrupoDiv.className = 'subgrupo-admin-card';
                    subgrupoDiv.innerHTML = `
                        <div class="subgrupo-admin-info">
                            <h4>${subgrupo.nombre}</h4>
                            <div class="subgrupo-admin-stats">
                                <span class="vote-badge">${subgrupo.total_votos} votos</span>
                            </div>
                        </div>
                        <div class="subgrupo-admin-actions">
                            <button class="admin-btn-small" onclick="editarSubgrupo(${subgrupo.id}, '${subgrupo.nombre}')" title="Editar">✏️</button>
                            <button class="admin-btn-small delete-btn" onclick="eliminarSubgrupo(${subgrupo.id})" title="Eliminar">🗑️</button>
                        </div>
                    `;
                    subgruposGrid.appendChild(subgrupoDiv);
                });
                
                grupoContainer.appendChild(subgruposGrid);
                subgruposList.appendChild(grupoContainer);
            });
        } else {
            subgruposList.innerHTML = '<p>Error al cargar subgrupos</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        subgruposList.innerHTML = '<p>Error al cargar subgrupos</p>';
    });
}

// Función para mostrar el modal de agregar subgrupo
function mostrarAgregarSubgrupo() {
    document.getElementById('agregarSubgrupoModal').style.display = 'block';
}

// Función para cerrar el modal de agregar subgrupo
function cerrarAgregarSubgrupo() {
    document.getElementById('agregarSubgrupoModal').style.display = 'none';
    document.getElementById('agregarSubgrupoForm').reset();
}

// Función para editar un subgrupo
function editarSubgrupo(id, nombre) {
    const nuevoNombre = prompt('Ingrese el nuevo nombre del subgrupo:', nombre);
    if (nuevoNombre) {
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=actualizar_subgrupo&id=${id}&nombre=${encodeURIComponent(nuevoNombre)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje(data.message);
                cargarSubgrupos();
            } else {
                mostrarMensaje(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al actualizar subgrupo', 'error');
        });
    }
}

// Función para eliminar un subgrupo
function eliminarSubgrupo(id) {
    if (confirm('¿Estás seguro de que quieres eliminar este subgrupo?')) {
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=eliminar_subgrupo&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje(data.message);
                cargarSubgrupos();
            } else {
                mostrarMensaje(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al eliminar subgrupo', 'error');
        });
    }
}

// Función para resetear votos de un usuario
function resetearVotosUsuario(id) {
    if (confirm('¿Estás seguro de que quieres resetear los votos de este usuario?')) {
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=resetear_votos_usuario&usuario_id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje(data.message);
                cargarUsuarios();
            } else {
                mostrarMensaje(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al resetear votos', 'error');
        });
    }
}

// Función para eliminar un usuario
function eliminarUsuario(id) {
    if (confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=eliminar_usuario&usuario_id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje(data.message);
                cargarUsuarios();
            } else {
                mostrarMensaje(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al eliminar usuario', 'error');
        });
    }
}

// Función para limpiar votos huérfanos
function limpiarVotosHuerfanos() {
    if (confirm('¿Estás seguro de que quieres limpiar los votos huérfanos?')) {
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=limpiar_votos_huerfanos'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje(data.message);
                cargarUsuarios();
            } else {
                mostrarMensaje(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al limpiar votos huérfanos', 'error');
        });
    }
}

// Función para resetear todos los votos
function resetearTodosLosVotos() {
    if (confirm('¿Estás seguro de que quieres resetear TODOS los votos?')) {
        fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=resetear_todos_los_votos'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje(data.message);
                cargarUsuarios();
                cargarSubgrupos();
            } else {
                mostrarMensaje(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al resetear todos los votos', 'error');
        });
    }
}

// Manejar el formulario de agregar subgrupo
document.getElementById('agregarSubgrupoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const grupoId = formData.get('grupo_id');
    const nombre = formData.get('nombre');
    
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=agregar_subgrupo&grupo_id=${grupoId}&nombre=${encodeURIComponent(nombre)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje(data.message);
            cerrarAgregarSubgrupo();
            cargarSubgrupos();
        } else {
            mostrarMensaje(data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al agregar subgrupo', 'error');
    });
});

// Función para cargar ganadores
function cargarGanadores() {
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=obtener_resultados'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarGanadoresPorAño(data.resultados);
            mostrarGanadorGeneral(data.resultados);
            mostrarResultadosDetallados(data.resultados);
        } else {
            mostrarMensaje('Error al cargar ganadores', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al cargar ganadores', 'error');
    });
}

// Función para mostrar ganadores por año
function mostrarGanadoresPorAño(resultados) {
    const winnersByYear = document.getElementById('winnersByYear');
    winnersByYear.innerHTML = '';
    
    const grupos = {};
    resultados.forEach(resultado => {
        if (!grupos[resultado.grupo_id]) {
            grupos[resultado.grupo_id] = {
                nombre: resultado.grupo_nombre,
                subgrupos: []
            };
        }
        grupos[resultado.grupo_id].subgrupos.push(resultado);
    });
    
    Object.values(grupos).forEach(grupo => {
        const maxVotos = Math.max(...grupo.subgrupos.map(s => parseInt(s.votos)));
        const ganador = grupo.subgrupos.find(s => parseInt(s.votos) === maxVotos);
        
        const winnerCard = document.createElement('div');
        winnerCard.className = 'winner-card-admin';
        winnerCard.innerHTML = `
            <h5>${grupo.nombre}</h5>
            <p class="winner-name">${ganador ? ganador.nombre : 'Sin votos'}</p>
            <p class="winner-votes">${ganador ? ganador.votos : 0} votos</p>
        `;
        winnersByYear.appendChild(winnerCard);
    });
}

// Función para mostrar ganador general
function mostrarGanadorGeneral(resultados) {
    const overallWinnerAdmin = document.getElementById('overallWinnerAdmin');
    const maxVotos = Math.max(...resultados.map(r => parseInt(r.votos)));
    const ganador = resultados.find(r => parseInt(r.votos) === maxVotos);
    
    overallWinnerAdmin.innerHTML = `
        <h5>${ganador ? ganador.nombre : 'Sin votos'}</h5>
        <p>Grupo: ${ganador ? ganador.grupo_nombre : '-'}</p>
        <p>Total de votos: ${ganador ? ganador.votos : 0}</p>
    `;
}

// Función para mostrar resultados detallados
function mostrarResultadosDetallados(resultados) {
    const detailedResultsAdmin = document.getElementById('detailedResultsAdmin');
    detailedResultsAdmin.innerHTML = '';
    
    const grupos = {};
    resultados.forEach(resultado => {
        if (!grupos[resultado.grupo_id]) {
            grupos[resultado.grupo_id] = {
                nombre: resultado.grupo_nombre,
                subgrupos: []
            };
        }
        grupos[resultado.grupo_id].subgrupos.push(resultado);
    });
    
    Object.values(grupos).forEach(grupo => {
        const grupoDiv = document.createElement('div');
        grupoDiv.className = 'result-group-admin';
        grupoDiv.innerHTML = `<h5>${grupo.nombre}</h5>`;
        
        const totalVotos = grupo.subgrupos.reduce((sum, s) => sum + parseInt(s.votos), 0);
        
        grupo.subgrupos.forEach(subgrupo => {
            const porcentaje = totalVotos > 0
                ? ((parseInt(subgrupo.votos) / totalVotos) * 100).toFixed(1)
                : 0;
                
            const subgrupoDiv = document.createElement('div');
            subgrupoDiv.className = 'result-item-admin';
            subgrupoDiv.innerHTML = `
                <span class="result-name">${subgrupo.nombre}</span>
                <div class="progress-bar-admin">
                    <div class="progress-admin" style="width: ${porcentaje}%"></div>
                </div>
                <span class="result-votes">${subgrupo.votos} votos (${porcentaje}%)</span>
            `;
            grupoDiv.appendChild(subgrupoDiv);
        });
        
        detailedResultsAdmin.appendChild(grupoDiv);
    });
}

// Función para cargar configuración
function cargarConfiguracion() {
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=obtener_configuracion'
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('configuracionContainer');
        
        if (data.success) {
            const config = data.configuracion;
            
            // Obtener fecha/hora actual para establecer valores por defecto
            const now = new Date();
            const fechaNow = now.toISOString().slice(0, 16);
            const fechaFin = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
            
            container.innerHTML = `
                <div class="config-form">
                    <div class="form-group-config">
                        <label for="inicio_votacion">Fecha y Hora de Inicio:</label>
                        <input type="datetime-local" id="inicio_votacion" value="${config.inicio_votacion ? config.inicio_votacion.slice(0, 16) : fechaNow}">
                    </div>
                    
                    <div class="form-group-config">
                        <label for="fin_votacion">Fecha y Hora de Fin:</label>
                        <input type="datetime-local" id="fin_votacion" value="${config.fin_votacion ? config.fin_votacion.slice(0, 16) : fechaFin}">
                    </div>
                    
                    <div class="form-group-config">
                        <button class="submit-button-config" onclick="guardarConfiguracion()">💾 Guardar Configuración</button>
                    </div>
                    
                    <div class="config-info">
                        <h4>📅 Estado Actual:</h4>
                        <p><strong>Inicio:</strong> ${config.inicio_votacion ? new Date(config.inicio_votacion).toLocaleString('es-AR') : 'No configurado'}</p>
                        <p><strong>Fin:</strong> ${config.fin_votacion ? new Date(config.fin_votacion).toLocaleString('es-AR') : 'No configurado'}</p>
                        <p><strong>Estado:</strong> <span id="estadoVotacion">Verificando...</span></p>
                    </div>
                </div>
            `;
            
            // Verificar el estado actual de la votación
            verificarEstadoVotacion(config);
        } else {
            container.innerHTML = '<p>Error al cargar la configuración</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('configuracionContainer').innerHTML = '<p>Error al cargar la configuración</p>';
    });
}

// Función para verificar el estado actual de la votación
function verificarEstadoVotacion(config) {
    const estadoElement = document.getElementById('estadoVotacion');
    const ahora = new Date();
    const inicio = config.inicio_votacion ? new Date(config.inicio_votacion) : null;
    const fin = config.fin_votacion ? new Date(config.fin_votacion) : null;
    
    if (!inicio || !fin) {
        estadoElement.innerHTML = '<span style="color: #666;">No configurado</span>';
        return;
    }
    
    if (ahora < inicio) {
        estadoElement.innerHTML = '<span style="color: #3498db;">⏳ Pendiente - La votación aún no ha comenzado</span>';
    } else if (ahora >= inicio && ahora <= fin) {
        estadoElement.innerHTML = '<span style="color: #27ae60;">✅ Activa - Los usuarios pueden votar</span>';
    } else {
        estadoElement.innerHTML = '<span style="color: #e74c3c;">❌ Finalizada - La votación ha terminado</span>';
    }
}

// Función para guardar configuración
function guardarConfiguracion() {
    const inicio = document.getElementById('inicio_votacion').value;
    const fin = document.getElementById('fin_votacion').value;
    
    if (!inicio || !fin) {
        mostrarMensaje('Por favor completa todos los campos', 'error');
        return;
    }
    
    if (new Date(inicio) >= new Date(fin)) {
        mostrarMensaje('La fecha de fin debe ser posterior a la fecha de inicio', 'error');
        return;
    }
    
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=guardar_configuracion&inicio_votacion=${inicio}&fin_votacion=${fin}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje(data.message);
            cargarConfiguracion();
        } else {
            mostrarMensaje(data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al guardar la configuración', 'error');
    });
}

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    cargarSubgrupos();
});
