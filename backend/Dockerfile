# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
    pcntl \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY deploy/Caddyfile.api /etc/caddy/Caddyfile
COPY deploy/backend-entrypoint.sh /usr/local/bin/backend-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/backend-entrypoint.sh \
    && chmod +x /usr/local/bin/backend-entrypoint.sh

ENV SERVER_NAME=:80
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/backend-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
