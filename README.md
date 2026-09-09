# eExpert

A legal case management and enterprise operations platform built with **Laravel 13** and **Filament PHP v5**. The application provides end-to-end management for legal matters, court tracking, party allocations, human resources (payroll, employee loans, leave requests), incentive calculation engines, bulk email campaigns, internal live chat, calendar synchronization via Microsoft Graph, and role-based access control.

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

**eExpert** is tailored for legal firms and corporate legal operations within the UAE. It streamlines case lifecycles, automates commission and incentive calculations for legal assistants and consultants, handles multi-currency and AED-centric accounting/payroll runs with journal voucher exports, enables team collaboration with integrated live chat and user avatars, and provides full bilingual support (Arabic default, English fallback).

---

## Key Features

- **Legal Matter Management**: Comprehensive tracking of legal matters, courts, claim types, statuses, document attachments, and dynamic matter metadata.
- **Party & Allocation Tracking**: Management of plaintiffs, defendants, assignees, experts, and party leaves.
- **Real-Time Live Chat**:
  - Integrated in-app live messaging between team members (`ChatWidget` & full-page `/admin/chat`).
  - Unread message counters, instant message broadcasting (`ChatMessageSent`), and responsive chat UI.
- **User Avatars & Profile Customization**:
  - Custom avatar upload support via `FilamentUsersPlugin` and `CustomProfile`.
  - Consistent avatar display across the navigation bar, user management, and live chat conversations.
- **Workflow & Matter Requests**: Request approval lifecycle (e.g., received date disputes, date changes) with signed email links and in-app notifications.
- **HR & Payroll Engine**:
  - Employee profiles with custom salary component definitions.
  - Leave entitlement calculation and multi-stage leave request workflows.
  - Employee loan management with monthly installment tracking and payroll deduction.
  - End of Service Gratuity (EOSG) accruals.
  - Payroll runs with automated payslip generation and printable journal vouchers.
- **Incentive Calculation Engine**: Multi-tiered incentive calculation based on matter types, custom extra rules, and assistant allocations with printable statements.
- **Advanced Analytics & Reporting Suite**:
  - **Operational Reports**: Overdue Matters, Court Workload, Matter Quality, and Monthly Matters.
  - **Financial Reports**: Fee Collection Aging, VAT Summary, Type Profitability, and Deductions Reconciliation.
  - **Performance & Incentive Reports**: Assistant Performance, Assistant Matter Fees, Assistant Matters Count, My Matters, and My Incentives.
- **Bulk Email Campaigns**: Targeted campaigns with tracking, recipient management, preview generation, and unsubscribe workflows.
- **Calendar & Third-Party Integrations**: FullCalendar view, Microsoft Graph calendar sync and mailer integration, plus WhatsApp notifications.
- **Maintenance & Diagnostics Tools**:
  - **Access Control Maintenance**: Real-time audit and repair tool for Spatie/Shield permissions and roles.
  - **Fee Data Maintenance**: Sanity validation and correction of matter financial figures.
  - **Difficulty Adjustment**: Automated difficulty level normalization across legal matters.
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
git clone <repository-url> e-expert
cd e-expert
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

#### B. Configure Environment

Copy the example environment file and generate the application encryption key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database connection in `.env`:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eexpert
DB_USERNAME=root
DB_PASSWORD=
```

#### C. Run Database Migrations & Seeders

```bash
php artisan migrate --seed
```

To seed comprehensive production baseline roles, permissions, and test data:

```bash
php artisan db:seed --class=ProductionDatabaseSeeder
```

#### D. Link Storage & Build Frontend Assets

```bash
php artisan storage:link
npm run build
```

---

## Running the Application

### Development (Single Command)

To run the Laravel backend server, Vite asset dev server, queue worker, and logs simultaneously:

```bash
composer run dev
```

### Individual Development Runners

- **Laravel Backend Server:**
  ```bash
  php artisan serve
  ```
- **Vite Asset Watcher:**
  ```bash
  npm run dev
  ```
- **Queue Worker:**
  ```bash
  php artisan queue:work
  ```
- **Livewire Reverb / WebSocket Server (for real-time events):**
  ```bash
  php artisan reverb:start
  ```

---

## Entry Points & Routing

- **Admin & Operations Panel**: `http://localhost:8000/admin`
  - **Live Chat**: `http://localhost:8000/admin/chat`
  - **Matters Management**: `http://localhost:8000/admin/matters`
  - **Financial Configuration**: `http://localhost:8000/admin/financial-configuration`
  - **Reports Hub**: `http://localhost:8000/admin/reports/*`
  - **Access Control Maintenance**: `http://localhost:8000/admin/access-control-maintenance`
