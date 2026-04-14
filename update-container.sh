#!/bin/bash

echo "Updating Wordpress container..."

docker exec stregsystem-payment-gateway-wordpress-1 rm -rf /var/www/html/wp-content/plugins/stregpay-checkout
docker exec stregsystem-payment-gateway-wordpress-1 mkdir /var/www/html/wp-content/plugins/stregpay-checkout
docker cp build stregsystem-payment-gateway-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout/build
docker cp stregpay-checkout.php stregsystem-payment-gateway-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout
docker cp blocks-integration.php stregsystem-payment-gateway-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout
docker cp payment-gateway-integration.php stregsystem-payment-gateway-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout
docker exec stregsystem-payment-gateway-wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/plugins/stregpay-checkout
echo "Finished updating container"
