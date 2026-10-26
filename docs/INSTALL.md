# Installation

Use a separate folder/database from MustrHQ Recipes. Supported host: Apache 2.4 with .htaccess enabled, PHP 8.1+ with PDO MySQL and mbstring, MySQL 8.0+ or MariaDB 10.6+. HTTPS is required for tablet PWA installation and should be enabled before live use. ZIP updates additionally require `zip`; GitHub updates require `curl` and outbound HTTPS to GitHub.

1. In cPanel File Manager, upload the built release ZIP and extract it. Move the contents of its `mustrhq-kitchen-safety` folder into your chosen directory if needed. Enable Show Hidden Files and retain all .htaccess files.
2. Create a new database and database user. Add the user to that database with privileges. Note full cPanel-prefixed names and host/port.
3. Set the appropriate PHP version/extensions. Open `https://YOUR-DOMAIN/YOUR-FOLDER/install.php`.
4. Enter the database connection, first kitchen, IANA time zone (Europe/London for London), and an administrator password of at least 12 characters.
5. Delete install.php after success. The installer also refuses to run if config.php exists or the target database already contains ks_ tables.
6. Open `admin/`. Add equipment, check schedules and optional manager accounts with kitchen assignments. Validate controls and record the manager's control review.
7. Open the main address on the tablet. Staff record temperatures without login, entering their name or initials. The frontend can display all active kitchens. Archived kitchens and backend settings remain protected.

Typical permissions are folders 755, files 644 and config.php 640, depending on the host's PHP user. Do not use 777. Updates need PHP write access to the application files and private directory. The private folder is protected by Apache rules and is created/protected by the updater before use. With Nginx, deny direct access to private, inc, docs, tests, scripts, migrations, dotfiles and config.php explicitly.

Do not share config.php or store database dumps in a public directory. Back up the entire database plus config.php and code, and test a separate restore. CSV/PDF reports are evidence exports, not database backups.

A frontend name is self-reported. To use this as a premises-only recorder, apply your own network/web-server access controls. The application intentionally does not require a staff account or PIN on the frontend.

If all administrators forget their passwords, the CLI-only docs/reset-admin.php recovery script can set a new password with your hosting administrator's help. It accepts the password through standard input, logs the reset and invalidates old sessions; see its usage message. Never place passwords in command arguments or in chat.
