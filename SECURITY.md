# Security and operational scope

- Passwords use PHP password_hash/password_verify; no default accounts or passwords ship with the app.
- PDO prepared statements protect user-supplied query values. HTML output is escaped. CSV neutralises formula prefixes.
- State-changing requests require a CSRF token. Login uses server-side, HMAC-keyed email/IP throttling after ten failed attempts per fifteen-minute window.
- Sessions use strict cookie mode, HttpOnly and SameSite=Lax; Secure is set when PHP sees HTTPS. Inactivity expiry is one hour. Auth-version changes revoke sessions on the next request.
- Kitchen assignments and roles are checked on reads and writes. Staff cannot manage controls, users, sites, export reports or resolve exceptions.
- Original readings are never updated or deleted through the application. Corrections and events are append-only. Manager resolutions do not change the original result. Schedule revisions retain earlier schedules. Limit and actor snapshots preserve the original context.
- This is not a cryptographic evidence ledger. An administrator of the hosting account or database can modify the database. Maintain independent backups and restrict host access.
- The installer uses an exclusive lock, refuses existing ks_ tables, writes the private configuration and locks after config.php exists. Delete install.php after setup. Use a new database; never install over live data.
- Apache access rules deny private directories, configuration, dotfiles and backup/log file extensions. PHP must be enabled; do not serve the source as text. Configure equivalent restrictions for Nginx. Check access rules on your actual host.
- No email sending, external analytics, sensor integration, background notifications, native mobile binary or offline submission synchronisation is included. Administrator release ZIP uploads are supported. Dashboard warnings are visible on refresh. Reports are manager-only but a staff member with site access can see that site's individual records.
- Keep PHP, MariaDB/MySQL, Apache and the hosting account patched. Use TLS and strong individual passwords. Do not store SQL backups in the web root.

Before production acceptance, verify HTTPS, installer removal, sign-in/out, every role/site assignment, a passing and failing reading, cooling completion, manager resolution, CSV/print output, and backup/restore on your hosting account. Have a competent manager validate the kitchen's actual HACCP controls and train users.

Version 1.1.0 intentionally opens the frontend without staff login. Active-kitchen histories and submissions are available to anyone who can reach it. Names entered there are self-reported, stored through a disabled system recorder identity, and never treated as authenticated manager identity. Public actions are limited to checks and append-only follow-up events, protected by CSRF and submission throttling. Manager resolution, administration, exports and updates require backend authentication. A PWA draft is browser-local and is never marked submitted until the server confirms saving.

Updater release ZIPs execute code chosen by an administrator; only install trusted releases. Packages are path/checksum/version validated, protected configuration/storage is excluded, database and affected files are backed up, and file rollback is attempted on failure. Database DDL can require manual recovery, so maintenance is kept enabled after a migration failure. GitHub downloads require matching official repository asset URLs and SHA-256 digests. See docs/UPDATES.md.
