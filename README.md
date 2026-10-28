# Funeral Service Management (FPRS)

Quick start:

- Import the database: open `sql/setup.sql` in MySQL and run it to create tables.
- Update database credentials in `config/db.php`.
- Ensure `uploads/` is writable by the web server for ID uploads.
- Create an admin user manually in the `users` table or update the seeded row and set a secure password hash.
 - Or run the included seed script to create default users (admin, director, client) with a default password.

Important files:
- `index.php` – landing page
- `register.php` / `login.php` / `logout.php` – authentication flows
- `client/dashboard.php` – client portal and booking UI
- `director/packages.php` + `director/package_action.php` – package CRUD for directors
- `admin/dashboard.php` + `admin/verify_user.php` – admin verification and monitoring
- `api/messages.php` – simple chat API (GET/POST)

Security notes:
- Uses `password_hash` and PDO prepared statements across the app.
- Uploaded IDs are validated by MIME type (PNG/JPG/PDF) and saved to `uploads/`.

This is a starter implementation focusing on core workflows. You can extend features like real-time messaging, richer validation, and role-based UI improvements.

Default seeded users (run `php scripts/seed_users.php`):

- `admin@example.com` — role: admin
- `director@example.com` — role: director
- `client@example.com` — role: client

All seeded users use the default password defined in `config/db.php` as `DEFAULT_USER_PASSWORD`. Change passwords after first login.
