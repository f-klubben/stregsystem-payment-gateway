<div align="center">

<!-- LOGO -->
<br/>

```
████ ████ ████ ████
 ██   ██   ██   ██
  ╲━━━━━━━━━━━━╱
     StregPay
```

# StregPay

### WooCommerce Payment Gateway for F-Klub's Stregdollar System

*Let members pay with their club balance — directly at checkout.*

<br/>

[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.x%2B-96588a?style=flat-square&logo=woocommerce&logoColor=white)](https://woocommerce.com)
[![WordPress](https://img.shields.io/badge/WordPress-6.x%2B-21759b?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Docker](https://img.shields.io/badge/Docker-Dev%20Environment-2496ed?style=flat-square&logo=docker&logoColor=white)](https://docker.com)
[![License](https://img.shields.io/badge/License-LPW-22c55e?style=flat-square)](#license)
[![F-Klub](https://img.shields.io/badge/F--Klub-fklub.dk-f59e0b?style=flat-square)](https://fklub.dk)

<br/>

[**Stregsystemet →**](https://github.com/f-klubben/stregsystemet) &nbsp;·&nbsp; [**F-Klub →**](https://fklub.dk) &nbsp;·&nbsp; [**Report a Bug →**](https://github.com/f-klubben/stregsystemet/issues)

<br/>

</div>

---

## What is StregPay?

StregPay is a native WooCommerce payment gateway plugin that connects F-Klub's online store to **Stregsystemet** — the club's internal credit (stregdollar) system. Members can pay for purchases using the same balance they use at the bar, without ever leaving the checkout page.

**No redirects. No third-party processors. Just stregdollars.**

```
  Member at checkout
        │
        ▼
  StregPay queries Stregsystemet API
        │
        ├── Balance ≥ order total? → Show StregPay as payment option
        │
        ▼
  Member confirms payment
        │
        ▼
  Stregsystemet deducts balance atomically
        │
        ▼
  WooCommerce order confirmed ✓
```

---

## Features

| | Feature | Description |
|---|---|---|
| ⚡ | **Live balance display** | Member's stregdollar balance shown inline at checkout before committing |
| 🛡️ | **Atomic transactions** | Balance deduction and order confirmation happen together — no partial states |
| 🔄 | **Auto-refund support** | Refunds return stregdollars directly to the member's account |
| 🎛️ | **WP Admin config** | Full settings panel in *WooCommerce → Settings → Payments* |
| 🔒 | **Member-gated** | Only members with sufficient balance can use the gateway |
| 🐳 | **Hot-reload dev loop** | File watcher auto-builds and syncs to Docker on every save |

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.0 |
| WordPress | ≥ 6.x |
| WooCommerce | ≥ 7.x |
| Stregsystemet API | ≥ v2.6 |
| Node.js *(build only)* | ≥ 18 |
| Docker *(dev only)* | any recent |

---

## Quick Start

### 1 — Install build dependencies

```bash
npm install
```

### 2 — Start the Docker environment

Spins up WordPress, WooCommerce, MySQL, and a local Stregsystemet instance.

```bash
docker-compose up -d
```

### 3 — Install the plugin

Copy the plugin into the running container and fix ownership.

```bash
sh update-container.sh
```

### 4 — Open WordPress

Navigate to **http://localhost:8080** and activate StregPay from *WooCommerce → Settings → Payments*.

---

## Development Workflow

### Automated — file watcher *(recommended)*

Watches for file changes, auto-rebuilds assets, and syncs to the container.

```bash
npm run dev-watch
# Auto-rebuilds on changes
# Auto-updates plugin in the container
# Press Ctrl+C to stop
```

### Manual — explicit rebuild & sync

```bash
# 1. Compile JS + CSS assets
npm run build

# 2. Push changes to the container
sh update-container.sh
```

> **Note:** PHP changes are synced by the watcher automatically.
> JavaScript and CSS changes always require `npm run build` first.
> Container sync takes approximately 3–5 seconds.

---

## Docker Commands

| Action | Command |
|---|---|
| Start containers | `docker-compose up -d` |
| Stop containers | `docker-compose down` |
| View logs | `docker-compose logs -f` |
| Restart containers | `docker-compose restart` |
| Admin shell | `docker exec -u root <container> bash` |

---

## Troubleshooting

**Plugin not showing in WooCommerce?**
Re-run the install step from Quick Start — the copy command must be run again after any container restart.

**Changes not reflecting in the browser?**
Clear your browser cache and flush WooCommerce transients from *WooCommerce → System Status → Tools*.

**Permission errors on container copy?**
Use `docker exec -u root` for administrative commands that write outside the `www-data` scope.

---

## Project Structure

```
stregsystem-payment-gateway/
├── src/                  # Source JS + CSS (compiled via npm run build)
├── assets/               # Compiled JS + CSS (do not edit directly)
├── includes/             # PHP gateway class and helpers
├── templates/            # WooCommerce checkout templates
├── stregpay-checkout.php # Plugin entry point
├── docker-compose.yml    # Dev environment
├── update-container.sh   # Container sync helper
└── package.json          # Build toolchain
```

---

## Related Projects

- [**stregsystemet**](https://github.com/f-klubben/stregsystemet) — The F-Klub stregdollar system (Django) that StregPay integrates with
- [**fklub.dk**](https://fklub.dk) — The F-Klub club website running WooCommerce + StregPay

---

## License

LPW © F-Klub — [fklub.dk](https://fklub.dk)

---

<div align="center">

Made with ☕ and stregdollars by the members of **F-Klub**, Aalborg University

[fklub.dk](https://fklub.dk) &nbsp;·&nbsp; [stregsystemet](https://github.com/f-klubben/stregsystemet)

</div>