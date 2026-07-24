# Hotspot Billing System

A Laravel hotspot billing portal for MikroTik RouterOS and Selcom payments.

This started as a funny project: connect a MikroTik hotspot, accept mobile money through Selcom, and automatically give paid users internet access. It became useful enough to share publicly.

Use it, modify it, break it in a test router first, then make it better.

## What It Does

This project lets you sell time-based Wi-Fi packages through a captive portal. A customer opens the checkout page, chooses a package, pays through Selcom, and the system provisions access on MikroTik.

The admin portal also gives you a practical view of what is happening on the router: active hotspot sessions, connected hosts, DHCP leases, IP bindings, hotspot users, queues, logs, router health, uptime, CPU, memory, traffic, revenue, and checkout analytics.

## Main Features

- Selcom payment checkout for mobile money payments.
- MikroTik RouterOS API integration.
- Automatic Hotspot user creation after successful payment.
- Automatic Hotspot active login when the device is visible on the router.
- Per-user simple queues for package speed limits.
- Queue order repair so `RateLimit_*` queues stay above broad hotspot queues.
- Admin dashboard for users, transactions, packages, revenue, and router state.
- Active Router Sessions page with tabs for active sessions, hosts, DHCP leases, IP bindings, and MikroTik hotspot users.
- Simple Queues page with readable rates, limits, bytes, packets, status, and comments.
- MikroTik Logs page for router logs from `/log/print`.
- Router Panel with uptime, CPU, memory, interfaces, and live router status.
- Analytics page for revenue, conversion rate, checkout visitors, router usage, active users, and hosts.
- Checkout visitor log grouped by MAC/IP, with click-to-expand visit history.
- Admin package management.
- Manual reconnect, extend time, delete, and kick actions.
- Expired package cleanup through Laravel scheduler/cron.
- Tests for the risky provisioning, router, analytics, and cleanup behavior.

## Requirements

- PHP 8.3 or newer
- Composer
- Node.js and npm
- Laravel-supported database, for example SQLite or MySQL
- MikroTik router with RouterOS API enabled
- MikroTik Hotspot configured already
- Selcom merchant account and API credentials
- A public HTTPS domain for Selcom webhooks in production

You need your own Selcom account. This repository does not include payment credentials.

For help or questions, contact: `Kibodytz@gmail.com`

## Important MikroTik Notes

This app expects MikroTik Hotspot to be working before the portal is used. The software controls users, active logins, queues, bindings cleanup, and monitoring through the RouterOS API, but it does not magically configure your whole router from zero.

Recommended MikroTik setup:

- Enable RouterOS API service.
- Create an API user with enough permissions for hotspot, queue, DHCP, logs, and system reads.
- Configure Hotspot and DHCP correctly on the router.
- Configure your captive portal redirect/walled garden for your domain and Selcom endpoints.
- Make sure the portal server can reach the router API address and port.

Speed limits are enforced with MikroTik simple queues named like `RateLimit_XX:XX:XX:XX:XX:XX`. These queues must be above broad hotspot queues such as `hs-<hotspot1>`, otherwise the broad queue can catch traffic first and the user rate limit will not apply.

## Selcom Notes

You must configure Selcom credentials in `.env`:

```env
SELCOM_BASE_URL=https://apigw.selcommobile.com
SELCOM_API_KEY=your_api_key
SELCOM_API_SECRET=your_api_secret
SELCOM_VENDOR_TILL=your_vendor_till
```

Your production app URL must be correct because Selcom webhooks depend on it:

```env
APP_URL=https://your-domain.example
```

Webhook route:

```text
POST /webhook/selcom
```

## Installation

Clone the project:

```bash
git clone https://github.com/yourusername/hotspot-billing.git
cd hotspot-billing
```

Install dependencies:

```bash
composer install
npm install
npm run build
```

Create environment file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure database, admin login, MikroTik, and Selcom values in `.env`.

Example admin and router values:

```env
ADMIN_USER=admin
ADMIN_PASS=change_this_password

MIKROTIK_HOST=192.168.88.1
MIKROTIK_USER=api-user
MIKROTIK_PASS=api-password
```

Run migrations:

```bash
php artisan migrate
```

Start locally:

```bash
php artisan serve
```

Admin portal:

```text
/admin
```

Checkout page:

```text
/checkout
```

## Production Setup

For production hosting, configure your web server to point to Laravel's `public` directory.

You must run the Laravel queue worker if provisioning is queued:

```bash
php artisan queue:work --tries=3
```

Use Supervisor, systemd, aaPanel process manager, or another process manager so the queue worker stays running.

You must also configure Laravel's scheduler cron. This project clears expired hotspot access through a scheduled command, so cron is required in production.

Add this cron entry on the server:

```cron
* * * * * cd /path/to/hotspot-billing && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:

```bash
php artisan hotspot:clean-expired
```

That cleanup removes expired router access, including active sessions, MikroTik hotspot users, simple queues, hosts, IP bindings, and cookies where applicable.

If you host this for real users and forget cron, expired users may stay on the router longer than expected. Configure cron.

## Useful Commands

Run tests:

```bash
php artisan test
```

Clear cached config after changing `.env`:

```bash
php artisan config:clear
php artisan cache:clear
```

Run expired access cleanup manually:

```bash
php artisan hotspot:clean-expired
```

Run queue worker:

```bash
php artisan queue:work --tries=3
```

Build frontend assets:

```bash
npm run build
```

## Admin Pages

- `/admin` - active users and transactions
- `/admin/active-sessions` - MikroTik active sessions, hosts, DHCP leases, IP bindings, and hotspot users
- `/admin/router` - router health panel
- `/admin/queues` - MikroTik simple queues and speed limit visibility
- `/admin/logs` - MikroTik logs
- `/admin/packages` - package management
- `/admin/earnings` - revenue report
- `/admin/analytics` - business and checkout analytics

## Disclaimer

This is a real working project, but it began as a funny experiment. Test it carefully with your own MikroTik and Selcom sandbox/live account before using it with customers.

I am not responsible for misconfigured routers, failed payments, free internet, wrong firewall rules, or angry customers. Read the code, test your setup, and monitor your router.

## Contact

For questions, feedback, or setup help:

```text
Kibodytz@gmail.com
```

## License

This project is open-source software licensed under the MIT license.
