# 📄 Invoice Management System
### PHP + MySQL (XAMPP) — Full Setup Guide

---

## 🗂 Project Structure

```
invoice_system/
├── login.php                  ← Entry point — Authentication page
├── logout.php                 ← Ends session, redirects to login
├── setup_users.php            ← Run ONCE after DB import, then DELETE
├── index.php                  ← Dashboard (protected)
├── database.sql               ← Import in phpMyAdmin first
├── includes/
│   ├── config.php             ← DB config, session & auth helpers
│   ├── header.php             ← Sidebar + topbar (auth-aware)
│   └── footer.php
├── assets/
│   ├── css/style.css
│   └── js/app.js
└── pages/
    ├── invoices.php           ← Create, edit, list invoices
    ├── view_invoice.php       ← View + print/PDF invoice
    ├── clients.php            ← Client management (Admin only)
    ├── payments.php           ← Record & track payments
    ├── invoice_status.php     ← Invoice status lookup
    └── manage_users.php       ← User management (Admin only)
```

---

## ⚙️ Setup Steps

### 1. Copy files to XAMPP
```
C:\xampp\htdocs\invoice_system\
```

### 2. Start XAMPP
Open XAMPP Control Panel → Start **Apache** and **MySQL**

### 3. Import the Database
1. Go to `http://localhost/phpmyadmin`
2. Click **Import** → Choose `database.sql` → Click **Go**

### 4. Create Seed Users
Go to: `http://localhost/invoice_system/setup_users.php`

This generates bcrypt-hashed passwords and inserts demo users.
**Delete this file after running it.**

### 5. Sign In
Go to: `http://localhost/invoice_system/login.php`

---

## 🔐 User Roles & Demo Credentials

| Email | Password | Role | Access |
|---|---|---|---|
| admin@invoice.com | Admin1234! | **Admin** | Full access — all invoices, clients, users |
| john@example.com | Company123! | **Company** | Only their own invoices & payments |
| sara@techbd.com | Company123! | **Company** | Only their own invoices & payments |
| james@leeco.io | Company123! | **Company** | Only their own invoices & payments |

### Role Differences
- **Admin**: Sees all clients, all invoices, all payments; can manage users and clients
- **Company**: Sees only invoices sent to their company; can check status and view payments for their invoices

---

## ✨ Features

| Feature | Who |
|---|---|
| Login / Logout with sessions | All |
| Dashboard with live stats | All (scoped by role) |
| Create & edit invoices | All (company users see only theirs) |
| View & Print invoices (→ PDF) | All |
| Client management | Admin only |
| User management (create/edit/deactivate) | Admin only |
| Payment recording & history | All (scoped by role) |
| Invoice status checker | All |
| Auto overdue detection | System |

---

## 🔧 Configuration
Edit `includes/config.php`:
```php
define('DB_PASS', '');      // Add MySQL password if set
define('CURRENCY', '$');    // Change currency symbol
define('APP_NAME', 'Invoice Manager'); // Change app name
```
