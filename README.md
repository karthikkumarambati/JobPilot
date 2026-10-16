# JobPilot — Personal AI Job Application Assistant

A local-first Windows 11 dashboard for finding and organizing WordPress Developer opportunities in Hyderabad and remote roles.

## What this MVP does

- Local PHP + MySQL dashboard; no Node.js required.
- Login-protected dashboard with CSRF protection and prepared SQL statements.
- Searches public Greenhouse and Lever company career-board APIs where configured.
- Provides one-click search links for LinkedIn, Naukri, Indeed, Glassdoor, and remote job boards.
- Scores imported jobs against a WordPress/PHP/WooCommerce profile and location preferences.
- Tracks jobs and statuses, prevents duplicate records by source URL, and records application notes.
- Generates tailored CV bullet suggestions and cover letters using either an OpenAI-compatible API or local Ollama.
- Includes CSV export and a basic test checklist.

## Important boundaries

This is a functional starter MVP, not a universal unattended auto-apply bot. LinkedIn, Naukri, Indeed, and Glassdoor may restrict automated access and submission, change their UI, or require CAPTCHA, identity checks, or additional screening answers. This project does **not** bypass those controls or scrape private/login-only pages. It opens official search pages, imports public career-board postings, prepares application material, and tracks the result. Submit applications yourself unless a portal explicitly provides a permitted API/integration and you have configured it.

The AI output is a draft. Review every claim and never submit qualifications, years of experience, work authorization, salary expectations, or screening answers that are not true.

## Requirements

- Windows 11
- XAMPP (Apache + PHP 8.1+ + MySQL/MariaDB) or equivalent
- PHP extensions: `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo`
- Optional local AI: Ollama installed and running
- Optional cloud AI: an OpenAI-compatible API key

No Node.js is required.

## Install on Windows 11 using XAMPP

1. Install XAMPP and start **Apache** and **MySQL**.
2. Extract this project into `C:\xampp\htdocs\jobpilot`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create a database named `jobpilot` with collation `utf8mb4_unicode_ci`.
5. Copy `.env.example` to `.env` and edit database credentials. Keep `.env` outside public access in production; for local XAMPP, the included `.htaccess` denies access.
6. Open `http://localhost/jobpilot/public/setup.php` and create the first admin account. Remove or rename `public/setup.php` after setup.
7. Visit `http://localhost/jobpilot/public/`.
8. Add public Greenhouse/Lever company board identifiers in Settings or edit `config.php` (details below).
9. Configure AI in `.env`, or leave it disabled and use the local scoring engine.

### Apache note

For a simple XAMPP setup, browse to `/jobpilot/public/`. For a safer setup, configure the virtual host document root to `C:/xampp/htdocs/jobpilot/public`, so `app/`, `.env`, and `storage/` are outside the web root.

## Configuration

Copy `.env.example` to `.env`.

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`: MySQL connection.
- `APP_URL`: local app URL.
- `AI_PROVIDER=none|openai|ollama`
- `AI_BASE_URL`: API base URL. For Ollama, use `http://127.0.0.1:11434/v1`.
- `AI_API_KEY`: cloud API key, or any placeholder for local Ollama if required by the client.
- `AI_MODEL`: model name, e.g. an available chat model or Ollama model.
- `APP_SECRET`: replace with a long random secret.

AI calls send the job description and the profile text entered in the app to the configured provider. Do not include sensitive personal data unless you are comfortable sending it to that provider.

## Public company career-board search

Edit `app/config.php` and set `GREENHOUSE_BOARDS` and `LEVER_SITES` as comma-separated public identifiers, for example:

```php
'greenhouse_boards' => ['company_board_slug'],
'lever_sites' => ['company-site-slug'],
```

The public APIs only work for companies using those platforms and only return their published postings. They are not a general web crawler. Use company career pages for other employers and paste/import job links manually.

## Using the dashboard

1. Sign in and set your profile, target role, location preferences, and skills.
2. Click **Search public career boards** to import available Greenhouse/Lever listings.
3. Use the portal search shortcuts to find roles on LinkedIn, Naukri, Indeed, Glassdoor, and remote boards.
4. Add relevant jobs from their listing URLs using **Add job**.
5. Review match scores and open the original posting.
6. Use **Generate application draft** to create a cover letter and suggested CV bullets.
7. Update status after applying and add follow-up notes.

## Security checklist

- Keep the app bound to localhost; do not expose it to the public internet without a security review.
- Use a strong unique admin password.
- Remove `public/setup.php` after creating the first account.
- Keep `.env` and `storage/` outside the public document root in production.
- Never store portal passwords or session cookies in this app.
- AI-generated content is not trusted; verify all facts.
- Use HTTPS if deployed, secure backups, and update PHP/XAMPP regularly.
- This starter has login, CSRF, output escaping, prepared statements, upload restrictions, and basic session hardening, but it has not undergone a professional security audit.

## Testing

- Run `php tests/smoke_test.php` from the project root to check basic configuration and required files.
- In XAMPP, confirm login, add a job, duplicate URL handling, career-board import, AI draft generation, status updates, and CSV export.
- Verify that invalid URLs, missing required fields, and unauthenticated requests are rejected.
- Test with AI disabled first, then test Ollama/API settings separately.

## Repository layout

```text
jobpilot/
├── .env.example
├── .gitignore
├── README.md
├── app/
│   ├── bootstrap.php
│   ├── config.php
│   ├── db.php
│   ├── functions.php
│   └── schema.sql
├── public/
│   ├── index.php
│   ├── setup.php
│   ├── logout.php
│   ├── assets/app.css
│   └── assets/app.js
├── storage/resumes/.gitkeep
└── tests/smoke_test.php
```

## GitHub

Create a private repository if you will later add personal CVs or configuration. Do not commit `.env`, resumes, exports, or real application records.
