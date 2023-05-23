
FROM composer:2 as builder
WORKDIR /app/
COPY . .
RUN composer install

FROM php:8.2-apache
WORKDIR /var/www/
RUN a2enmod rewrite
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=builder /app/vendor /var/www/vendor
RUN rm -rf html && ln -sf public/ html
ENTRYPOINT ["/var/www/docker-entrypoint.sh"]