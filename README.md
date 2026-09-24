# Elldy Academy

Professional PHP/MySQL website for Elldy's data analytics and BI learning platform.

## Setup

1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin: `http://localhost/phpmyadmin`
3. Import `database.sql`.
4. Open the website: `http://localhost/academy/`
5. Open admin: `http://localhost/academy/admin/login.php`

## Default Admin

- Username: `admin`
- Password: `admin123`

Change the admin password after first login for production use.

## Admin dashboard analytics

The secure Elldy embed appears only on `admin/index.php`. Set the server environment
variable `ELLDY_EMBED_SERVER_CREDENTIAL` to your Elldy server credential (without
the `Bearer ` prefix), then restart Apache. For XAMPP, you can set it with
`SetEnv ELLDY_EMBED_SERVER_CREDENTIAL "your-credential"` in the Apache virtual-host
configuration outside this repository. PHP cURL with working HTTPS certificates
is required.

The browser calls `admin/elldy_token.php`, which requires an authenticated admin
and a valid session CSRF token. It sends `subject: admin:<admin id>` and the
verified admin ID as `attributes.user_id` to Elldy. Never put the server credential
in JavaScript or commit it to this repository.

## Database connection

The database connection is in `config/database.php`.

Default values:

- Database: `elldy_academy`
- User: `root`
- Password: empty

## WhatsApp OTP Login

Trainee login uses WhatsApp OTP instead of passwords.

Configure WhatsApp from the admin panel:

- Open `http://localhost/academy/admin/whatsapp.php`
- Save WhatsApp Business Account ID
- Save Phone Number ID
- Save Access Token
- Save approved OTP template name, for example `elldy_academy_otp`
- Save template language code exactly as approved in Meta, for example `en`

`config/whatsapp.php` only keeps default fallback IDs. The live OTP sender reads settings from the database.

## Main Features

- Home page for Elldy Academy
- Free first-session registration without trainee signup or login
- Active analytics program listing and program detail pages
- Backend-managed program learning plan and completion benefits
- Trainee contact details saved for admin follow-up
- First session free enrollment status
- Trainee dashboard with session access and payment request
- Admin dashboard
- Analytics program create/update/deactivate
- Material update publishing
- Enrollment status management: first session free, payment pending, paid, cancelled
