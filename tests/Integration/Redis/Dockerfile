ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli
ARG PHPREDIS_VERSION=6.2.0
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libxml2-dev \
    && for extension in mbstring dom xml xmlwriter; do \
        php -r "exit(extension_loaded('$extension') ? 0 : 1);" || docker-php-ext-install "$extension"; \
    done \
    && pecl install redis-${PHPREDIS_VERSION} \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
