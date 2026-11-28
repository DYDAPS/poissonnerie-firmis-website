# Project maintenance guidance

Use `$dydaps-wordpress-maintenance` for shared discovery, version detection, compatibility, validation and release procedures.

## Project structure

- The `hello-elementor-child` theme extends `hello-elementor`; preserve child-theme code and compare parent-theme changes before replacement.
- `dydaps-api-protection` is maintained by DYDAPS; preserve its project-specific behavior.

## Durable constraints

- PHP 8.4 is the common DYDAPS target. Select the current stable 8.4 patch dynamically for local validation; never change hosting PHP settings automatically.
- WordPress files are separate from database content and plugin settings. Do not infer active status from files or alter production data during maintenance.

## Deployment

- The README documents a `main` to OVH production `www` deployment. Verify the current webhook and deployment configuration before release; a push may deploy production.

## Git conventions

Use `$dydaps-wordpress-maintenance` for commit creation and promotion. Every commit is UTF-8 and exactly one English line: one valid Gitmoji, one space, and a concise title. Do not add a body, description, source reference, cherry-pick note, or trailer.

Create one code-update commit per component. Independent translations use one separate commit per component per campaign, including every language, text domain, file, and PO/MO/JSON/`.l10n.php` format. Never split translations by language, file, or format; combine components; or create empty translation commits. Use `🌐 Update WordPress translations`, `🌐 Update [Plugin name] translations`, or `🌐 Update [Theme name] translations`, without a language name. Translations inseparable from an official component distribution may remain in its update commit.

Install and run the Skill's Git policy hooks before commits and pushes. `README.md` and `AGENTS.md` may be versioned on both `main` and `develop`; root `.htaccess` must deny public HTTP access to them. History rewrites and force pushes require explicit authorization for the specific task.

## OVH access and deployment safeguards

- Current access evidence: SSH, SFTP, FTP/FTPS, and full OVH filesystem access were not confirmed in the current campaign. Confirm the available protocol and OVH Git project state before a production action; do not infer it from a push event.
- Production deployment source is `main` only. Verify the OVH repository association, source branch, deployment directory, site status, webhook and completed job before release. Never configure or deploy `develop` to production. A successful Git push or webhook response does not prove deployment completion.
- Inventory production filesystem changes before deployment. Protect `wp-config.php`, `.htaccess`, uploads, media, backups, caches, generated plugin data and unverified files. Quarantine only exact approved collision paths after remote SHA-256 recheck and a verified backup; retain a restore manifest.
