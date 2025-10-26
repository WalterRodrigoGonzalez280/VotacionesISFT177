document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.getElementById('token');
    
    if (tokenInput) {
        // Permitir solo números
        tokenInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Limitar a 4 dígitos
        tokenInput.addEventListener('keypress', function(e) {
            if (this.value.length >= 4 && e.key !== 'Backspace' && e.key !== 'Delete') {
                e.preventDefault();
            }
        });
    }
});
