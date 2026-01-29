# ERP Laravel Romania

Versiune: v0.0.1

## Cerinte

- PHP 8.2+
- Laravel 12
- Baza de date configurata in .env

## Instalare

1. `composer install`
2. `cp .env.example .env`
3. `php artisan key:generate`
4. `php artisan migrate`
5. `php artisan db:seed`
6. `php artisan storage:link`

Pentru dezvoltare front-end (optional):

- `npm install`
- `npm run build`
