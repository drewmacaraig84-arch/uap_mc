#!/bin/bash
set -e

# Configure dynamic port from Railway ($PORT) or fallback to 80
PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${PORT}..."
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Ensure upload directory exists and is writable by www-data
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# Run database setup & migrations if MySQL is reachable
echo "Running database initialization and migrations..."
php /var/www/html/database/setup.php 2>&1 || echo "Notice: Database connection not ready yet or migrations deferred."

# Execute Apache in foreground
echo "Starting Apache on port ${PORT}..."
exec apache2-foreground
