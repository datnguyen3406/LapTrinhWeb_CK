FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_sqlite \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

EXPOSE 8000
