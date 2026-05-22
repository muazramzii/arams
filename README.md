# ARAMS — Academic Research Activity Monitoring System

A web-based research activity tracker for **Universiti Tun Hussein Onn Malaysia (UTHM)**, Faculty of Computer Science and Information Technology (FSKTM). Replaces the manual FRT10.xlsm spreadsheet workflow with a centralised PHP/MySQL platform.

> Final Year Project — Muaz Ramzi, supervised by Dr. Shuhaida.

## Features

- **Lecturer dashboard** — profile, research projects, grants, publications, students, income, IP records
- **Admin dashboard** — manage lecturers, faculties, projects, grants, publications, analytics, reports
- **Validation workflow** — admin review of lecturer-submitted research entries
- **Reporting & analytics** — per-lecturer and faculty-wide summaries

## Tech Stack

- **Backend:** PHP (PDO + MySQLi), vanilla — no framework
- **Database:** MySQL (XAMPP local)
- **Frontend:** Vanilla JS, CSS custom properties
- **Runtime:** XAMPP (Apache + MySQL) on Windows

## Local Setup

1. Install [XAMPP](https://www.apachefriends.org/).
2. Clone this repo into `C:\xampp\htdocs\arams`.
3. Start Apache + MySQL from the XAMPP control panel.
4. Open phpMyAdmin, create a database named `arams_uthm` (and/or `arams_db` if you use the legacy `config/db.php`).
5. Import the schema (export from your local instance via phpMyAdmin → SQL).
6. Visit `http://localhost/arams/`.

## Configuration

Database credentials live in:
- `config/database.php` — primary PDO connection (`arams_uthm`)
- `config/db.php` — legacy MySQLi connection (`arams_db`)

Defaults are XAMPP standard (`root` / no password). **Change before any non-local deployment.**

## Project Structure

```
arams/
├── admin/          # Admin-side pages (legacy)
├── api/            # AJAX endpoints
├── assets/         # CSS, JS, images
├── auth/           # Login/logout
├── config/         # DB connection
├── includes/       # Shared header, sidebar, auth helpers
├── lecturer/       # Lecturer-side pages (legacy)
├── pages/
│   ├── admin/      # Current admin pages
│   └── lecturer/   # Current lecturer pages
└── uploads/        # User-uploaded files (gitignored)
```

## License

Academic project — not licensed for redistribution.
