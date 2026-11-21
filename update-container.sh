#!/bin/bash
set -euo pipefail

C=stregsystem-payment-gateway_wordpress_1
D=/var/www/html/wp-content/plugins/stregpay-checkout

podman start "$C"
podman exec "$C" rm -rf "$D"
podman exec "$C" mkdir -p "$D"
podman cp build stregpay-checkout.php blocks-integration.php payment-gateway-integration.php "$C:$D"
podman exec "$C" chown -R www-data:www-data "$D"
