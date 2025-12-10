FROM php:8.3-cli-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # PostgreSQL client and dev libraries
    libpq-dev \
    postgresql-client \
    # SSL support
    libssl-dev \
    # Image processing (GD extension)
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    # ZIP support
    libzip-dev \
    # Version control and package management
    git \
    unzip \
    curl \
    wget \
    # Optional: Development tools (comment out for production)
    vim \
    nano \
    net-tools \
    iputils-ping \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better layer caching)
COPY composer.json composer.lock* ./

# Install dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# Copy application code
COPY . .

# Regenerate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Create required directories
RUN mkdir -p logs

# Expose port (default 8000, override with docker run -p)
EXPOSE 8000

# Health check (optional - checks if server is responding)
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/api/health || exit 1

# Start PHP built-in server
# Note: Use 0.0.0.0 to allow external connections inside container
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
