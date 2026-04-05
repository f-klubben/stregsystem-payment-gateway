# Stregpay Checkout - Development Setup

## Quick Start

1. **Install Dependencies**:
   ```bash
   npm install
   ```

2. **Start Docker Containers**:
   ```bash
   docker-compose up -d
   ```

3. **Install Plugin in Container**:
   ```bash
   docker cp . stregpay-checkout-wordpress-1:/tmp/stregpay-checkout
   docker exec stregpay-checkout-wordpress-1 cp -r /tmp/stregpay-checkout/. /var/www/html/wp-content/plugins/stregpay-checkout/
   docker exec stregpay-checkout-wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/plugins/stregpay-checkout
   ```

4. **Access WordPress**:
   Open http://localhost:8080 in your browser.

## Development Workflow

### Manual Updates
- **Build Assets**:
  ```bash
  npm run build
  ```

- **Update Container**:
  ```bash
  docker cp build stregpay-checkout-wordpress-1:/tmp/stregpay-build
  docker exec stregpay-checkout-wordpress-1 rm -rf /var/www/html/wp-content/plugins/stregpay-checkout/build
  docker exec stregpay-checkout-wordpress-1 cp -r /tmp/stregpay-build/. /var/www/html/wp-content/plugins/stregpay-checkout/build/
  ```

### Automated Watching
- **Start Watcher**:
  ```bash
  npm run dev-watch
  ```
  - Watches `src/`, `includes/`, `stregpay-checkout.php`, `block.json`
  - Auto-rebuilds on changes
  - Auto-updates container
  - Press Ctrl+C to stop

## Docker Commands

- **Start Containers**: `docker-compose up -d`
- **Stop Containers**: `docker-compose down`
- **View Logs**: `docker-compose logs -f`
- **Restart Containers**: `docker-compose restart`

## Troubleshooting

- **Plugin not showing?** Run the install command again
- **Changes not reflecting?** Clear browser cache and WooCommerce transients
- **Permission issues?** Use `docker exec -u root` for administrative commands

## Notes

- PHP changes require manual copy or watcher
- JavaScript/CSS changes require rebuild (`npm run build`)
- Container updates take 3-5 seconds
- WordPress may cache plugin info - refresh or clear cache
