# 1.1.1 - 26 October 2026

Fix cPanel extraction permissions: built release ZIPs now carry explicit 0644 file modes and 0755 directory modes, so public CSS, scripts, images and fonts can be served after extraction. Add a packaging regression test to validation and release workflows. Verify the fresh installer layout at root and nested URLs. Installer requirements use setup-specific status labels and the current logo. No database changes.

# 1.1.0 - 26 October 2026

Login-free kitchen frontend, separate protected admin panel, Recipes-style tablet design, self-reported recorder attribution, date history navigation, staged ZIP/GitHub updates with private backups, PWA installation and local drafts, new logo/icons and open-source release tooling. Existing records are preserved by schema migration 2.

# 1.0.0 - 22 October 2026

Initial installable release: separate multi-kitchen PHP/MySQL portal with cPanel installer, individual roles, equipment/timetable revisions, 11 configurable temperature controls, routine checklists, timed cooling, corrective-action history, manager resolution and daily reviews, CSV/print reports, account management and audit trail. Starting UK guidance and deployment assumptions are documented. No production data or credentials are bundled.