- **Installation Wizard**: `http://localhost:8000/install` (Redirects automatically if not yet configured)
- **Email Campaign Unsubscribe**: `http://localhost:8000/unsubscribe/{hash}`
- **Signed Request Actions**: `http://localhost:8000/requests/{request}/approve` and `/reject`
- **Scheduled Console Tasks**: Defined in `routes/console.php` (e.g. calendar synchronizations, campaign dispatches, overdue reminders).

---

## Available Scripts

### Composer Scripts

| Command | Description |
|---|---|
| `composer run dev` | Runs PHP server, queue worker, and Vite dev server concurrently. |
| `composer run setup` | Performs end-to-end installation (install deps, copy `.env`, generate key, migrate, build). |
| `composer run test` | Runs the full PHPUnit test suite. |

### NPM Scripts

| Command | Description |
|---|---|
| `npm run dev` | Starts the Vite development server with hot-module replacement. |
| `npm run build` | Compiles and minifies Tailwind CSS and JavaScript assets for production. |

### Code Quality & Static Analysis

- **Format Code (Laravel Pint):**
  ```bash
  vendor/bin/pint --dirty --format agent
  ```
- **Static Analysis (PHPStan):**
  ```bash
  vendor/bin/phpstan analyse
  ```

---

## Environment Variables

Key configuration variables in `.env.example`:

| Variable | Description | Example / Default |
|---|---|---|
| `APP_NAME` | Name of the application | `eExpert` |
| `APP_ENV` | Application environment | `local` / `production` |
| `APP_KEY` | Laravel encryption key | `base64:...` |
| `APP_TIMEZONE` | Default timezone | `Asia/Dubai` |
| `APP_LOCALE` | Primary language | `ar` |
| `APP_FALLBACK_LOCALE` | Fallback language | `en` |
| `DB_CONNECTION` | Database driver | `mysql` |
| `BROADCAST_CONNECTION` | Broadcast driver (e.g., Reverb) | `reverb` / `log` |
| `MICROSOFT_GRAPH_CLIENT_ID` | Azure App Client ID | `your-client-id` |
| `MICROSOFT_GRAPH_CLIENT_SECRET` | Azure App Secret | `your-client-secret` |
| `MICROSOFT_GRAPH_TENANT_ID` | Azure Active Directory Tenant ID | `your-tenant-id` |
| `WHATSAPP_API_TOKEN` | WhatsApp Gateway API Token | `your-token` |
| `AWS_ACCESS_KEY_ID` | S3 Storage Access Key | `your-aws-key` |
| `AWS_BUCKET` | S3 Storage Bucket | `your-bucket` |

---

## Testing

The project uses **PHPUnit 12** exclusively for unit and feature testing.

### Run All Tests

```bash
php artisan test --compact
```

### Run Specific Test Suites

```bash
# Feature tests
php artisan test --compact tests/Feature/

# Filament resource & page tests
php artisan test --compact tests/Feature/Filament/

# Live chat tests
php artisan test --compact tests/Feature/Filament/Pages/ChatTest.php

# Filter by test method name
php artisan test --compact --filter=test_can_render_list_page
```

---

## Project Structure

```
e-expert/
├── app/
│   ├── Events/                 # Domain & broadcast events (ChatMessageSent, MatterEvents)
│   ├── Filament/               # Filament admin panel implementation
│   │   ├── Pages/              # Custom Filament pages & Reports
│   │   ├── Resources/          # Domain resources (Matters, Parties, Payroll, Users, etc.)
│   │   │   └── [Resource]/
│   │   │       ├── Schemas/    # Form definitions (Separated architecture)
│   │   │       ├── Tables/     # Table configurations
│   │   │       └── Pages/      # List, Create, Edit, View sub-pages
│   │   └── Widgets/            # Dashboard stats and chart widgets
│   ├── Http/
│   │   ├── Controllers/        # Web controllers (Install, Unsubscribe, Actions)
│   │   └── Middleware/         # Custom HTTP middleware (Offline, LastSeen, Installer)
│   ├── Livewire/               # Standalone Livewire components (ChatWidget)
│   ├── Models/                 # Eloquent models & business logic
│   ├── Policies/               # Spatie & Shield authorization policies
│   └── Services/               # Domain services (Incentives, Payroll, AccessControl, MS Graph)
├── config/                     # Application & plugin configurations
├── database/
│   ├── factories/              # Model factories for testing
│   ├── migrations/             # Database schema migrations
│   └── seeders/                # Database seeders (ProductionDatabaseSeeder, Shield)
├── lang/                       # Localization files (ar, en)
├── resources/
│   ├── css/                    # Tailwind CSS v4 & theme stylesheets
│   ├── js/                     # Frontend scripts & Vite entry points
│   └── views/                  # Blade templates, payslip prints, and emails
├── routes/
│   ├── console.php             # Scheduled Artisan commands
│   └── web.php                 # Web and action routes
└── tests/
    ├── Feature/                # Feature & integration tests
    └── Unit/                   # Unit tests
```

---

## License

This software is proprietary and confidential. All rights reserved.
