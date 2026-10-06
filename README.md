# Poissonnerie Firmis Website

Source repository for the WordPress website.

## Overview

This repository contains the tracked source files for the Poissonnerie Firmis Website. It does not by itself provide a complete production runtime or data set.

## Tech stack

WordPress and PHP; HTML, CSS, and JavaScript in the tracked site files.

## Project structure

```text
.
├── wp-admin/       # WordPress administration
├── wp-content/     # Themes, plugins, and site content
├── wp-includes/    # WordPress core libraries
├── index.php       # WordPress entry point
└── wp-config-sample.php
```

## Development

No project-specific development commands were detected at the repository root. Use a compatible PHP and CMS environment for local development.

## Git workflow

```sh
git status
git pull
git switch -c feature/short-description
# Make and review changes
git add <files>
git commit -m ":books: Describe the change"
git push -u origin feature/short-description
```

## Deployment

No deployment provider or automated deployment process is documented here. Deploy through the project’s approved process, using a compatible application environment and keeping environment configuration outside source control.

## Persistent and non-versioned data

The WordPress database, uploads, and environment-specific configuration are persistent site data and are not fully represented by this repository.

## Maintenance

Review `git status` and the diff before committing. Keep secrets, database exports, generated files, and backups out of Git. Update this document when the detected stack or project workflow changes.

## Ownership and license

No project-specific license or ownership terms are declared in this repository. Confirm applicable ownership and reuse rights with the project owner.

---

# Version française

# Poissonnerie Firmis Website

Dépôt source du site WordPress de la Poissonnerie Firmis.

## Présentation

Ce dépôt contient les fichiers sources versionnés du projet Poissonnerie Firmis Website. Il ne fournit pas à lui seul un environnement de production ni l’ensemble des données du site.

## Technologies

WordPress et PHP ; HTML, CSS et JavaScript sont présents dans les fichiers du site versionnés.

## Structure du projet

```text
.
├── wp-admin/       # Administration WordPress
├── wp-content/     # Thèmes, extensions et contenu du site
├── wp-includes/    # Bibliothèques du cœur WordPress
├── index.php       # Point d’entrée WordPress
└── wp-config-sample.php
```

## Développement

Aucune commande de développement propre au projet n’a été détectée à la racine du dépôt. Utiliser un environnement PHP et CMS compatible pour le développement local.

## Flux Git

```sh
git status
git pull
git switch -c feature/description-courte
# Modifier et relire les changements
git add <files>
git commit -m ":books: Describe the change"
git push -u origin feature/description-courte
```

## Déploiement

Aucun hébergeur ni processus de déploiement automatisé n’est documenté ici. Déployer selon le processus approuvé du projet, dans un environnement applicatif compatible et en conservant la configuration d’environnement hors du contrôle de version.

## Données persistantes et non versionnées

La base de données WordPress, les fichiers téléversés et la configuration propre à l’environnement sont des données persistantes qui ne sont pas entièrement représentées par ce dépôt.

## Maintenance

Relire `git status` et le diff avant chaque commit. Ne pas versionner les secrets, exports de base de données, fichiers générés ni sauvegardes. Mettre ce document à jour si la pile technique détectée ou le flux du projet évolue.

## Propriété et licence

Aucune licence ni condition de propriété propre au projet n’est déclarée dans ce dépôt. Confirmer les droits de propriété et de réutilisation applicables auprès du propriétaire du projet.
