## 🗂 Payment Gateway

---
In this project I have added all different functions of a payment gateway (checkout, success, webhook). However, I didn't add any stripe library so the payment gateway doesn't work. But how will they work if I add a gateway is fully shown in the code files.

## 🗂 Project Structure

```
invoice_system/
├── login.php                  ← Entry point — Authentication page
├── logout.php                 ← Ends session, redirects to login
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


