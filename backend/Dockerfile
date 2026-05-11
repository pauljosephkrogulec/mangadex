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
    && pecl install pcov && docker-php-ext-enable pcov

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Copy backend code
COPY . .

# Install PHP dependencies (including dev for now, can use --no-dev in prod)
# --no-scripts: .env is excluded by .dockerignore, so post-install scripts (cache:clear) would fail
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Production stage
FROM php:8.4-fpm

# Install production dependencies
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
    && pecl install pcov && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /app
COPY --from=builder /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /app

EXPOSE 9000

CMD ["php-fpm"]
