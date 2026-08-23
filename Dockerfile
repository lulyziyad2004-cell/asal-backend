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
    && docker-php-ext-install \
    pdo \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && rm -rf /var/lib/apt/lists/*

RUN printf '%s\n' \
    'upload_max_filesize=50M' \
    'post_max_size=60M' \
    'max_execution_time=300' \
    'max_input_time=300' \
    'memory_limit=256M' \
    > /usr/local/etc/php/conf.d/uploads.ini

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin \
    --filename=composer

COPY composer.json composer.lock ./

RUN composer update league/flysystem-aws-s3-v3 \
    --with-dependencies \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --ignore-platform-req=ext-zip

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-req=ext-zip

COPY . .

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database

RUN chmod -R 775 storage bootstrap/cache database

RUN php artisan config:clear || true
RUN php artisan cache:clear || true

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
