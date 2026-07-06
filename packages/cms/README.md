# Coda CMS

A generic CMS framework for Laravel + Livewire applications. Provides user management, authentication, configurable admin panel with sidebar navigation, CRUD list/tree views, form system, and tagging.

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL (e.g. DBngin / Herd)
- Laravel Herd (for local `.test` domains)

## Installation

### 1. Create a new Laravel app

```bash
laravel new cms-test
cd cms-test
```

### 2. Add the CMS package

Add the repository and require the package in `composer.json`:

```json
{
    "repositories": {
        "cms": {
            "type": "path",
            "url": "../athlete-training/packages/cms"
        }
    }
}
```

Then require the package:

```bash
composer require coda/cms
```

This automatically installs all dependencies (Livewire, Fortify, Flux Pro, Spatie packages, etc.).

> **Note:** The CMS requires `livewire/flux-pro` which needs a valid license. Ensure the Flux Pro Composer repository is configured. See [Flux Pro docs](https://flux.livewire.com) for setup.

### 3. Run the CMS installer

```bash
php artisan cms:install --migrate
```

This automatically:
- Configures `.env` for MySQL (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_USERNAME=root`) with the database named after the project folder (e.g. `cms_test`)
- Creates the database (supports DBngin, Homebrew MySQL, system MySQL)
- Replaces Laravel's default users migration with CMS schema
- Publishes `config/cms.php` and CMS migrations
- Scaffolds `CmsServiceProvider`, `routes/web.php`, `bootstrap/app.php`, `DatabaseSeeder`, `app.js`, and `app.css`
- Registers the provider in `bootstrap/providers.php`
- Runs migrations (with `--migrate` flag)

### 4. Seed the admin user

```bash
php artisan db:seed
```

This creates a user with `dev@dev.dev` / `123456789`.

### 5. Install frontend and build

```bash
npm install && npm run build
```

### 6. Visit your site

Open `https://cms-test.test/login` and sign in with:

- **Email:** `dev@dev.dev`
- **Password:** `123456789`

You'll be redirected to the dashboard. Use the sidebar to navigate to the Users list.

---

## Configuration

Update `config/cms.php` after install:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `name` | `string\|null` | `null` | App name in sidebar/login. Falls back to `config('app.name')` |
| `logo` | `string\|array\|null` | `null` | Logo asset path. String for single, `['light' => ..., 'dark' => ...]` for theme variants |
| `home` | `string` | `/admin/dashboard` | Redirect after login |
| `site_title` | `string\|null` | `null` | Page title prefix. Falls back to `name` then `config('app.name')` |
| `title_format` | `string` | `:page_name // :site_title` | Page title format with `:site_title` and `:page_name` placeholders |
| `models.*` | `string` | CMS built-ins | Override only if you extend the models |
| `enums.*` | `string` | CMS built-ins | Override only if you define custom enums |

## Publish Tags

| Tag | Published by install? | Description |
|-----|:---:|-------------|
| `cms-config` | Yes | Configuration file |
| `cms-migrations` | Yes | Database migrations |
| `cms-views` | No | Layout and auth views (publish only to customise) |

## Built-in Modules

| Module | Route | Description |
|--------|-------|-------------|
| `DashboardModule` | `/admin/dashboard` | Welcome/landing page |
| `UserModule` | `/admin/users` | User CRUD list |

Register modules in your `CmsServiceProvider` using the `Registry`.

## Creating Custom Modules

```php
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;

class ProductModule extends Module
{
    public function name(): string
    {
        return 'products';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('product-index')
                ->route('/products')
                ->title('Products')
                ->heading('Products')
                ->content(['product-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('product-list', ProductList::class)
                ->type(ComponentType::List),
        ];
    }
}
```

Your `ProductList` should extend `Coda\Cms\Livewire\AbstractModelList` and implement `getDataClass()`, `getBaseQuery()`, and `getTable()`.
