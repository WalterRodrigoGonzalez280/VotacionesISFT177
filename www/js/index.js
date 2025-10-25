        let currentUser = null;
        let userVotes = {1: false, 2: false, 3: false};
        
        // Mostrar modal de registro al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar si hay una sesión de administrador
            const adminSession = localStorage.getItem('adminSession');
            if (adminSession === 'true') {
                // Mostrar información de administrador
                document.getElementById('adminInfo').style.display = 'block';
                mostrarMensaje('Sesión de administrador activa. Usa el botón "📊 Panel Admin" para acceder al panel.', 'info');
            } else {
                // Verificar si hay un usuario guardado en localStorage
                const savedUser = localStorage.getItem('currentUser');
                if (savedUser) {
                    try {
                        currentUser = JSON.parse(savedUser);
                        document.getElementById('userName').textContent = currentUser.nombre;
                        document.getElementById('userInfo').style.display = 'block';
                        verificarEstadoVotos();
                    } catch (e) {
                        mostrarModalRegistro();
                    }
                } else {
                    mostrarModalRegistro();
                }
            }
            obtenerResultados();
            
            // Agregar event listener adicional para el botón de agregar subgrupo
            const btnAgregar = document.getElementById('btnAgregarSubgrupo');
            if (btnAgregar) {
                btnAgregar.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Botón de agregar subgrupo clickeado via event listener');
                    mostrarAgregarSubgrupo();
                });
            }
            
            // Función de prueba alternativa
            window.testModal = function() {
                console.log('=== PRUEBA ALTERNATIVA DEL MODAL ===');
                const modal = document.getElementById('agregarSubgrupoModal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.style.visibility = 'visible';
                    modal.style.opacity = '1';
                    console.log('Modal mostrado con método alternativo');
                } else {
                    console.error('Modal no encontrado en método alternativo');
                }
            };
        });
        
        // Función para mostrar modal de registro
        function mostrarModalRegistro() {
            document.getElementById('registrationModal').style.display = 'flex';
        }
        
        // Función para ocultar modal
        function ocultarModal() {
            document.getElementById('registrationModal').style.display = 'none';
        }
        
        // Función para cambiar pestañas
        function cambiarTab(tabName) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar pestaña seleccionada
            if (tabName === 'registro') {
                document.getElementById('registroTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTab(\'registro\')"]').classList.add('active');
            } else if (tabName === 'login') {
                document.getElementById('loginTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTab(\'login\')"]').classList.add('active');
            } else if (tabName === 'admin') {
                document.getElementById('adminTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTab(\'admin\')"]').classList.add('active');
            }
        }
        
        // Manejar registro de usuario
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nombre = document.getElementById('nombre').value;
            const email = document.getElementById('email').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=registrar_usuario&nombre=${encodeURIComponent(nombre)}&email=${encodeURIComponent(email)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentUser = {
                        id: data.usuario_id,
                        nombre: nombre,
                        email: email
                    };
                    
                    // Guardar usuario en localStorage
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                    
                    document.getElementById('userName').textContent = nombre;
                    document.getElementById('userInfo').style.display = 'block';
                    
                    ocultarModal();
                    mostrarMensaje('¡Registro exitoso! Ya puedes votar.', 'success');
                    
                    // Verificar estado de votos del usuario
                    verificarEstadoVotos();
                } else {
                    mostrarMensaje('Error al registrarse: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Manejar login de usuario
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('loginEmail').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login_usuario&email=${encodeURIComponent(email)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentUser = {
                        id: data.usuario.id,
                        nombre: data.usuario.nombre,
                        email: data.usuario.email
                    };
                    
                    // Guardar usuario en localStorage
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                    
                    document.getElementById('userName').textContent = data.usuario.nombre;
                    document.getElementById('userInfo').style.display = 'block';
                    
                    ocultarModal();
                    mostrarMensaje('¡Bienvenido de vuelta!', 'success');
                    
                    // Verificar estado de votos del usuario
                    verificarEstadoVotos();
                } else {
                    mostrarMensaje('Error al iniciar sesión: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Manejar login de administrador
        document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('adminEmail').value;
            const password = document.getElementById('adminPassword').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login_admin&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Guardar sesión de administrador en localStorage
                    localStorage.setItem('adminSession', 'true');
                    
                    ocultarModal();
                    mostrarAdminPanel();
                    mostrarMensaje('Acceso de administrador autorizado', 'success');
                } else {
                    mostrarMensaje('Credenciales incorrectas', 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Función para cerrar sesión
        function cerrarSesion() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                // Limpiar datos del usuario
                currentUser = null;
                userVotes = {1: false, 2: false, 3: false};
                
                // Limpiar localStorage
                localStorage.removeItem('currentUser');
                
                // Ocultar información del usuario
                document.getElementById('userInfo').style.display = 'none';
                
                // Limpiar estado de votación
                document.querySelectorAll('.subgrupo-card').forEach(card => {
                    card.classList.remove('voted', 'not-voted');
                    const button = card.querySelector('.vote-button');
                    button.disabled = false;
                    button.textContent = 'Votar';
                });
                
                // Mostrar modal de login/registro
                mostrarModalRegistro();
                
                mostrarMensaje('Sesión cerrada correctamente', 'success');
            }
        }
        
        // Función para verificar estado de votos del usuario
        async function verificarEstadoVotos() {
            if (!currentUser) return;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verificar_usuario&usuario_id=${currentUser.id}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    userVotes = {
                        1: data.votos.votos_1er_año,
                        2: data.votos.votos_2do_año,
                        3: data.votos.votos_3er_año
                    };
                    
                    actualizarEstadoVotos();
                }
            } catch (error) {
                console.error('Error verificando votos:', error);
            }
        }
        
        // Función para actualizar estado visual de votos
        function actualizarEstadoVotos() {
            const status1er = document.getElementById('voteStatus1er');
            const status2do = document.getElementById('voteStatus2do');
            const status3er = document.getElementById('voteStatus3er');
            
            status1er.textContent = userVotes[1] ? '✅ 1er Año' : '❌ 1er Año';
            status2do.textContent = userVotes[2] ? '✅ 2do Año' : '❌ 2do Año';
            status3er.textContent = userVotes[3] ? '✅ 3er Año' : '❌ 3er Año';
            
            // Deshabilitar botones de votación según el estado
            document.querySelectorAll('.subgrupo-card').forEach(card => {
                const grupoId = parseInt(card.dataset.grupo);
                const button = card.querySelector('.vote-button');
                
                if (userVotes[grupoId]) {
                    card.classList.remove('not-voted');
                    card.classList.add('voted');
                    button.textContent = '✓ Votado';
                    button.disabled = true;
                } else {
                    card.classList.remove('voted');
                    card.classList.add('not-voted');
                    button.textContent = 'Votar';
                    button.disabled = false;
                }
            });
        }
        
        // Función para resetear estado visual de votos (cuando se blanquean)
        function resetearEstadoVisualVotos() {
            userVotes = {1: false, 2: false, 3: false};
            actualizarEstadoVotos();
        }
        
        // Función para actualizar estado visual basado en resultados del servidor
        function actualizarEstadoVisualDesdeResultados(resultados) {
            if (!currentUser || !resultados) return;
            
            // Limpiar todos los estados visuales primero
            document.querySelectorAll('.subgrupo-card').forEach(card => {
                card.classList.remove('voted', 'not-voted');
                const button = card.querySelector('.vote-button');
                button.textContent = 'Votar';
                button.disabled = false;
            });
            
            // Marcar los subgrupos según el estado del usuario
            resultados.forEach(resultado => {
                const card = document.querySelector(`[data-id="${resultado.id}"]`);
                if (card) {
                    if (resultado.usuario_voto == 1) {
                        // Usuario votó en este subgrupo
                        card.classList.add('voted');
                        const button = card.querySelector('.vote-button');
                        button.textContent = '✓ Votado';
                        button.disabled = true;
                    } else {
                        // Usuario no votó en este subgrupo
                        card.classList.add('not-voted');
                        const button = card.querySelector('.vote-button');
                        button.textContent = 'Votar';
                        button.disabled = false;
                    }
                }
            });
        }
        
        // Función para mostrar panel de administración
        function mostrarAdminPanel() {
            // Ocultar información de usuario si está visible
            document.getElementById('userInfo').style.display = 'none';
            
            // Mostrar información de administrador
            document.getElementById('adminInfo').style.display = 'block';
            
            // Mostrar panel de administración
            document.getElementById('adminPanel').style.display = 'block';
            cargarUsuarios();
            cargarSubgrupos();
        }
        
        // Función para cerrar sesión de administrador (solo oculta el panel)
        function cerrarSesionAdmin() {
            // Ocultar panel de administración
            document.getElementById('adminPanel').style.display = 'none';
            
            mostrarMensaje('Panel de administración cerrado. Puedes volver a abrirlo con el botón "📊 Panel Admin"', 'info');
        }
        
        // Función para cerrar completamente la sesión de administrador
        function cerrarSesionAdminCompleta() {
            if (confirm('¿Estás seguro de que quieres cerrar completamente la sesión de administrador?')) {
                // Ocultar información de administrador
                document.getElementById('adminInfo').style.display = 'none';
                
                // Ocultar panel de administración
                document.getElementById('adminPanel').style.display = 'none';
                
                // Limpiar cualquier estado de administrador
                localStorage.removeItem('adminSession');
                
                // Mostrar modal de registro
                mostrarModalRegistro();
                
                mostrarMensaje('Sesión de administrador cerrada completamente', 'success');
            }
        }
        
        // Función para resetear votos de un usuario
        async function resetearVotosUsuario(usuarioId, nombreUsuario) {
            if (confirm(`¿Estás seguro de que quieres resetear los votos de ${nombreUsuario}?`)) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=resetear_votos_usuario&usuario_id=${usuarioId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarMensaje(`Votos de ${nombreUsuario} reseteados correctamente. El usuario puede volver a votar.`, 'success');
                        // Actualizar la lista de usuarios
                        cargarUsuarios();
                        // Actualizar resultados en tiempo real para reflejar los votos eliminados
                        obtenerResultados();
                        // Si el usuario reseteado está logueado, actualizar su estado visual
                        if (currentUser && currentUser.id == usuarioId) {
                            resetearEstadoVisualVotos();
                            mostrarMensaje('Tus votos han sido reseteados. Puedes votar nuevamente.', 'info');
                        }
                    } else {
                        mostrarMensaje('Error: ' + data.error, 'error');
                    }
                } catch (error) {
                    mostrarMensaje('Error de conexión', 'error');
                }
            }
        }
        
        // Función para eliminar un usuario
        async function eliminarUsuario(usuarioId, nombreUsuario) {
            if (confirm(`⚠️ ELIMINACIÓN COMPLETA DE USUARIO\n\n¿Estás seguro de que quieres eliminar completamente al usuario "${nombreUsuario}"?\n\nEsta acción eliminará:\n• Todos los votos del usuario\n• Toda la información del usuario\n• Su historial completo\n\nEsta acción NO se puede deshacer.`)) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=eliminar_usuario&usuario_id=${usuarioId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarMensaje(`✅ Usuario "${nombreUsuario}" eliminado completamente del sistema.\n\nSe han eliminado:\n• Todos sus votos\n• Toda su información\n• Su historial completo\n\nLos conteos de votos se han actualizado automáticamente.`, 'success');
                        // Actualizar la lista de usuarios
                        cargarUsuarios();
                        // Actualizar resultados en tiempo real para reflejar los votos eliminados
                        obtenerResultados();
                    } else {
                        mostrarMensaje('Error: ' + data.error, 'error');
                    }
                } catch (error) {
                    mostrarMensaje('Error de conexión', 'error');
                }
            }
        }
        
        // Función para resetear TODOS los votos del sistema
        async function resetearTodosLosVotos() {
            if (confirm('⚠️ ADVERTENCIA: ¿Estás seguro de que quieres resetear TODOS los votos del sistema?\n\nEsta acción eliminará todos los votos de todos los usuarios y no se puede deshacer.')) {
                if (confirm('🚨 CONFIRMACIÓN FINAL: Esta acción eliminará TODOS los votos del sistema.\n\n¿Estás completamente seguro?')) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=resetear_todos_los_votos'
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            mostrarMensaje('✅ TODOS los votos han sido reseteados. El conteo está en cero.', 'success');
                            // Actualizar todas las listas
                            cargarUsuarios();
                            cargarSubgrupos();
                            // Actualizar resultados para mostrar conteos en cero
                            obtenerResultados();
                            // Si hay usuarios logueados, resetear su estado visual
                            if (currentUser) {
                                resetearEstadoVisualVotos();
                                mostrarMensaje('Tus votos han sido reseteados por el administrador.', 'info');
                            }
                        } else {
                            mostrarMensaje('Error: ' + data.error, 'error');
                        }
                    } catch (error) {
                        mostrarMensaje('Error de conexión', 'error');
                    }
                }
            }
        }
        
        // Función para limpiar votos huérfanos
        async function limpiarVotosHuerfanos() {
            if (confirm('🧹 ¿Limpiar votos huérfanos?\n\nEsta acción eliminará votos que no tienen usuario asociado.\n\n¿Continuar?')) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=limpiar_votos_huerfanos'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarMensaje(`✅ ${data.message}`, 'success');
                        // Actualizar todas las listas
                        cargarUsuarios();
                        cargarSubgrupos();
                        // Actualizar resultados para mostrar conteos corregidos
                        obtenerResultados();
                    } else {
                        mostrarMensaje('Error: ' + data.error, 'error');
                    }
                } catch (error) {
                    mostrarMensaje('Error de conexión', 'error');
                }
            }
        }
        
        // Función para votar
        async function votar(subgrupoId, grupoId) {
            if (!currentUser) {
                mostrarMensaje('Debes registrarte primero', 'error');
                return;
            }
            
            if (userVotes[grupoId]) {
                mostrarMensaje('Ya has votado en este grupo', 'error');
                return;
            }
            
            const button = document.querySelector(`[data-id="${subgrupoId}"] .vote-button`);
            button.disabled = true;
            button.textContent = 'Votando...';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=votar&usuario_id=${currentUser.id}&subgrupo_id=${subgrupoId}&grupo_id=${grupoId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    userVotes[grupoId] = true;
                    mostrarMensaje('¡Voto registrado exitosamente!', 'success');
                    
                    // Marcar la opción como votada
                    const card = document.querySelector(`[data-id="${subgrupoId}"]`);
                    card.classList.add('voted');
                    button.textContent = '✓ Votado';
                    
                    // Actualizar estado visual
                    actualizarEstadoVotos();
                    
                    // Actualizar resultados
                    actualizarResultados(data.resultados);
                    
                    // Actualizar automáticamente las listas del panel de administración si está abierto
                    if (document.getElementById('adminPanel').style.display === 'block') {
                        actualizarUsuariosAutomaticamente();
                        actualizarListaAutomaticamente();
                    }
                } else {
                    mostrarMensaje('Error al registrar el voto: ' + data.error, 'error');
                    button.disabled = false;
                    button.textContent = 'Votar';
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
                button.disabled = false;
                button.textContent = 'Votar';
            }
        }
        
        // Función para obtener resultados
        async function obtenerResultados() {
            try {
                const usuarioId = currentUser ? currentUser.id : null;
                const body = usuarioId ? 
                    `action=obtener_resultados&usuario_id=${usuarioId}` : 
                    'action=obtener_resultados';
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body
                });
                
                const data = await response.json();
                
                if (data.success) {
                    actualizarResultados(data.resultados);
                }
            } catch (error) {
                console.error('Error obteniendo resultados:', error);
            }
        }
        
        // Función para actualizar la visualización de resultados
        function actualizarResultados(resultados) {
            if (!resultados || resultados.length === 0) {
                document.getElementById('resultsContainer').innerHTML = '<div class="loading">No hay resultados disponibles</div>';
                document.getElementById('winnersContainer').innerHTML = '<div class="loading">No hay ganadores</div>';
                document.getElementById('overallWinnerContainer').innerHTML = '<div class="loading">No hay ganador general</div>';
                return;
            }
            
            // Organizar resultados por grupos
            const gruposResultados = {};
            resultados.forEach(resultado => {
                const grupoId = resultado.grupo_id;
                if (!gruposResultados[grupoId]) {
                    gruposResultados[grupoId] = {
                        nombre: resultado.grupo_nombre,
                        subgrupos: []
                    };
                }
                gruposResultados[grupoId].subgrupos.push(resultado);
            });
            
            // Actualizar estado visual basado en los votos del usuario
            actualizarEstadoVisualDesdeResultados(resultados);
            
            // Actualizar ganadores por grupo
            actualizarGanadoresPorGrupo(gruposResultados);
            
            // Actualizar ganador general
            actualizarGanadorGeneral(resultados);
            
            // Actualizar resultados detallados
            actualizarResultadosDetallados(gruposResultados);
            
            // Actualizar timestamp
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
        }
        
        // Función para actualizar ganadores por grupo
        function actualizarGanadoresPorGrupo(gruposResultados) {
            const winnersContainer = document.getElementById('winnersContainer');
            let html = '';
            
            Object.values(gruposResultados).forEach(grupo => {
                if (grupo.subgrupos.length > 0) {
                    // Encontrar el subgrupo con más votos
                    const ganador = grupo.subgrupos.reduce((max, current) => 
                        parseInt(current.votos) > parseInt(max.votos) ? current : max
                    );
                    
                    html += `
                        <div class="winner-card">
                            <h4>${grupo.nombre}</h4>
                            <div class="winner-name">${ganador.nombre}</div>
                            <div class="winner-votes">${ganador.votos} votos</div>
                        </div>
                    `;
                }
            });
            
            winnersContainer.innerHTML = html || '<div class="loading">No hay ganadores</div>';
        }
        
        // Función para actualizar ganador general
        function actualizarGanadorGeneral(resultados) {
            const overallWinnerContainer = document.getElementById('overallWinnerContainer');
            
            if (resultados.length === 0) {
                overallWinnerContainer.innerHTML = '<div class="loading">No hay ganador general</div>';
                return;
            }
            
            // Encontrar el subgrupo con más votos de todos
            const ganadorGeneral = resultados.reduce((max, current) => 
                parseInt(current.votos) > parseInt(max.votos) ? current : max
            );
            
            const totalVotosGenerales = resultados.reduce((sum, item) => sum + parseInt(item.votos), 0);
            
            overallWinnerContainer.innerHTML = `
                <h4>${ganadorGeneral.nombre}</h4>
                <div class="total-votes">${ganadorGeneral.votos} votos</div>
                <p>Del grupo: ${ganadorGeneral.grupo_nombre}</p>
                <p>Total de votos en el sistema: ${totalVotosGenerales}</p>
            `;
        }
        
        // Función para actualizar resultados detallados
        function actualizarResultadosDetallados(gruposResultados) {
            const container = document.getElementById('resultsContainer');
            
            let html = '';
            Object.values(gruposResultados).forEach(grupo => {
                const totalVotosGrupo = grupo.subgrupos.reduce((sum, item) => sum + parseInt(item.votos), 0);
                
                html += `
                    <div class="group-results">
                        <h3>${grupo.nombre}</h3>
                        <div class="subgrupos-results">
                `;
                
                grupo.subgrupos.forEach(subgrupo => {
                    const porcentaje = totalVotosGrupo > 0 ? (subgrupo.votos / totalVotosGrupo) * 100 : 0;
                    
                    html += `
                        <div class="result-card">
                            <h4>${subgrupo.nombre}</h4>
                            <div class="vote-count">${subgrupo.votos} votos</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${porcentaje}%"></div>
                            </div>
                            <p>${porcentaje.toFixed(1)}% del grupo</p>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Función para mostrar mensajes
        function mostrarMensaje(mensaje, tipo) {
            const container = document.getElementById('messageContainer');
            container.innerHTML = `<div class="${tipo}">${mensaje}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
        
        // Actualizar resultados cada 3 segundos
        setInterval(obtenerResultados, 3000);
        
        // Funciones del Panel de Administración
        function mostrarLoginAdmin() {
            document.getElementById('adminLoginModal').style.display = 'flex';
        }
        
        function cerrarLoginAdmin() {
            document.getElementById('adminLoginModal').style.display = 'none';
        }
        
        function mostrarPanelAdmin() {
            document.getElementById('adminPanel').style.display = 'block';
            cargarUsuarios();
        }
        
        function cerrarPanelAdmin() {
            document.getElementById('adminPanel').style.display = 'none';
        }
        
        function cambiarTabAdmin(tabName) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.admin-tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.admin-tab').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar pestaña seleccionada
            if (tabName === 'usuarios') {
                document.getElementById('usuariosTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTabAdmin(\'usuarios\')"]').classList.add('active');
            } else {
                document.getElementById('subgruposTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTabAdmin(\'subgrupos\')"]').classList.add('active');
            }
        }
        
        // Manejar login de administrador
        document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('adminEmail').value;
            const password = document.getElementById('adminPassword').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login_admin&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    cerrarLoginAdmin();
                    mostrarPanelAdmin();
                    mostrarMensaje('Acceso de administrador exitoso', 'success');
                } else {
                    mostrarMensaje('Error de acceso: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Cargar usuarios
        async function cargarUsuarios() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=obtener_usuarios'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarUsuarios(data.usuarios);
                } else {
                    document.getElementById('usuariosList').innerHTML = '<div class="error">Error al cargar usuarios</div>';
                }
            } catch (error) {
                document.getElementById('usuariosList').innerHTML = '<div class="error">Error al cargar usuarios</div>';
            }
        }
        
        // Función para actualizar automáticamente la lista de usuarios
        function actualizarUsuariosAutomaticamente() {
            cargarUsuarios();
        }
        
        function mostrarUsuarios(usuarios) {
            const container = document.getElementById('usuariosList');
            
            if (usuarios.length === 0) {
                container.innerHTML = '<div class="loading">No hay usuarios registrados</div>';
                return;
            }
            
            let html = `
                <table class="usuarios-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Total Votos</th>
                            <th>Estado por Grupo</th>
                            <th>Votos Detalle</th>
                            <th>Registrado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            usuarios.forEach(usuario => {
                const fechaRegistro = new Date(usuario.fecha_registro).toLocaleString();
                const votosDetalle = usuario.votos_detalle || 'Sin votos';
                
                html += `
                    <tr>
                        <td>
                            <div class="user-name">${usuario.nombre}</div>
                        </td>
                        <td>
                            <div class="user-details">${usuario.email}</div>
                        </td>
                        <td>
                            <div class="user-details"><strong>${usuario.total_votos}</strong></div>
                        </td>
                        <td>
                            <div class="vote-status-inline">
                                <span class="${usuario.votos_1er_año ? 'voted' : 'not-voted'}">
                                    ${usuario.votos_1er_año ? '✅ 1er' : '❌ 1er'}
                                </span>
                                <span class="${usuario.votos_2do_año ? 'voted' : 'not-voted'}">
                                    ${usuario.votos_2do_año ? '✅ 2do' : '❌ 2do'}
                                </span>
                                <span class="${usuario.votos_3er_año ? 'voted' : 'not-voted'}">
                                    ${usuario.votos_3er_año ? '✅ 3er' : '❌ 3er'}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="user-details" style="max-width: 300px; word-wrap: break-word;">
                                ${votosDetalle}
                            </div>
                        </td>
                        <td>
                            <div class="user-details">${fechaRegistro}</div>
                        </td>
                        <td>
                            <div class="user-actions">
                                <button class="action-btn reset-votes-btn" onclick="resetearVotosUsuario(${usuario.id}, '${usuario.nombre}')" title="Resetear votos">
                                    🔄 Resetear Votos
                                </button>
                                <button class="action-btn delete-user-btn" onclick="eliminarUsuario(${usuario.id}, '${usuario.nombre}')" title="Eliminar usuario">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            container.innerHTML = html;
        }
        
        // Cargar subgrupos
        async function cargarSubgrupos() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=obtener_subgrupos_admin'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarSubgrupos(data.subgrupos);
                } else {
                    document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
                }
            } catch (error) {
                document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
            }
        }
        
        function mostrarSubgrupos(subgrupos) {
            const container = document.getElementById('subgruposList');
            
            // Organizar subgrupos por grupos
            const gruposOrganizados = {};
            subgrupos.forEach(subgrupo => {
                const grupoId = subgrupo.grupo_id;
                if (!gruposOrganizados[grupoId]) {
                    gruposOrganizados[grupoId] = {
                        nombre: subgrupo.grupo_nombre,
                        subgrupos: []
                    };
                }
                gruposOrganizados[grupoId].subgrupos.push(subgrupo);
            });
            
            let html = '';
            
            // Mostrar los 3 grupos principales
            const grupos = [
                { id: 1, nombre: '1er Año' },
                { id: 2, nombre: '2do Año' },
                { id: 3, nombre: '3er Año' }
            ];
            
            grupos.forEach(grupo => {
                const subgruposDelGrupo = gruposOrganizados[grupo.id] ? gruposOrganizados[grupo.id].subgrupos : [];
                const totalSubgrupos = subgruposDelGrupo.length;
                const totalVotos = subgruposDelGrupo.reduce((sum, sg) => sum + parseInt(sg.total_votos), 0);
                
                html += `
                    <div class="grupo-card" onclick="mostrarSubgruposDelGrupo(${grupo.id}, '${grupo.nombre}')">
                        <div class="grupo-header">
                            <h3>${grupo.nombre}</h3>
                            <div class="grupo-stats">
                                <span class="stat-item">${totalSubgrupos} subgrupos</span>
                                <span class="stat-item">${totalVotos} votos totales</span>
                            </div>
                        </div>
                        <div class="grupo-arrow">▶️</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function mostrarSubgruposDelGrupo(grupoId, grupoNombre) {
            // Obtener subgrupos del grupo específico
            cargarSubgruposDelGrupo(grupoId, grupoNombre);
        }
        
        async function cargarSubgruposDelGrupo(grupoId, grupoNombre) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=obtener_subgrupos_admin&grupo_id=${grupoId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarSubgruposDetallados(data.subgrupos, grupoNombre);
                } else {
                    document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
                }
            } catch (error) {
                document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
            }
        }
        
        function mostrarSubgruposDetallados(subgrupos, grupoNombre) {
            const container = document.getElementById('subgruposList');
            
            let html = `
                <div class="grupo-detalle-header">
                    <button class="back-btn" onclick="cargarSubgrupos()">← Volver a Grupos</button>
                    <h3>Subgrupos de ${grupoNombre}</h3>
                </div>
                <div class="subgrupos-detalle">
            `;
            
            if (subgrupos.length === 0) {
                html += '<div class="loading">No hay subgrupos en este grupo</div>';
            } else {
                subgrupos.forEach(subgrupo => {
                    html += `
                        <div class="admin-item subgrupo-item" id="subgrupo-${subgrupo.id}">
                            <div class="admin-item-info">
                                <h4>${subgrupo.nombre}</h4>
                                <p><strong>Total votos:</strong> ${subgrupo.total_votos}</p>
                            </div>
                            <div class="admin-item-actions">
                                <button class="edit-btn" onclick="editarSubgrupo(${subgrupo.id}, '${subgrupo.nombre}')">✏️ Editar</button>
                                <button class="delete-btn" onclick="eliminarSubgrupo(${subgrupo.id})">🗑️ Eliminar</button>
                            </div>
                        </div>
                    `;
                });
            }
            
            html += `
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function editarSubgrupo(id, nombreActual) {
            const item = document.getElementById(`subgrupo-${id}`);
            const infoDiv = item.querySelector('.admin-item-info h4');
            
            infoDiv.innerHTML = `
                <input type="text" class="edit-input" value="${nombreActual}" id="edit-${id}">
                <button class="save-btn" onclick="guardarSubgrupo(${id})">💾 Guardar</button>
                <button class="cancel-btn" onclick="cancelarEdicion(${id}, '${nombreActual}')">❌ Cancelar</button>
            `;
        }
        
        async function guardarSubgrupo(id) {
            const nuevoNombre = document.getElementById(`edit-${id}`).value;
            
            if (!nuevoNombre.trim()) {
                mostrarMensaje('El nombre no puede estar vacío', 'error');
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=actualizar_subgrupo&id=${id}&nombre=${encodeURIComponent(nuevoNombre)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarMensaje('Subgrupo actualizado correctamente', 'success');
                    // Actualizar automáticamente la lista
                    actualizarListaAutomaticamente();
                    // Actualizar resultados en tiempo real
                    obtenerResultados();
                } else {
                    mostrarMensaje('Error: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión', 'error');
            }
        }
        
        function cancelarEdicion(id, nombreOriginal) {
            const item = document.getElementById(`subgrupo-${id}`);
            const infoDiv = item.querySelector('.admin-item-info h4');
            infoDiv.textContent = nombreOriginal;
        }
        
        async function eliminarSubgrupo(id) {
            if (!confirm('¿Estás seguro de que quieres eliminar este subgrupo? Esta acción eliminará todos los votos asociados.')) {
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=eliminar_subgrupo&id=${id}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarMensaje('Subgrupo eliminado correctamente', 'success');
                    // Actualizar automáticamente la lista
                    actualizarListaAutomaticamente();
                    // Actualizar resultados en tiempo real
                    obtenerResultados();
                } else {
                    mostrarMensaje('Error: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión', 'error');
            }
        }
        
        function mostrarAgregarSubgrupo() {
            console.log('=== INICIANDO mostrarAgregarSubgrupo ===');
            console.log('Intentando mostrar modal de agregar subgrupo');
            
            try {
                // Verificar que el DOM esté listo
                if (document.readyState !== 'complete') {
                    console.log('DOM no está completo, esperando...');
                    setTimeout(mostrarAgregarSubgrupo, 100);
                    return;
                }
                
                const modal = document.getElementById('agregarSubgrupoModal');
                console.log('Modal encontrado:', modal);
                
                if (modal) {
                    console.log('Estilos del modal antes:', modal.style.display);
                    modal.style.display = 'flex';
                    modal.style.zIndex = '3000';
                    console.log('Estilos del modal después:', modal.style.display);
                    console.log('Modal mostrado correctamente');
                    
                    // Verificar que el modal sea visible
                    const rect = modal.getBoundingClientRect();
                    console.log('Posición del modal:', rect);
                    
                    // Enfocar el primer campo del formulario
                    const nombreInput = document.getElementById('subgrupoNombre');
                    if (nombreInput) {
                        setTimeout(() => {
                            nombreInput.focus();
                            console.log('Campo de nombre enfocado');
                        }, 100);
                    } else {
                        console.warn('Campo de nombre no encontrado');
                    }
                } else {
                    console.error('Modal no encontrado');
                    alert('Error: No se pudo encontrar el modal de agregar subgrupo');
                }
            } catch (error) {
                console.error('Error al mostrar modal:', error);
                alert('Error al abrir el modal: ' + error.message);
            }
            console.log('=== FINALIZANDO mostrarAgregarSubgrupo ===');
        }
        
        function cerrarAgregarSubgrupo() {
            document.getElementById('agregarSubgrupoModal').style.display = 'none';
            document.getElementById('agregarSubgrupoForm').reset();
        }
        
        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('agregarSubgrupoModal');
            if (e.target === modal) {
                cerrarAgregarSubgrupo();
            }
        });
        
        // Manejar formulario de agregar subgrupo
        document.getElementById('agregarSubgrupoForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const grupoId = document.getElementById('grupoSelect').value;
            const nombre = document.getElementById('subgrupoNombre').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=agregar_subgrupo&grupo_id=${grupoId}&nombre=${encodeURIComponent(nombre)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarMensaje('Subgrupo agregado correctamente', 'success');
                    cerrarAgregarSubgrupo();
                    // Actualizar automáticamente la lista
                    actualizarListaAutomaticamente();
                    // Actualizar resultados en tiempo real
                    obtenerResultados();
                } else {
                    mostrarMensaje('Error: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión', 'error');
            }
        });
        
        // Función para actualizar automáticamente la lista según el contexto
        function actualizarListaAutomaticamente() {
            // Detectar si estamos en la vista de grupos o en vista detallada
            const container = document.getElementById('subgruposList');
            const backButton = container.querySelector('.back-btn');
            
            if (backButton) {
                // Estamos en vista detallada de un grupo específico
                // Obtener el grupo actual del título
                const titulo = container.querySelector('.grupo-detalle-header h3');
                if (titulo) {
                    const texto = titulo.textContent;
                    if (texto.includes('1er Año')) {
                        cargarSubgruposDelGrupo(1, '1er Año');
                    } else if (texto.includes('2do Año')) {
                        cargarSubgruposDelGrupo(2, '2do Año');
                    } else if (texto.includes('3er Año')) {
                        cargarSubgruposDelGrupo(3, '3er Año');
                    }
                }
            } else {
                // Estamos en la vista principal de grupos
                cargarSubgrupos();
            }
        }
        
        // Función para mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '🙈';
                toggle.classList.add('active');
            } else {
                input.type = 'password';
                toggle.textContent = '👁️';
                toggle.classList.remove('active');
            }
        }
    