FROM php:8.4-cli AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install zip intl pcntl mbstring gd pdo pdo_pgsql pgsql \
    && pecl install apcu && docker-php-ext-enable apcu \
    && pecl install pcov && docker-php-ext-enable pcov \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Copy backend code
COPY . .

# Install PHP dependencies without dev packages for production
# --no-scripts: .env is excluded by .dockerignore, so post-install scripts (cache:clear) would fail
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts

# Production stage
FROM php:8.4-fpm

# Install production dependencies (no pcov — that's a dev/test tool)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install zip intl pcntl mbstring gd pdo pdo_pgsql pgsql \
    && pecl install apcu && docker-php-ext-enable apcu \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /app

WORKDIR /app

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
