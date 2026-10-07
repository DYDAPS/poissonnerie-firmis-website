# Poissonnerie Firmis Website

## Overview

This repository contains the tracked WordPress application and site-specific code for Poissonnerie Firmis. The WordPress database remains separate from the filesystem deployment and holds page content, plugin settings, activation state, and other site data.

## Tech Stack

- WordPress 7.1.2 (the version in the tracked core source)
- PHP; use a local runtime compatible with the production host and installed extensions
- Poissonnerie Firmis Theme (`hello-elementor-child`), based on Hello Elementor
- Site features and integrations supplied by the plugins listed below

## Repository Structure

- WordPress core files live at the repository root.
- `wp-content/themes/` contains the parent and site child theme.
- `wp-content/plugins/` contains the plugin source shipped by this project.
- `wp-content/mu-plugins/` contains must-use code when present.
- `wp-content/uploads/` is persistent user media and is intentionally excluded from Git.
- `wp-config.php` is the tracked shared bootstrap; the local environment file is not tracked.

## Theme

The site-specific theme shipped here is **Poissonnerie Firmis Theme (`hello-elementor-child`), based on Hello Elementor**. Its parent theme is included under `wp-content/themes/`. WordPress stores the selected theme in the database; theme activation is therefore not inferred from the directory name alone.

## Important Plugins

This project includes source for the following site features and integrations:

- Elementor and Elementor Pro; Essential Addons for Elementor; DYDAPS API Protection; Yoast SEO; Complianz; Site Mailer; PDF Embedder and Templately.

Plugin files are tracked as application code. Plugin activation, credentials, and settings are WordPress database/configuration state and are not controlled by this README.

## Configuration

- `wp-config.php` is versioned and provides shared WordPress bootstrap configuration. It contains no environment-specific database password, salts, or production tokens.
- `wp-config-local.php` is private, ignored by Git, and supplied separately for each environment.
- `wp-config-local.example.php` is the tracked safe template. Copy it when preparing a new environment and fill values only in the ignored private file.

Do not commit private configuration or alter production secrets as part of source deployment.

## Persistent and Generated Data

- `wp-content/uploads/` contains original uploaded media and is persistent user-generated data. Preserve it across deployments and back it up independently.
- Elementor-generated styles, typically below `wp-content/uploads/elementor/css/`, are runtime output. Elementor can regenerate them; do not treat them as theme source.
- WebP/AVIF variants are generated from original uploads by the configured image plugin. Regenerate them through that plugin if needed.
- WordPress/plugin caches, logs, temporary upgrade files, and backup-plugin archives are runtime or backup data, not source. Keep them out of the tracked deployment. The database is persistent and is not deployed by Git.

## GitHub and OVH Deployment

- GitHub repository: [`DYDAPS/poissonnerie-firmis-website`](https://github.com/DYDAPS/poissonnerie-firmis-website)
- `main` is the production branch and GitHub is the source of truth for tracked source files.
- The configured OVH Git deployment targets the production `www` directory. A push to `main` is expected to trigger deployment through the GitHub/OVH webhook.
- The deployment must preserve ignored environment configuration and persistent uploads. Never place `.git` or a private config file into a manual FTP transfer.

## Automatic Deployment and Checklist

After a reviewed push, confirm the production revision (or deployed README hash where server-side Git metadata is unavailable), then check:

1. Homepage and a representative database-backed page return successfully.
2. CSS, JavaScript, and an original uploaded image load.
3. Elementor renders and its generated styles are available or regenerated.
4. The intended admin login route remains reachable when its location is known.
5. `wp-config-local.php`, uploads, and other required persistent data remain present.
6. PHP/web-server logs show no new fatal error.

## Local Development and Maintenance

No repository-wide build or test command is defined. Develop against an isolated WordPress environment with a compatible PHP/MySQL stack and non-production data. Keep custom theme/plugin changes in their existing project directories; do not edit core or dependency output directly. Follow a plugin?s own documented development workflow when changing it.

## Update Policy

Review and test WordPress core, theme, and plugin changes in the project checkout, commit only intended tracked source, and deploy through the `main` workflow. Database migrations and plugin settings require a separate, controlled plan; a filesystem push does not deploy them.

## Rollback

Before a risky production deployment, retain a verified copy of the current `www` tree (for example, `www-backup`) and a separate database backup. If verification fails, restore the prior tree and investigate before retrying. Do not remove the rollback copy until the new release is accepted.

## Security

Never commit passwords, database credentials, salts, API keys, webhook secrets, private configuration, database exports, user/customer data, uploads, or backups. Keep `wp-config-local.php` ignored and use the example file only as a placeholder template.

## Project-Specific Notes

The site-specific child theme and plugin source are maintained with the WordPress application.
