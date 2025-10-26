// Función para toggle de mostrar/ocultar contraseña
document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const input = document.getElementById(target);
            
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    // Cambiar el ícono a ojo cerrado (emoji)
                    this.textContent = '👁️';
                    this.alt = 'Ocultar contraseña';
                } else {
                    input.type = 'password';
                    // Cambiar el ícono a ojo abierto (emoji)
                    this.textContent = '👁️‍🗨️';
                    this.alt = 'Mostrar contraseña';
                }
            }
        });
    });
});
