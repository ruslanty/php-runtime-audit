FROM php:8.5.12-cli-alpine AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-autoloader
COPY bin ./bin
COPY src ./src
COPY data ./data
RUN composer dump-autoload --no-dev --classmap-authoritative

ENTRYPOINT ["php", "/app/bin/php-runtime-audit"]
CMD ["scan", "/scan"]
