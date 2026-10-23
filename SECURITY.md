# Security deployment checklist

Run this application behind HTTPS with the project root outside the public
document root. Publish only the web entry points and `assets/`; never expose
`.env`, `config/`, `database/`, `vendor/`, or `bin/` directly.

Before production:

- Copy `.env.example` to `.env` and set `APP_ENV=production`.
- Use a dedicated database user with a strong password; never use MySQL `root`.
- Run `database/helpdesk_db.sql`, then create the first admin with
  `ADMIN_EMAIL`, `ADMIN_PASSWORD`, and `ADMIN_ID_NUMBER` environment variables:
  `php bin/create_admin.php`.
- Configure SMTP credentials and HTTPS. All roles require email OTP; protect
  admin and technician mailboxes with MFA as well.
- Run `composer audit --locked` in CI and keep `composer.lock` committed.
- Keep `display_errors=0` in production and send errors to protected logs.
- Back up the database, test restore procedures, and monitor authentication
  failures, OTP sends, admin actions, and AI/API usage.
