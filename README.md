# Sistema de Votaciones ISFT177 - Entorno de Desarrollo con Docker

Este proyecto utiliza **Docker** para configurar un entorno completo de desarrollo PHP con Apache, MySQL y phpMyAdmin para el sistema de votaciones.

---
## Paso de instalación y ejecución del docker PHP-APACHE 

    Clonar el repositorio si contas con la key SSH:  
    
        git clone git@github.com/GonzaloOSierra/VotacionesISFT177.git
        cd VotacionesISFT177
        git checkout main


## Requisitos Previos

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- Git

---

## Estructura del Proyecto

```
    VotacionesISFT177/
    ├── Dockerfiles/
    │ └── php-apache/
    │        └── Dockerfile
    ├── dump/                   # Volúmenes de base de datos
    ├── www/                    # Código fuente de la aplicación
    │   ├── assets/             # Archivos estáticos (CSS, JS, imágenes)
    │   ├── controllers/        # Controladores PHP
    │   ├── models/             # Modelos (lógica de datos)
    │   ├── views/              # Vistas (HTML, templates)
    │   └── index.php           # Punto de entrada principal
    ├── .env                    # Variables de entorno
    ├── docker-compose.yml     # Configuración de Docker Compose
    ├── .gitignore
    └── README.md

``` 

---

## Sistema de Votaciones

Este entorno Docker está configurado específicamente para el desarrollo del **Sistema de Votaciones ISFT177**, que incluye:

- **Aplicación web PHP** con Apache para el sistema de votaciones
- **Base de datos MySQL 5.7** para almacenar datos de votantes, candidatos y resultados
- **phpMyAdmin** para gestión y administración de la base de datos
- **Entorno de desarrollo** optimizado para PHP 7.4 con extensiones MySQL

### Características del Sistema:
- Gestión de usuarios y autenticación
- Registro de candidatos y propuestas
- Proceso de votación seguro
- Generación de reportes y estadísticas
- Panel de administración

---

## Archivo `.env`

Crea un archivo `.env` en la raíz del proyecto con el siguiente contenido:

.env


```
# Base de datos MySQL
MYSQL_DATABASE=votacion_db
MYSQL_USER=votacion_user
MYSQL_ROOT_PASSWORD=root2025
MYSQL_PASSWORD=develop2025
MYSQL_SERVER=db

PHP_PORT=5000

# Configuración de PHPMyAdmin
PMA_HOST=mysql
PMA_USER=votacion_user
PMA_PASSWORD=develop2025
PMA_PORT=5005



```
---

## Comandos para levantar docker

    docker-compose up -d --build
    
    Construirá la imagen para el contenedor php-apache

        Levantará:

        -db: base de datos MySQL 5.7

        -App_votacion: contenedor PHP + Apache

        -phpmyadmin_votacion: interfaz web para MySQL

    Para verificar si se levanto el docker:  
        usar `docker ps`

## Apagar los contenedores

    Para detener y eliminar los contenedores:

    docker-compose down

    Si además querés eliminar los volúmenes (base de datos):

    docker-compose down -v

# Accesos

### Desde el navegador local:  
    http://localhost:5000/ ----> para la aplicación de votaciones (PHP + Apache)
    
    http://localhost:5005/ ----> para phpMyAdmin (gestión de base de datos)

    **Credenciales de acceso a MySQL / phpMyAdmin:**

        Usuario: votacion_user

        Contraseña: develop2025

    **Base de datos:** votacion_db

### Acceso desde otra computadora en la red

Si querés usar la aplicación desde otra máquina conectada a la misma red local (WiFi o cable), seguí estos pasos:

    1. Obtener la IP local del host

    Desde la computadora que ejecuta Docker:

    En Linux/macOS:
        ip a  -----> **192.168.1.40**


    En Windows (CMD o PowerShell):
        ipconfig


    2. Acceder desde otra computadora

    Desde cualquier navegador en otra máquina de la red:

    **Sistema de Votaciones (PHP + Apache):**
        http://192.168.1.40:5000

    **phpMyAdmin (gestión de base de datos):**
        http://192.168.1.40:5005

    3 Requisitos
    -Ambas computadoras deben estar conectadas a la misma red local.
    -Asegurate de que Docker esté corriendo en la computadora host (docker ps).
    -Verificá que el firewall del host no bloquee los puertos 5000 y 5005.

---

## Solución de Problemas

### Si Docker no se levanta correctamente:

1. **Limpiar contenedores y volúmenes:**
   ```bash
   docker-compose down -v
   docker system prune -f
   ```

2. **Verificar que el archivo .env existe:**
   ```bash
   ls -la .env
   ```

3. **Verificar que los directorios existen:**
   ```bash
   mkdir -p www dump
   ```

4. **Reconstruir las imágenes:**
   ```bash
   docker-compose up -d --build
   ```

### Verificar que todo funciona:

- **Aplicación web:** http://localhost:5000 (debe mostrar "Sistema de Votaciones")
- **phpMyAdmin:** http://localhost:5005 (debe mostrar la interfaz de login)
- **Contenedores activos:** `docker ps` (debe mostrar 3 contenedores corriendo)

### Comandos útiles:

```bash
# Ver logs de los contenedores
docker-compose logs

# Ver logs de un contenedor específico
docker-compose logs App_votacion

# Reiniciar un contenedor específico
docker-compose restart App_votacion

# Acceder al contenedor PHP
docker exec -it App_votacion bash
```