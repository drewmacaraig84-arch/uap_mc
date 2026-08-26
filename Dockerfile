# ==============================================================================
# Multi-Stage Dockerfile for UAP Mindoro Chapter (PHP Backend + React Frontend)
# Optimized for Railway, Render, Fly.io, or standard Docker deployments
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Build the React 18 + Vite Frontend
# ------------------------------------------------------------------------------
FROM node:20-alpine AS frontend-builder
WORKDIR /app/website

# Copy package descriptors first to leverage Docker layer caching
COPY website/package*.json ./
RUN npm install

# Copy frontend source and compile production bundle
COPY website/ ./
RUN npm run build

# ------------------------------------------------------------------------------
# Stage 2: Production PHP + Apache Web Server
# ------------------------------------------------------------------------------
FROM php:8.2-apache

# Install system dependencies and PHP extensions (PDO, MySQL, GD, Zip)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli gd zip \
    && a2dismod mpm_prefork mpm_worker mpm_event 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache VirtualHost with AllowOverride All and ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -ri -e 's!/var/www/html!/var/www/html!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html!g' /etc/apache2/apache2.conf \
    && echo "<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/override.conf \
    && a2enconf override

WORKDIR /var/www/html

# Copy the entire PHP application into the container
COPY . /var/www/html/

# Copy compiled React frontend assets over the web root (index.html, assets, logo)
COPY --from=frontend-builder /app/website/dist/ /var/www/html/

# Ensure entrypoint script is executable and uploads folder is writable
RUN chmod +x /var/www/html/docker-entrypoint.sh \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

# Expose default HTTP port
EXPOSE 80

# Run entrypoint script for dynamic Railway port binding & database setup
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]

