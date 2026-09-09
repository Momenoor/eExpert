# eExpert

A legal case management and enterprise operations platform built with **Laravel 13** and **Filament PHP v5**. The application provides end-to-end management for legal matters, court tracking, party allocations, human resources (payroll, employee loans, leave requests), incentive calculation engines, bulk email campaigns, calendar synchronization via Microsoft Graph, and role-based access control.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [System Requirements](#system-requirements)
- [Installation & Setup](#installation--setup)
- [Running the Application](#running-the-application)
- [Entry Points & Routing](#entry-points--routing)
- [Available Scripts](#available-scripts)
- [Environment Variables](#environment-variables)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [License](#license)

---

## Overview

**JPA Emirates** is tailored for legal firms and legal operations within the UAE. It streamlines case lifecycles, automates commission and incentive calculations for legal assistants and consultants, handles multi-currency and AED-centric accounting/payroll runs with journal voucher exports, and provides bilingual support (Arabic default, English fallback).

---

## Key Features

- **Legal Matter Management**: Comprehensive tracking of legal matters, courts, claim types, statuses, document attachments, and dynamic matter metadata.
- **Party & Allocation Tracking**: Management of plaintiffs, defendants, assignees, and party leaves.
- **Workflow & Matter Requests**: Request approval lifecycle (e.g., received date disputes, date changes) with signed email links and in-app notifications.
- **HR & Payroll Engine**:
  - Employee profiles and salary component definitions.
  - Leave entitlement calculation and leave request workflows.
  - Employee loan management with monthly installment tracking.
  - End of Service Gratuity (EOSG) accruals.
  - Payroll runs with automated payslip generation and printable journal vouchers.
- **Incentive Calculation Engine**: Multi-tiered incentive calculation based on matter types, custom extra rules, and assistant allocations with printable statements.
- **Bulk Email Campaigns**: Targeted campaigns with tracking, recipient management, preview generation, and unsubscribe workflows.
- **Calendar & Third-Party Integrations**: FullCalendar view, Microsoft Graph calendar sync and mailer integration, plus WhatsApp notifications.
- **Security & Access Control**: Granular permissions and role-based access control via Filament Shield, user impersonation, and detailed activity auditing.

---

## Tech Stack

| Component | Technology / Library | Version |
|---|---|---|
| **Language** | PHP | `^8.5` |
| **Backend Framework** | Laravel | `^13.0` |
| **Admin UI Framework** | Filament PHP | `^5.0` |
| **Frontend / Reactive** | Livewire & Alpine.js | `^4.0` |
| **CSS Framework** | Tailwind CSS | `^4.0` |
| **Asset Bundler** | Vite | `^7.0` |
| **Package Managers** | Composer & npm | Latest |
| **Testing Framework** | PHPUnit | `^12.0` |
| **Code Formatter** | Laravel Pint | `^1.24` |
| **Static Analysis** | Larastan / PHPStan | `^3.10` |

---

## System Requirements

- **PHP**: `>= 8.5` with extensions:
  - `ext-pdo` / `ext-pdo_mysql`
  - `ext-zip`
  - `ext-mbstring`
  - `ext-openssl`
  - `ext-curl`
  - `ext-bcmath`
  - `ext-intl`
  - `ext-fileinfo`
- **Composer**: `>= 2.2`
- **Node.js**: `>= 20.x` and **npm**: `>= 10.x`
- **Database**: MySQL `>= 8.0`, MariaDB `>= 10.4`, or SQLite (local/testing)
- **Web Server**: Nginx, Apache, Laravel Sail, or Laragon / Valet

---

## Installation & Setup

### 1. Clone the Repository

```bash
git clone <repository-url> jpa-emirates
cd jpa-emirates
```

### 2. Automated Setup (Quick Start)

You can run Composer's automated setup script which installs dependencies, creates the `.env` file, generates the application key, runs migrations, and builds frontend assets:

```bash
composer run setup
```

---

### 3. Manual Step-by-Step Installation

If you prefer to configure each step manually:

#### A. Install PHP & JavaScript Dependencies

```bash
composer install
npm install
```

#### B. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` to configure your database connection, mail settings, and application URLs.

#### C. Run Database Migrations & Seeders

```bash
php artisan migrate --seed
```

*Note: You can also use the interactive web installer by accessing `/install` in your browser.*

#### D. Create Storage Symlink

```bash
php artisan storage:link
```

#### E. Build Frontend Assets

```bash
npm run build
```

---

## Running the Application

### Option 1: All-in-One Development Command

Run the web server, queue listener, Laravel Pail log stream, and Vite bundler concurrently:

```bash
composer run dev
```

### Option 2: Running Services Individually

Start each component in a separate terminal:

```bash
# Terminal 1: Laravel Web Server
php artisan serve

# Terminal 2: Vite Dev Server (Hot Module Reloading)
npm run dev

# Terminal 3: Queue Worker
php artisan queue:work

# Terminal 4 (Optional): Real-time Log Tailing
php artisan pail
```

---

## Entry Points & Routing

### Web & Admin Interfaces

| Route / URI | Description | Access |
|---|---|---|
| `/` | Application root (redirects to admin/login) | Public |
| `/login` | Authentication entry point (redirects to Filament login) | Public |
| `/admin` | Filament Admin Panel dashboard & resources | Authenticated / Roles |
| `/install` | Web setup & installation wizard | Installer Guard |
| `/admin/system-down` | Custom maintenance / system-down page | Public |

### Functional Endpoints

| Route / URI | Description | Access |
|---|---|---|
| `admin/matter/{matter}/received-date/accept/{matterRequest}` | Accept matter assigned date | Signed URL |
| `admin/matter/{matter}/received-date/dispute/{matterRequest}` | Dispute matter assigned date | Signed URL |
| `bulk-mail/preview/{campaign}/{recipient}` | Preview bulk email template for recipient | Authenticated |
| `mail/unsubscribe/{token}` | Bulk email unsubscribe handler | Public |
| `attachments/{attachment}/download` | Secure matter attachment download | Authenticated |
| `incentive/calculations/{calculation}/print` | Print incentive calculation sheet | Authenticated |
| `incentive/calculations/{calculation}/print/{party}` | Print assistant-specific incentive statement | Authenticated |
| `payroll/runs/{run}/journal-voucher/print` | Print payroll journal voucher (PDF) | Authenticated |

### Scheduled Console Commands

- `mail:send-bulk-campaigns`: Scheduled daily at `08:00` (timezone `Asia/Dubai`) via `routes/console.php`.

---

## Available Scripts

### Composer Scripts

| Command | Description |
|---|---|
| `composer run setup` | Full bootstrap: installs packages, creates `.env`, generates app key, migrates DB, and builds assets |
| `composer run dev` | Runs `serve`, `queue:listen`, `pail`, and `vite` concurrently using `concurrently` |
| `composer run test` | Clears configuration cache and runs the test suite |
| `composer run post-autoload-dump` | Auto-discovers packages and runs `filament:upgrade` |

### NPM Scripts

| Command | Description |
|---|---|
| `npm run dev` | Starts the Vite development server with Tailwind CSS v4 support |
| `npm run build` | Compiles and minifies frontend assets for production |

### Code Quality & Analysis

```bash
# Format PHP code with Laravel Pint
vendor/bin/pint --dirty --format agent

# Run static analysis with PHPStan / Larastan
vendor/bin/phpstan analyse
```

---

## Environment Variables

Key configuration variables defined in `.env.example`:

### Application & Localization

| Variable | Description | Default |
|---|---|---|
| `APP_NAME` | Name of the application | `JPA Emirates` |
| `APP_ENV` | Environment (`local`, `production`, `testing`) | `local` |
| `APP_KEY` | 32-character encryption key | Generated |
| `APP_DEBUG` | Enable/disable debug mode | `true` |
| `APP_URL` | Base application URL | `http://localhost` |
| `APP_LOCALE` | Default locale | `ar` |
| `APP_FALLBACK_LOCALE` | Fallback locale | `en` |
| `APP_CURRENCY` | Default currency code | `AED` |
| `APP_TIMEZONE` | Default timezone | `Asia/Muscat` |

### Database & Storage

| Variable | Description | Default |
|---|---|---|
| `DB_CONNECTION` | Database driver (`mysql`, `sqlite`, etc.) | `mysql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `3306` |
| `DB_DATABASE` | Database name | - |
| `DB_USERNAME` | Database user | - |
| `DB_PASSWORD` | Database password | - |
| `FILESYSTEM_DISK` | Storage driver (`local`, `public`, `s3`) | `local` |
| `QUEUE_CONNECTION` | Queue driver (`database`, `redis`, `sync`) | `database` |

### Third-Party Integrations

| Variable | Description |
|---|---|
| `MICROSOFT_CLIENT_ID` / `MICROSOFT_CLIENT_SECRET` | Microsoft Azure AD App Credentials |
| `MICROSOFT_TENANT_ID` / `MICROSOFT_REDIRECT_URI` | Azure Tenant ID & OAuth Redirect URI |
| `MICROSOFT_CALENDAR_EMAIL` | Outlook calendar target account email |
| `MICROSOFT_GRAPH_*` | Microsoft Graph API mailer credentials |
| `WHATSAPP_TOKEN` / `WHATSAPP_FROM` / `WHATSAPP_PHONE_ID` | WhatsApp Business API credentials for notification dispatch |

> **TODO**: Configure external service credentials (Microsoft Graph, WhatsApp API, AWS S3) in staging/production environments.

---

## Testing

The application uses **PHPUnit 12** for feature and unit tests.

### Running Tests

```bash
# Run all tests
php artisan test --compact

# Run tests via Composer script
composer test

# Run a specific test suite or file
php artisan test --compact tests/Feature/IncentiveCalculatorServiceTest.php

# Run a filtered test method
php artisan test --compact --filter=test_calculates_incentive_correctly
```

---

## Project Structure

```
jpa-emirates/
├── app/
│   ├── Console/Commands/        # Custom Artisan commands (bulk mail, sync, etc.)
│   ├── Enums/                   # Enums for statuses, types, and priorities
│   ├── Filament/                # Filament v5 admin panel structure
│   │   ├── Actions/             # Reusable custom actions
│   │   ├── Pages/               # Custom Filament pages & dashboard
│   │   ├── Resources/           # Domain resources (Matters, Payroll, Leaves, etc.)
│   │   │   └── [Resource]/
│   │   │       ├── Schemas/     # Separated form schema definitions
│   │   │       ├── Tables/      # Separated table definitions
│   │   │       └── Pages/       # List, Create, Edit, View pages
│   │   └── Widgets/             # Dashboard and resource widgets
│   ├── Http/
│   │   ├── Controllers/         # Document printing, downloads & signed links
│   │   └── Middleware/          # Maintenance & installation guards
│   ├── Models/                  # Eloquent models (Matter, PayrollRun, Employee, etc.)
│   ├── Policies/                # Authorization policies (Filament Shield)
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── Filament/AdminPanelProvider.php
│   └── Services/                # Business logic services (Incentives, Payroll, WhatsApp)
├── config/                      # Application configuration files
├── database/
│   ├── factories/               # Model factories for testing and seeding
│   ├── migrations/              # Database migration files
│   └── seeders/                 # Database seeders (Shield permissions, default data)
├── lang/                        # Localization files (`ar.json`, `en.json`, vendor translations)
├── public/                      # Web root / compiled public assets
├── resources/
│   ├── css/                     # Tailwind CSS v4 styling
│   ├── js/                      # Frontend JavaScript
│   └── views/                   # Blade templates & printable documents
├── routes/
│   ├── console.php              # Scheduled commands & console routes
│   └── web.php                  # Web and signed link routes
└── tests/
    ├── Feature/                 # Feature tests for resources, services, and widgets
    └── Unit/                    # Unit tests
```

---

## License

This project is licensed under the [MIT License](LICENSE) (or organizational proprietary terms where applicable).

> **TODO**: Confirm legal distribution terms and licensing specifics with project stakeholders.
