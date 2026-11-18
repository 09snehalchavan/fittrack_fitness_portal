# Sanskar Kuber Chavan — Personal Portfolio

Static front end (HTML, CSS, vanilla JS) with a PHP contact-form backend and optional MySQL storage.

## Structure

```
index.html            Main page (all sections)
404.html              Custom not-found page (uses absolute /assets paths, so deploy at the domain root)
.htaccess             404 routing, security headers, caching, compression (Apache)
assets/css/style.css  Design system and styles
assets/js/script.js   Nav, scroll reveal, form validation and submit
assets/images/        favicon.svg, og-image.svg / og-image.png
php/contact.php       Contact form endpoint
includes/config.php   Mail and database settings (blocked from web access)
database/database.sql Optional table for storing messages
```

## Deploy (shared hosting / cPanel / Hostinger)

1. Upload everything to `public_html` (keep the folder layout).
2. Open `includes/config.php` and set `mail_from` to an address on your domain, for example `no-reply@yourdomain.com`. This makes `mail()` delivery far more reliable. Messages go to `chavansanskar19@gmail.com`.
3. Test the form once on the live site. If email does not arrive, create a mailbox on your host and check its spam folder, or enable database storage (below) as a backup.
4. In `index.html`, add absolute URLs once you have your domain:
   - `<meta property="og:image" content="https://yourdomain.com/assets/images/og-image.png">`
   - `<meta property="og:url" content="https://yourdomain.com/">` and `<link rel="canonical" href="https://yourdomain.com/">`

PHP 7.4+ is required (works on 8.x). The form needs PHP, so it will not run on Netlify static hosting. Use a PHP host.

## Optional: store messages in MySQL

1. Import `database/database.sql` in phpMyAdmin.
2. In `includes/config.php`, set `'enabled' => true` and fill in host, name, user, password.

## Spam protection included

CSRF token, hidden honeypot field, minimum fill time (3s), rate limit per session and per IP (3 per 10 min), strict server-side validation, header-injection-safe email, prepared SQL statements.

## Project previews

Card images are live screenshots loaded from `image.thum.io`. If one fails to load, the card shows the project name instead. To use your own images, save screenshots in `assets/images/` and change the `src` of that card's `<img>`.

## Editing content

- Phone, email, WhatsApp number: search `7420899489` in `index.html`.
- Projects: each is one `<article class="card project">` block.
- Colours: CSS variables at the top of `style.css`.
