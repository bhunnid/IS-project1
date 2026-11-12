# IS Project 1 — Signup / Login Web App

Server Administration **CAT 2** — hosting a PHP web application on a Linux
server (Apache + PHP 8.1 + MySQL 8.0).

**Author:** Brandon Kiplangat — CNS 3A, Strathmore University

## Features
- User **signup** (`register.php`) with hashed passwords (`password_hash`)
- User **login** (`login.php`) with session handling
- Protected **dashboard** (`dashboard.php`) listing registered users
- **Feedback** form (`feedback.php`) — second form, POST + retrieve
- All DB access via **prepared statements** (SQL-injection safe)
- Dedicated least-privilege DB user (`cat2_user`), never root

## Stack
| Layer | Software |
|-------|----------|
| OS | Ubuntu 22.04 LTS |
| Web server | Apache 2.4 |
| Language | PHP 8.1 |
| Database | MySQL 8.0 (database: `CAT2`) |

## Setup
```bash
# 1. Create the database, tables and app user
sudo mysql < db/cat2_setup.sql

# 2. Deploy to the web root
sudo cp -r . /var/www/html/IS-project1

# 3. Visit
http://localhost/IS-project1/register.php
```

## Files
```
config.php            DB connection (CAT2)
register.php          Signup page
login.php             Login page
dashboard.php         Post-login page (lists users)
logout.php            Ends the session
feedback.php          Second form + retrieval
partials/             Shared header & footer
assets/style.css      Styling
db/cat2_setup.sql     Database + tables + app user
```
