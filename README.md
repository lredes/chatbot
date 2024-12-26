

Levantar proyecto : 
composer install

crear .env con las credenciales correspondientes
cp .env.example .env ( por consola )
poner las credenciales de tu BD

DB_HOST=localhost
DB_DATABASE=tu_base_de_datos
DB_USERNAME=root
DB_PASSWORD=


php artisan key:generate

php artisan serve


http://localhost:8000/api/login?cedula=4218795&celular=+595986445706