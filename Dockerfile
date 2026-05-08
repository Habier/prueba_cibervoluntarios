FROM composer:2 AS composer_bin

FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
    bcmath \
    pdo_pgsql \
    pgsql \
    intl \
    opcache \
    sockets \
    zip

COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . /app
COPY Caddyfile /etc/caddy/Caddyfile

RUN composer dump-autoload --optimize

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
