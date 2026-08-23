FROM php:8.2-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install \
    pdo \
    pdo_sqlite \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin \
    --filename=composer

# نستخدم composer.json فقط ونتجاهل composer.lock التالف
COPY composer.json ./

RUN composer update \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-req=ext-zip

COPY . .

RUN mkdir -p \
    database \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

RUN touch database/database.sqlite

RUN chmod -R 775 storage bootstrap/cache database

RUN php artisan config:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && php artisan config:clear && php artisan route:clear && php artisan view:clear && php -d upload_max_filesize=50M -d post_max_size=60M -d max_execution_time=300 -d max_input_time=300 artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
