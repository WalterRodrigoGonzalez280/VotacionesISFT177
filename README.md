# APHD 2025 - Entorno de Desarrollo con Docker

Este proyecto utiliza **Docker** para configurar un entorno completo de desarrollo PHP con Apache, MySQL y phpMyAdmin.

---
## Paso de instalación y ejecución del docker PHP-APACHE 

    Clonar el repositorio si contas con la key SSH:  
    
        git clone git@github.com:GonzaloOSierra/APHD-2025.git
        cd APHD-2025
        git checkout develop


## Requisitos Previos

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- Git

---

## Estructura del Proyecto

```
    APHD-2025/
    ├── Dockerfiles/
    │ └── php-apache/
    │        └── Dockerfile
    ├── dump/ 
    ├── www/ 
    │   ├── assets/             # Archivos estáticos (CSS, JS, imágenes)
    │   ├── controllers/        # Controladores PHP
    │   ├── models/             # Modelos (lógica de datos)
    │   ├── views/              # Vistas (HTML, templates)
    │   └── index.php           # Punto de entrada princip
    ├── .env 
    ├── docker-compose.yml
    ├── .gitignore
    └── README.md

``` 

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

        -mysql: base de datos MySQL 5.7

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
    http://localhost:5000/ ----> para php-apache
    
    http://localhost:5005/ ----> para la imagen de phpmyadmin  

    Credenciales de acceso a MySQL / phpMyAdmin:

        Usuario: votacion_user

        Contraseña: develop2025

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

    Aplicación PHP + Apache:
        http://192.168.1.40:5000

    phpMyAdmin:
        http://192.168.1.40:5005

    3 Requisitos
    -Ambas computadoras deben estar conectadas a la misma red local.
    -Asegurate de que Docker esté corriendo en la computadora host (docker ps).
    -Verificá que el firewall del host no bloquee los puertos 5000 y 5005.