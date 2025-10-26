// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo = 'success') {
    const messageContainer = document.getElementById('messageContainer');
    messageContainer.innerHTML = `<div class="message ${tipo}">${mensaje}</div>`;
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 3000);
}

// Función para realizar una votación
function votar(subgrupoId, grupoId) {
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=votar&subgrupo_id=${subgrupoId}&grupo_id=${grupoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje(data.message);
            
            // Actualizar el contador de votos para este subgrupo
            const voteCountElement = document.getElementById(`vote-count-${subgrupoId}`);
            if (voteCountElement) {
                const currentVotes = parseInt(voteCountElement.textContent);
                voteCountElement.textContent = `${currentVotes + 1} votos`;
            }
            
            // Cambiar el estilo de las tarjetas: verde para el votado, rojo para los demás del mismo grupo
            const todasLasTarjetas = document.querySelectorAll(`.subgrupo-card[data-grupo="${grupoId}"]`);
            todasLasTarjetas.forEach(card => {
                const cardId = parseInt(card.dataset.id);
                if (cardId === subgrupoId) {
                    // Tarjeta votada: verde
                    card.style.background = 'linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)';
                    card.style.color = 'white';
                    card.style.border = '2px solid #229954';
                } else {
                    // Otras tarjetas del mismo grupo: rojo
                    card.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                    card.style.color = 'white';
                    card.style.border = '2px solid #a93226';
                    card.style.opacity = '0.7';
                }
                // Deshabilitar el botón de votar
                const button = card.querySelector('.vote-button');
                if (button) {
                    button.disabled = true;
                    button.style.cursor = 'not-allowed';
                }
            });
            
            // Ocultar la información del evento después del primer voto
            const eventInfoSection = document.getElementById('eventInfoSection');
            if (eventInfoSection) {
                eventInfoSection.classList.add('hidden');
            }
            
            // Verificar si el usuario completó sus 3 votos desde la respuesta del servidor
            if (data.votos_completos === true) {
                console.log('Usuario completo todos los votos');
                mostrarMensaje('¡Has completado tus 3 votos! Redirigiendo...');
                // Esperar 2 segundos y luego redirigir a la página de agradecimiento
                setTimeout(() => {
                    window.location.href = 'gracias.php';
                }, 2000);
            }
        } else {
            mostrarMensaje(data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al procesar la votación', 'error');
    });
}

// Función para cerrar sesión
function cerrarSesion() {
    // Redirigir directamente a logout.php que llevará a despedida.php
    window.location.href = 'logout.php';
}

// Función para volver al panel de administración
function volverAlPanel() {
    window.location.href = 'admin.php';
}

// Función para ver resultados detallados en el panel de admin
function verResultados() {
    // Redirigir al panel de admin con el parámetro de tab=ganadores
    window.location.href = 'admin.php?tab=ganadores';
}

// Función para verificar el estado del usuario
function verificarUsuario() {
    return fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=verificar_usuario'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const usuario = data.usuario;
            const userInfo = document.getElementById('userInfo');
            
            document.getElementById('userName').textContent = usuario.nombre;
            document.getElementById('voteStatus1er').textContent = usuario.votos_1er_año ? '✅ 1er Año' : '❌ 1er Año';
            document.getElementById('voteStatus2do').textContent = usuario.votos_2do_año ? '✅ 2do Año' : '❌ 2do Año';
            document.getElementById('voteStatus3er').textContent = usuario.votos_3er_año ? '✅ 3er Año' : '❌ 3er Año';
            
            userInfo.style.display = 'block';
            
            // Si es administrador, deshabilitar todos los botones de votación
            if (data.es_admin) {
                const botonesVotar = document.querySelectorAll('.vote-button');
                botonesVotar.forEach(boton => {
                    boton.disabled = true;
                    boton.textContent = 'Solo para usuarios';
                    boton.style.cursor = 'not-allowed';
                    boton.style.opacity = '0.5';
                });
            }
            
            // Retornar true si completó los 3 votos
            const completo = usuario.votos_1er_año == 1 && usuario.votos_2do_año == 1 && usuario.votos_3er_año == 1;
            console.log('Verificando votos:', {
                votos_1er_año: usuario.votos_1er_año,
                votos_2do_año: usuario.votos_2do_año,
                votos_3er_año: usuario.votos_3er_año,
                completo: completo
            });
            return completo;
        } else {
            document.getElementById('userInfo').style.display = 'none';
            return false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al verificar usuario', 'error');
        return false;
    });
}

