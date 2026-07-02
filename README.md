# 🚀 Hotspot Billing System (The "Funny Project" Edition)

Welcome to the **Hotspot Billing System**! Built with Laravel, this was originally started as a "funny project" to see if I could duct-tape a MikroTik router and the Selcom payment gateway together. Turns out, it works! Now I'm open-sourcing it so you can have fun with it too.

## 🌟 Features (Yes, it actually has features)

- **MikroTik RouterOS Integration**: Connects seamlessly with MikroTik routers using the `routeros-api-php` package to provision hotspot users.
- **Selcom Payment Gateway**: Handles payments via Selcom mobile money using `laravel-selcom`.
- **Automated Provisioning**: Once a payment is confirmed, the user is automatically granted internet access. Magic! 🪄
- **Admin Dashboard**: Manage packages, view transactions, and monitor your captive portal operations.
- **Captive Portal Goodness**: Built to handle captive portal walled garden bypasses and waiting pages that seamlessly redirect when you finally get internet.

## 🛠️ Prerequisites

Before you dive in, you'll need a few things:
- PHP 8.3 or higher
- Composer
- Node.js & NPM
- Database (SQLite, MySQL, or PostgreSQL)
- A MikroTik Router (with API access enabled)
- Selcom API credentials (for taking real or test payments)

## 🚀 Getting Started

1. **Clone the repo:**
   ```bash
   git clone https://github.com/yourusername/hotspot-billing.git
   cd hotspot-billing
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install NPM dependencies:**
   ```bash
   npm install
   npm run build
   ```

4. **Set up your environment:**
   Copy the example `.env` file and generate an app key.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your `.env`:**
   Open the `.env` file and add your database credentials, MikroTik Router API details, and Selcom API keys.
   Make sure you run queue workers if you are using queueing for provisioning:
   ```bash
   php artisan queue:listen
   ```

6. **Migrate the database:**
   ```bash
   php artisan migrate
   ```

7. **Run the development server:**
   ```bash
   php artisan serve
   ```

## ⚠️ Disclaimer

I built this as a side project for fun. It comes with zero warranties. If it accidentally gives your entire neighborhood free internet or charges someone a million , that's on you! Please test it thoroughly before putting it anywhere near a production environment. 

## 🤝 Contributing

Found a bug? Want to add a feature? Feel free to submit a Pull Request. If you can make this "funny project" even better, I'm all for it.

## 📜 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
