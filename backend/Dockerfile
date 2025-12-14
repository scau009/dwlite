FROM dunglas/frankenphp:latest-php8.2

RUN install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    apcu \
    redis

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

COPY Caddyfile /etc/caddy/Caddyfile

RUN chown -R www-data:www-data /app/var

EXPOSE 8000

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
