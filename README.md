# NYS – Nkunziyenungu Systems

NYS (Nkunziyenungu Systems) is a **Smart Home & Business Management Platform** designed to manage users, accounts, devices, livestock, stock, and finances through a centralized system.

This repository contains the **full stack source code and documentation**, including:

* Laravel API backend
* Vue.js frontend
* Project documentation

---

## Project Structure

```
NYS/
│
├── nkunziyenugu_api/        # Laravel Backend API
├── nkunziyenugu_systems/    # Vue.js Frontend Application
├── NYS_Documentation/       # Project documentation & specs
└── README.md
```

---

## Project Components

### 🔹 nkunziyenugu_api (Backend)

* Laravel REST API
* Authentication (Sanctum)
* Role-based access control (RBAC)
* Multi-account support
* MySQL database

### 🔹 nkunziyenugu_systems (Frontend)

* Vue.js (Vue Router + Vuex)
* Role-based UI rendering
* Authentication & protected routes
* API integration using Axios

### 🔹 NYS_Documentation

* Nkunziyenungu Smart Home DB
* Nkunziyenungu Smart Home ESP
* Nkunziyenungu Smart Home

---

## Prerequisites

Make sure you have the following installed:

* Node.js (v16+ recommended)
* NPM
* PHP 8.1+
* Composer
* MySQL / MariaDB
* Git

---

## Frontend Setup (Vue.js)

```bash
cd nkunziyenugu_systems
npm install
npm run serve
```

All required dependencies are defined in:

```
package.json
package-lock.json
```

The frontend will be available at:

```
http://localhost:8080
```

---

##  Backend Setup (Laravel API)

```bash
cd nkunziyenugu_api
composer install
cp .env.example .env
php artisan key:generate
```

### Run the backend server

```bash
php artisan serve
```

API will be available at:

```
http://127.0.0.1:8000
```

---

##  Database Migration ( Development Only)

```bash
php artisan migrate:refresh
```

**WARNING**
This command **drops and recreates all tables**.
Use **ONLY in development**, never in production.

---

## Authentication & Roles

Supported roles:

* **SuperAdmin** – Full system access
* **Owner** – Full access to own account
* **Admin** – Limited access (configurable)
* **Viewer** – Read-only access

Roles are assigned **per account**, not globally.

---

## Development Notes

* Frontend and backend are decoupled
* API authentication uses Bearer Tokens (Sanctum)
* Role enforcement is done **server-side**
* Axios interceptors handle auth & session expiry

---

##  Roadmap (Planned Features)

* Device management (IoT integration)
* Financial reporting
* Livestock & stock modules
* Audit logs
* Permissions matrix per module

---

## License

This project is private and proprietary.
All rights reserved © Nkunziyenungu Systems.

---

**Maintained by:**
Nkunziyenungu Systems
