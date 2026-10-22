# XAMPP setup (Composer is optional)

This project already contains its `vendor/` dependencies, so Composer is not
required for the first local run.

## 1. Copy the project

Copy the project folder to:

`C:\xampp\htdocs\IT-hel-deska-and-Ticketing-system`

Do not remove the `vendor/` folder.

## 2. Start XAMPP

Open XAMPP Control Panel and start:

- Apache
- MySQL

## 3. Create the database

Open `http://localhost/phpmyadmin`, create/import the database using:

`database/helpdesk_db.sql`

For an existing installation, also run:

`database/migrations/002_roles_and_tickets.sql`

`database/migrations/003_auth_security.sql`

The default local XAMPP connection is `root` with an empty password. For a
real deployment, create a dedicated database user and configure `.env` using
`.env.example` instead.

## 4. Create the first administrator

Open Command Prompt and run this from the project folder. Replace the values
with your own credentials; do not commit them:

```bat
set "ADMIN_EMAIL=admin@example.com"
set "ADMIN_PASSWORD=UseYourOwn12CharPassword1"
set "ADMIN_ID_NUMBER=ADMIN-0001"
C:\xampp\php\php.exe bin\create_admin.php
```

## 5. Open the application

Open:

`http://localhost/IT-hel-deska-and-Ticketing-system/`

All roles require email OTP. Configure the `MAIL_*` values in a local `.env`
file before testing login. The `.env` file is ignored by Git and must never be
committed.
