# 📚 Bibliotheca — Library Management System

A full-featured library management system built with PHP, MySQL, and JavaScript.

---

## ✅ Requirements

- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache / Nginx with mod_rewrite enabled
- A local server like XAMPP, WAMP, Laragon, or MAMP

---

## 🚀 Setup Instructions

### 1. Copy files to your web root

Place the `library/` folder inside your server's root:
- XAMPP → `C:/xampp/htdocs/library/`
- WAMP  → `C:/wamp64/www/library/`
- Linux → `/var/www/html/library/`

### 2. Create the database

Open **phpMyAdmin** (or run via CLI):
```sql
SOURCE /path/to/library/database.sql;
```
Or copy-paste the contents of `database.sql` into phpMyAdmin's SQL tab.

### 3. Configure the database connection

Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // ← your MySQL username
define('DB_PASS', '');          // ← your MySQL password
define('DB_NAME', 'library_db');
define('SITE_URL', 'http://localhost/library');  // ← adjust if needed
```

### 4. Set upload permissions

Make sure the `uploads/covers/` directory is writable:
```bash
chmod -R 775 uploads/
```

### 5. Open in browser

Navigate to: **http://localhost/library/**

---

## 🔑 Default Credentials

| Role  | Email                | Password   |
|-------|----------------------|------------|
| Admin | admin@library.com    | Admin@123  |

> ⚠️ Change the admin password after first login!

---

## 📁 Project Structure

```
library/
├── config/
│   └── db.php              # DB config + helper functions
├── auth/
│   ├── login.php           # Login page
│   ├── register.php        # Registration
│   └── logout.php          # Logout handler
├── user/
│   ├── dashboard.php       # Browse books (user)
│   ├── reserve.php         # Book reservation page
│   ├── my-reservations.php # User reservation history
│   └── account.php         # Profile management
├── admin/
│   ├── dashboard.php       # Admin overview + charts
│   ├── books.php           # Book CRUD + image upload
│   ├── customers.php       # Member management
│   ├── reservations.php    # Reservation management
│   └── statistics.php      # Detailed statistics
├── includes/
│   ├── header.php          # Shared navbar + head
│   └── footer.php          # Shared scripts + footer
├── assets/
│   ├── css/style.css       # Global stylesheet
│   └── js/main.js          # JS interactions + charts
├── uploads/
│   └── covers/             # Book cover images (writable)
├── index.php               # Landing page
└── database.sql            # DB schema + seed data
```

---

## 🎯 Features

### User-Side
- ✅ Register new account (auto-generated Member ID)
- ✅ Login / logout with session management
- ✅ Browse & search books (by title, author, ISBN, category)
- ✅ Reserve available books
- ✅ View reservation history with status tracking
- ✅ View and edit account profile
- ✅ Change password

### Admin-Side
- ✅ Admin dashboard with live stats + charts
- ✅ Book management — add, edit, delete + cover image upload
- ✅ Member management — view, suspend/activate, delete
- ✅ Reservation management — approve, mark returned, cancel
- ✅ Statistics page — trends, top books, top members, charts

---

## 🎨 Design

- **Theme:** Dark editorial (navy + gold accent)
- **Fonts:** Playfair Display (headings) + DM Sans (body)
- **Charts:** Chart.js 4.x
- **No external CSS framework** — 100% custom CSS

---

## 📝 Notes

- Borrowing period: **14 days** (configurable in `config/db.php` via `MAX_BORROW_DAYS`)
- Overdue status auto-updates on page load
- Book cover images: JPG, PNG, WebP, GIF — max 5 MB
- Password hashing: PHP `password_hash()` with BCRYPT
