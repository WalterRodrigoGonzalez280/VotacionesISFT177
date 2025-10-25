<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Registro</title>
    <link rel="stylesheet" href="../css/registro.css">
    <link rel="icon" href="../assets/img/Logo.png" type="image/png">

</head>

<body>
    <div class="container">
        <div class="form-wrapper">
            <h1 class="form-title">Registro de Usuario</h1>
            <form id="registroForm" action="registro_validar.php" method="post">

                <div class="input-group">
                    <label for="usuario" class="input-label">Nombre de Usuario</label>
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        class="input-field"
                        required
                        minlength="3"
                        maxlength="20"
                        pattern="[a-zA-Z]{3,20}"
                        placeholder="Elige un nombre de usuario">
                    <div class="validation-message">
                        Solo letras puedes utilizar letras (3-20 caracteres)
                    </div>
                </div>

                <div class="input-group">
                    <label for="correo" class="input-label">Correo Electrónico</label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        class="input-field"
                        required
                        maxlength="100"
                        pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                        placeholder="usuario@ejemplo.com">
                    <div class="validation-message">
                        Ingresa un correo electrónico válido
                    </div>
                </div>



                <button type="submit" class="submit-btn">
                    Registrarse
                </button>

                <div id="successMessage" class="success-message">
                    ¡Registro exitoso! Bienvenido a nuestra plataforma.
                </div>
            </form>
        </div>
    </div>
</body>

</html>