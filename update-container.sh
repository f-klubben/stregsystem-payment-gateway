#!/bin/bash

echo "Updating Wordpress container..."

alias podman=podman

podman start stregsystem-payment-gateway_wordpress_1

podman exec stregsystem-payment-gateway_wordpress_1 rm -rf /var/www/html/wp-content/plugins/stregpay-checkout
podman exec stregsystem-payment-gateway_wordpress_1 mkdir /var/www/html/wp-content/plugins/stregpay-checkout
podman cp build stregsystem-payment-gateway_wordpress_1:/var/www/html/wp-content/plugins/stregpay-checkout/build
podman cp stregpay-checkout.php stregsystem-payment-gateway_wordpress_1:/var/www/html/wp-content/plugins/stregpay-checkout
podman cp blocks-integration.php stregsystem-payment-gateway_wordpress_1:/var/www/html/wp-content/plugins/stregpay-checkout
podman cp payment-gateway-integration.php stregsystem-payment-gateway_wordpress_1:/var/www/html/wp-content/plugins/stregpay-checkout
podman exec stregsystem-payment-gateway_wordpress_1 chown -R www-data:www-data /var/www/html/wp-content/plugins/stregpay-checkout
echo "Finished updating container"
