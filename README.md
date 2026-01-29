# ERP Laravel Romania

Versiune: v0.0.1

Aplicatie ERP custom (fara dependinte composer) pentru firme din Romania.

## Cerinte

- PHP 8.0+ (compatibil shared hosting)
- MySQL 5.7+ sau MariaDB 10.3+

## Instalare (fara SSH)

1. Creeaza o baza de date si un user in hosting.
2. Importa schema SQL:
   - `database/schema.sql`
3. (Optional) Seed roluri:
   - `database/seed_roles.sql`
4. Copiaza `.env.example` ca `.env` si completeaza:
   - `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
5. Uploadeaza toate fisierele pe hosting.
6. Deschide in browser:
   - `/install` pentru configurare automata `.env`
   - `/setup` pentru a crea primul admin

Recomandat: seteaza document root catre folderul `public/`.

Daca document root ramane in radacina proiectului (ex: /erp), este necesar fisierul `.htaccess`
din radacina pentru a redirectiona toate cererile catre `index.php`.

## Structura branding

Logo-ul este salvat in:

- `storage/app/public/erp/logo.{ext}`
- Copie publicata in `public/storage/erp/logo.{ext}`
