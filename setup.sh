#!/bin/bash
# First-time WordPress setup. Run from the directory with docker-compose.yml and .env.
set -euo pipefail

. ./.env
: "${WP_ADMIN_PASSWORD:?set WP_ADMIN_PASSWORD in .env}"

wp() { docker compose run --rm cli wp "$@"; }

if ! wp core is-installed 2>/dev/null; then
    wp core install \
        --url="${WP_URL:-https://shop.fklub.dk}" \
        --title="${WP_TITLE:-F-Klub Shop}" \
        --admin_user="${WP_ADMIN_USER:-admin}" \
        --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="${WP_ADMIN_EMAIL:-admin@fklub.dk}" \
        --skip-email
fi

wp plugin install woocommerce printful-shipping-for-woocommerce --activate
wp plugin activate stregsystem-payment-gateway
wp option update woocommerce_currency FFF
