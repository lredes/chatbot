![Laravel](https://raw.githubusercontent.com/lredes/chatbot/refs/heads/main/public/Laravel_Framework-copy-1030x515.webp)

Proyecto basado en PHP/Laravel. 


## Requisitos

PHP 7.3 o superior

Laravel 8.0 o superior

## Instalación

Configurar el entorno de desarrollo de aplicaciones

1. Descargue/clone este repositorio en una carpeta en su computadora

2. Luego ejecute el comando:

```bash
composer install
```

Esto instalara las dependencias necesarias para el proyecto.

3. crear .env con las credenciales correspondientes cp .env.example .env (por consola) poner las credenciales de tu BD.

```bash
DB_HOST=localhost DB_DATABASE=tu_base_de_datos DB_USERNAME=root DB_PASSWORD=
```

4. Se genera el key para el desarrollo local:

```bash
php artisan key:generate
```

5. Ejecutamos y levantamos el proyecto en local.

```bash
php artisan serve
```

## Documentacion de llamadas y respuestas

La documentacion de request y response lo tenemos en un blog de postman que usamos en el ambiente de desarrollo para los demas proveedores.

Se puede acceder al siguiente link -->  [Documenter Postman](https://documenter.getpostman.com/view/34748304/2sA3JJ8hfv) 

## Contribuyendo

Esta documentacion es inicialmente para ayudar a levantar de forma local, cualquier duda o consulta contactar con David Ramirez - +595982728095 - blacklabpy@gmail.com

## License

[MIT](https://choosealicense.com/licenses/mit/)
