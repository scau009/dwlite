# Use base image with pre-installed extensions
# Build base first: docker buildx build --platform linux/amd64 -t dwlite-php-base:latest -f Dockerfile.base .
ARG BASE_IMAGE=dwlite-php-base:latest
FROM ${BASE_IMAGE}

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

COPY Caddyfile /etc/caddy/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh && \
    mkdir -p /app/var/cache /app/var/log && \
    chown -R www-data:www-data /app/var

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
