#!/bin/bash

echo "Updating Wordpress container..."

docker exec stregpay-checkout-wordpress-1 rm -rf /var/www/html/wp-content/plugins/stregpay-checkout
docker exec stregpay-checkout-wordpress-1 touch /var/www/html/wp-content/plugins/stregpay-checkout
docker cp build stregpay-checkout-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout/build
docker cp stregpay-checkout.php stregpay-checkout-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout
docker cp blocks-integration.php stregpay-checkout-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout
docker cp payment-gateway-integration.php stregpay-checkout-wordpress-1:/var/www/html/wp-content/plugins/stregpay-checkout
docker exec stregpay-checkout-wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/plugins/stregpay-checkout
echo "Finished updating container"
