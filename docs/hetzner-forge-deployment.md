# Hetzner + Laravel Forge Deployment Guide

This guide walks through deploying this Laravel app to a Hetzner VPS managed by Laravel Forge.

Hetzner provides the VPS. Forge provisions and manages the server, Nginx, PHP, Composer, database, SSL, deploy scripts, queues, and scheduler.

## 1. Prepare the Repository

Before deploying, make sure the app is committed and pushed to GitHub, GitLab, or Bitbucket.

This repo currently has an important production blocker: `composer.json` uses local path repositories such as:

```json
"/Users/conalloreilly/Development/coda-packages/cms"
```

Those paths will not exist on the VPS, so `composer install` will fail in production unless those packages are made available to the server.

Before deploying, choose one of these approaches:

- Include the packages in this repository.
- Publish the packages to a private Composer repository.
- Install the packages from private Git/VCS repositories.
- Copy or provision the package source on the server at paths Composer can access.

Do this before relying on Forge deployments.

## 2. Create a Hetzner API Token

In Hetzner Cloud Console:

1. Open the Hetzner project for this app.
2. Go to `Security` -> `API Tokens`.
3. Create a new token.
4. Give it read/write access so Forge can create and manage servers.
5. Copy the token immediately.

Hetzner API docs: https://docs.hetzner.cloud/reference/cloud

## 3. Sign Up for Laravel Forge

In Laravel Forge:

1. Connect the source control provider that hosts this repo.
2. Add Hetzner as a server provider.
3. Paste the Hetzner API token.
4. Confirm Forge can access the Hetzner project.

Forge provider docs: https://forge.laravel.com/docs/server-providers

## 4. Create the Server

In Forge, create a new Hetzner server.

Recommended starting settings:

- Provider: `Hetzner`
- Type: web server
- OS: Forge default Ubuntu image
- PHP: `8.3` or newer compatible with Laravel 12
- Database: MySQL or MariaDB
- Region: closest to users, likely Germany or Finland
- Size: start with 2 vCPU / 4 GB RAM for a real app, or 1 vCPU / 2 GB RAM for light traffic

Forge will provision the base server stack automatically.

## 5. Create the Site

Once the server is ready:

1. Add a new site in Forge.
2. Use the production domain, for example `training.example.com`.
3. Set the web directory to `public`.
4. Connect the Git repository.
5. Choose the production branch, usually `main`.

## 6. Configure the Production Environment

In Forge, open the site environment editor and set production values.

Example:

```env
APP_NAME="ARS Athlete Training"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

TELESCOPE_ENABLED=false
TELESCOPE_RECORD_ALL=false
```

Generate a real production app key on the server:

```bash
php artisan key:generate --force
```

Do not reuse the key from `.env.example` in production.

## 7. Configure the Deploy Script

In Forge, use a deploy script like this, replacing the domain and branch as needed:

```bash
cd /home/forge/your-domain.com

git pull origin main

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

php artisan migrate --force

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
```

Forge deployment docs: https://forge.laravel.com/docs/sites/deployments

## 8. Configure DNS

Wherever DNS is managed, add records pointing to the Forge server IP.

For an apex domain:

```text
A     your-domain.com      SERVER_IPV4
A     www                  SERVER_IPV4
```

For a subdomain:

```text
A     training             SERVER_IPV4
```

After DNS resolves, issue a Let's Encrypt SSL certificate from Forge.

## 9. Configure Queue Worker and Scheduler

This app is configured to use database-backed queues in production:

```env
QUEUE_CONNECTION=database
```

In Forge:

1. Add a queue worker for `php artisan queue:work`.
2. Add a scheduler entry for `php artisan schedule:run`.

Restart workers after each deploy:

```bash
php artisan queue:restart
```

## 10. Production Checks

After the first deploy, SSH into the server or use Forge's command runner:

```bash
php artisan migrate:status
php artisan about
php artisan queue:restart
```

Then verify:

- The site loads over HTTPS.
- Login works.
- Vite-built assets load correctly.
- Uploads and storage links work.
- Database-backed sessions work.
- Telescope is disabled publicly.

## Main Risk

The biggest deployment risk in this repository is the local Composer package setup. Forge and Hetzner should be straightforward once the `coda/*` packages are available to Composer on the production server.
