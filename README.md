# ShopPHP — Railway Deployment Guide

A full-featured PHP 8.2 ecommerce application with:

- 🛒 Product catalogue, cart, checkout, wishlist
- 👤 Customer accounts with order history
- 🔐 CSRF protection, password hashing, prepared statements
- 📊 Admin dashboard (stats, orders, products, users, activity logs)
- 🚀 One-click Railway deployment via Docker

---

## Quick Start (local)

```bash
# Copy env template
cp .env.example .env
# Edit .env with your local MySQL credentials, then:
docker compose up --build
```

Open <http://localhost:8080>.  
Admin login: `admin@shopphp.com` / `admin123`

---

## Deploy to Railway

### Prerequisites

- A free [Railway](https://railway.app) account
- The Railway CLI (optional but useful): `npm i -g @railway/cli`

---

### Step 1 — Push your code to GitHub

```bash
git init
git add .
git commit -m "Initial commit"
gh repo create shopphp --public --push
```

---

### Step 2 — Create a new Railway project

1. Go to <https://railway.app/new>
2. Click **"Deploy from GitHub repo"**
3. Authorise Railway and select your **shopphp** repository
4. Railway detects the `Dockerfile` automatically — click **Deploy**

---

### Step 3 — Add a MySQL database

1. In your Railway project, click **"+ New"** → **"Database"** → **"MySQL"**
2. Wait for the database to provision (≈ 30 seconds)
3. Click the MySQL service → **"Connect"** tab
4. Note the connection values: `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`

---

### Step 4 — Configure environment variables

1. Click your **web service** (the PHP app)
2. Go to **"Variables"** tab
3. Add the following variables (copy values from the MySQL Connect tab):

| Variable    | Value (from MySQL Connect tab)  |
|-------------|----------------------------------|
| `DB_HOST`   | e.g. `containers-us-west-1.railway.app` |
| `DB_USER`   | e.g. `root`                      |
| `DB_PASS`   | your MySQL password              |
| `DB_NAME`   | e.g. `railway`                   |
| `SITE_URL`  | your Railway URL (see Step 5)    |
| `APP_DEBUG` | `false`                          |

> **Tip:** Railway also provides `DATABASE_URL` – you can use that directly if you prefer PDO.

---

### Step 5 — Set your public URL

1. In the web service, go to **"Settings"** → **"Networking"**
2. Click **"Generate Domain"** — Railway gives you a URL like `https://shopphp-production.up.railway.app`
3. Copy that URL and paste it as the value of the `SITE_URL` variable (no trailing slash)

---

### Step 6 — Redeploy

After adding variables, click **"Deploy"** (or push a new commit).  
Watch the build logs — you should see:

```
[entrypoint] Database is reachable.
[entrypoint] Database is empty – importing database.sql…
[entrypoint] Database initialised successfully.
[entrypoint] Starting Apache…
```

Your shop is now live! 🎉

---

### Step 7 — Change the default admin password

1. Visit `https://<your-railway-url>/admin/login.php`
2. Log in with `admin@shopphp.com` / `admin123`
3. Go to **Admin → Settings** and change the password immediately

---

## Environment Variables Reference

| Variable     | Required | Default          | Description                        |
|--------------|----------|------------------|------------------------------------|
| `DB_HOST`    | ✅       | `127.0.0.1`      | MySQL host                         |
| `DB_USER`    | ✅       | `shopuser`       | MySQL username                     |
| `DB_PASS`    | ✅       | *(empty)*        | MySQL password                     |
| `DB_NAME`    | ✅       | `shopphp`        | MySQL database name                |
| `SITE_URL`   | ✅       | `http://localhost:8080` | Public URL (no trailing slash) |
| `SITE_NAME`  | —        | `ShopPHP`        | Store display name                 |
| `SITE_EMAIL` | —        | `info@shopphp.com` | Contact e-mail                   |
| `APP_DEBUG`  | —        | `false`          | Set `true` only during development |

---

## Local Development with Docker Compose

Create a `docker-compose.yml` alongside this README:

```yaml
version: "3.9"
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: shopphp
      MYSQL_USER: shopuser
      MYSQL_PASSWORD: shop123
    ports:
      - "3306:3306"

  web:
    build: .
    ports:
      - "8080:8080"
    environment:
      DB_HOST: db
      DB_USER: shopuser
      DB_PASS: shop123
      DB_NAME: shopphp
      SITE_URL: http://localhost:8080
      APP_DEBUG: "true"
    depends_on:
      - db
```

```bash
docker compose up --build
```

---

## Security Notes

- Passwords are hashed with `password_hash()` (bcrypt, PHP default)
- All database queries use **prepared statements** (no SQL injection)
- **CSRF tokens** protect every form
- Sessions are hardened: `httponly`, `samesite=Lax`, periodic ID regeneration
- Security headers sent on every request (`X-Frame-Options`, `X-XSS-Protection`, etc.)
- `display_errors` is off in production (`APP_DEBUG=false`)
- Sensitive files (`.env`, `database.sql`, `Dockerfile`) are blocked by `.htaccess`
- Admin activity is logged to the `activity_logs` table (logins, logouts, product/order changes)

---

## Project Structure

```
├── Dockerfile              ← Production image (PHP 8.2 + Apache on :8080)
├── docker-entrypoint.sh    ← Waits for DB, auto-imports schema, starts Apache
├── .env.example            ← Copy to .env for local development
├── .htaccess               ← Rewrite rules, security, caching
├── database.sql            ← Full schema + seed data
├── includes/
│   ├── config.php          ← Reads env vars, session hardening
│   ├── db.php              ← MySQLi singleton with error handling
│   └── functions.php       ← Auth, CSRF, logging, helpers
├── admin/                  ← Admin panel (dashboard, orders, products, users, logs)
└── assets/                 ← CSS, JS, images
```

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| "Service temporarily unavailable" | Check DB env vars in Railway Variables tab |
| Images not loading | Ensure `SITE_URL` matches your Railway domain exactly |
| Redirect loops | Make sure `SITE_URL` does **not** end with `/` |
| Database not initialised | Check entrypoint logs; confirm MySQL plugin is healthy |
| 500 errors | Set `APP_DEBUG=true` temporarily to see details, then reset to `false` |
