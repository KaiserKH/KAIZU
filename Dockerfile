# ── Stage: Production PHP 8.2 + Apache ───────────────────────────────────────
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        default-mysql-client \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        zip \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# ── Configure Apache to listen on port 8080 (Railway requirement) ─────────────
RUN sed -i 's/Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' \
        /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides in document root
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
        /etc/apache2/apache2.conf

# Set document root to /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# ── Copy application source ────────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# Remove any local .env that may have been copied (use Railway env vars instead)
RUN rm -f .env

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Make assets/images writable for uploads
RUN mkdir -p assets/images && chmod -R 775 assets/images

# Expose Railway port
EXPOSE 8080

# ── Entrypoint: initialise DB then start Apache ───────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
