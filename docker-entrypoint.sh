#!/bin/sh
env | sed -e 's/=/="/' -e 's/$/"/' > /var/www/.env
cat /var/www/.env.example >> /var/www/.env
echo $SAML_KEY_BASE64 | base64 -d > /var/www/storage/app/samlidp/key.pem
echo $SAML_CERT_BASE64 | base64 -d > /var/www/storage/app/samlidp/cert.pem
docker-php-entrypoint apache2-foreground