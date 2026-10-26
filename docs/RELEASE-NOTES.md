Kitchen Safety 1.1.1 fixes the release ZIP permissions that could leave the installer and portal unstyled on cPanel hosting.

- Explicit 0644 permissions for packaged files and 0755 for directories; public CSS, JavaScript, images and fonts are readable after extraction.
- Packaging regression tests verify Unix permissions, required assets, private-file exclusion and manifest hashes.
- Fresh installer layout checked at both the domain root and a nested installation URL, including actual computed styles and font loading.
- Clearer installer requirement labels and the current Kitchen Safety logo.
- No database changes. Existing configuration and records are retained.

Extract this release over the existing application folder in cPanel, replacing the application files and keeping config.php and the database. Delete install.php after setup. If adjusting permissions manually, public asset files should be 644 and directories 755; do not make private configuration or backups public. Once the admin panel is styled, later releases can use Admin > Updates.