// Función para actualizar resultados
function actualizarResultados() {
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
            actualizarVotos(data.resultados);
            actualizarGanadores(data.resultados);
            actualizarGanadorGeneral(data.resultados);
            document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
        } else {
            mostrarMensaje('Error al actualizar resultados', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al obtener resultados', 'error');
    });
}

// Función para actualizar los conteos de votos (solo estado de botones)
function actualizarVotos(resultados) {
    const subgrupoCards = document.querySelectorAll('.subgrupo-card');
    subgrupoCards.forEach(card => {
        const subgrupoId = parseInt(card.dataset.id);
        const resultado = resultados.find(r => r.id === subgrupoId);
        if (resultado) {
            // No mostrar conteo de votos para usuarios normales
            const voteButton = card.querySelector('.vote-button');
            if (resultado.usuario_voto) {
                voteButton.disabled = true;
                voteButton.textContent = 'Ya votaste';
            } else {
                voteButton.disabled = false;
                voteButton.textContent = 'Votar';
            }
        }
    });
    
    const resultsContainer = document.getElementById('resultsContainer');
    if (resultsContainer) {
        resultsContainer.innerHTML = '';
    }
    
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
        grupoDiv.className = 'result-group';
        grupoDiv.innerHTML = `<h4>${grupo.nombre}</h4>`;
        
        grupo.subgrupos.forEach(subgrupo => {
            const porcentaje = grupo.subgrupos.reduce((sum, s) => sum + parseInt(s.votos), 0) > 0
                ? ((subgrupo.votos / grupo.subgrupos.reduce((sum, s) => sum + parseInt(s.votos), 0)) * 100).toFixed(1)
                : 0;
                
            const subgrupoDiv = document.createElement('div');
            subgrupoDiv.className = 'result-item';
            subgrupoDiv.innerHTML = `
                <span>${subgrupo.nombre}</span>
                <div class="progress-bar">
                    <div class="progress" style="width: ${porcentaje}%"></div>
                </div>
                <span>${subgrupo.votos} votos (${porcentaje}%)</span>
            `;
            grupoDiv.appendChild(subgrupoDiv);
        });
        
        resultsContainer.appendChild(grupoDiv);
    });
}

// Función para actualizar ganadores por grupo
function actualizarGanadores(resultados) {
    const winnersContainer = document.getElementById('winnersContainer');
    winnersContainer.innerHTML = '';
    
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
        winnerCard.className = 'winner-card';
        winnerCard.innerHTML = `
            <h4>${grupo.nombre}</h4>
            <p>${ganador ? ganador.nombre : 'Sin votos'}</p>
            <p>Votos: ${ganador ? ganador.votos : 0}</p>
        `;
        winnersContainer.appendChild(winnerCard);
    });
}

// Función para actualizar el ganador general
function actualizarGanadorGeneral(resultados) {
    const overallWinnerContainer = document.getElementById('overallWinnerContainer');
    const maxVotos = Math.max(...resultados.map(r => parseInt(r.votos)));
    const ganador = resultados.find(r => parseInt(r.votos) === maxVotos);
    
    overallWinnerContainer.innerHTML = `
        <h4>${ganador ? ganador.nombre : 'Sin votos'}</h4>
        <p>Grupo: ${ganador ? ganador.grupo_nombre : '-'}</p>
        <p>Votos: ${ganador ? ganador.votos : 0}</p>
    `;
}

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    verificarUsuario();
    // Los resultados solo son visibles para el administrador
    // Los usuarios normales no pueden ver los resultados
});