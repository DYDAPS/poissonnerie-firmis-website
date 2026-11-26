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
## Git commit policy

- Write every commit in English as one line: a valid UTF-8 Gitmoji, one space, and a concise title. Do not add a body, cherry-pick note, source commit reference, validation description, replacement character, or damaged encoding.
- Keep one component update or one complete component-and-locale translation pack per commit. Include all files for the pack; do not split generated translation files or combine unrelated work.
- Use the exact commercial spelling **CREA...OSE** where applicable. Keep technical repository names, paths, identifiers and URLs unchanged.
- Do not use `git cherry-pick -x` when it adds a message trailer. Record source-to-promoted commit mappings in the maintenance Skill's internal registry.
- Install the `$dydaps-wordpress-maintenance` Git hooks and check staged files, message encoding, Gitmoji, single-line format, component/locale granularity and parasite files before commits and pushes. Check messages before publication; do not rewrite published history in future campaigns.
