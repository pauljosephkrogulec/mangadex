FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install zip intl pcntl \
    && pecl install apcu && docker-php-ext-enable apcu

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Copy backend code
COPY . .

# Install PHP dependencies including TwigBundle
RUN composer require symfony/twig-bundle --no-interaction && \
    composer install --no-interaction --no-dev --optimize-autoloader

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
