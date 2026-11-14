FROM php:8.2-apache

# Cài đặt sqlite3, pdo_sqlite và các tiện ích
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Cài đặt Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy source code
COPY . /var/www/html/

# Phân quyền ghi cho database và uploads
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/Database /var/www/html/Assets/Uploads

# Script entrypoint khởi động cả WebSocket Server và Apache
RUN echo '#!/bin/bash\nexport APP_KEY="genk-secret-key-at-least-32-chars-long-2026"\nphp bin/websocket-server.php &\napache2-foreground' > /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 8081

CMD ["/usr/local/bin/entrypoint.sh"]
