---
name: ddev-development
description: "Use this skill when a .ddev folder is present in the project. Trigger when working on local development environments, setting up or configuring DDEV, troubleshooting DDEV issues, or optimizing DDEV performance. Covers: DDEV configuration files, common commands (ddev start, ddev stop, ddev exec), environment variable management, database access with DDEV, and best practices for local development with DDEV. Do not use for non-DDEV related tasks or production environment issues."
license: MIT
metadata:
  author: Lee Caine
---

# DDEV Development

## Basic Usage
DDEV is a local development environment that uses Docker to create isolated environments for your projects. It allows you to easily set up and manage local development environments for various web applications.
Use DDEV when working on local development environments, setting up or configuring DDEV, troubleshooting DDEV issues, or optimizing DDEV performance. Do not use for non-DDEV related tasks or production environment issues.

If a .ddev folder is present any artisan command you need to execute should be prepended with ddev rather than php.

## Common Commands
- `ddev start`: Start the DDEV environment for the current project.
- `ddev stop`: Stop the DDEV environment for the current project.
- `ddev exec <command>`: Execute a command within the DDEV environment.
- `ddev ssh`: Access the DDEV environment via SSH.
- `ddev logs`: View the logs for the DDEV environment.
- `ddev config`: Configure the DDEV environment for the current project.
- `ddev import-db`: Import a database into the DDEV environment.
- `ddev export-db`: Export a database from the DDEV environment.

## Artisan Commands
- `ddev artisan <command>`: Execute an Artisan command within the DDEV environment.
