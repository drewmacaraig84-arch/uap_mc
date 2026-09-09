#!/bin/bash
set -e

# Configure dynamic port from Railway ($PORT) or fallback to 80
PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${PORT}..."
sed -i -E "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:([0-9]+)>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure exactly one MPM (mpm_prefork) is enabled for mod_php to prevent AH00534
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load 2>/dev/null || true
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf 2>/dev/null || true

# Fallback copy of config.php if not present in container
if [ ! -f /var/www/html/includes/config.php ] && [ -f /var/www/html/includes/config.example.php ]; then
    echo "Creating includes/config.php from template..."
    cp /var/www/html/includes/config.example.php /var/www/html/includes/config.php
fi

# Auto-bridge if a volume was mounted at /uploads or /data instead of /var/www/html/uploads
for ALT_DIR in /uploads /data; do
    if [ -d "$ALT_DIR" ] && [ "$ALT_DIR" != "/var/www/html/uploads" ] && [ ! -L "/var/www/html/uploads" ]; then
        if grep -qs " $ALT_DIR " /proc/mounts || [ "$(ls -A "$ALT_DIR" 2>/dev/null)" ]; then
            echo "Notice: Volume mount detected at ${ALT_DIR}. Bridging to /var/www/html/uploads..."
            if [ -d /var/www/html/uploads ] && [ ! -L /var/www/html/uploads ]; then
                cp -rn /var/www/html/uploads/* "$ALT_DIR/" 2>/dev/null || true
                rm -rf /var/www/html/uploads
            fi
            ln -sfn "$ALT_DIR" /var/www/html/uploads
            break
        fi
    fi
done

# Ensure storage directories exist and have proper permissions
mkdir -p /var/www/html/uploads/qr_codes \
         /var/www/html/uploads/avatars \
         /var/www/html/uploads/members \
         /var/www/html/uploads/sponsors \
         /var/www/html/uploads/proofs \
         /var/www/html/uploads/receipts \
         /var/www/html/uploads/news \
         /var/www/html/uploads/home_images \
         /var/www/html/uploads/header_badges \
         /var/www/html/receipts \
         /var/www/html/public

# If Railway mounted an empty or fresh volume, restore default seed assets (QR codes, logos, sample avatars)
if [ -d /var/www/html/seed_assets/uploads ]; then
    echo "Restoring and syncing default media assets into uploads volume..."
    cp -rn /var/www/html/seed_assets/uploads/* /var/www/html/uploads/ 2>/dev/null || true
fi

# Ensure default logo is present in public/ and uploads/
if [ -f /var/www/html/seed_assets/public/logo.jpg ] && [ ! -f /var/www/html/public/logo.jpg ]; then
    cp /var/www/html/seed_assets/public/logo.jpg /var/www/html/public/logo.jpg 2>/dev/null || true
fi

# Ensure QR codes exist in both uploads/ and uploads/qr_codes/
if [ -d /var/www/html/uploads/qr_codes ]; then
    cp -rn /var/www/html/uploads/qr_codes/* /var/www/html/uploads/ 2>/dev/null || true
    cp -rn /var/www/html/uploads/qr_* /var/www/html/uploads/qr_codes/ 2>/dev/null || true
fi

# Set ownership and read/write permissions for Apache www-data
chown -R www-data:www-data /var/www/html/uploads /var/www/html/receipts /var/www/html/public 2>/dev/null || true
[ -d /uploads ] && chown -R www-data:www-data /uploads 2>/dev/null || true
chmod -R 775 /var/www/html/uploads /var/www/html/receipts /var/www/html/public 2>/dev/null || true
[ -d /uploads ] && chmod -R 775 /uploads 2>/dev/null || true

# Run database setup & migrations if database is reachable
echo "Running database initialization and migrations..."
php /var/www/html/database/setup.php || echo "Notice: Database connection not ready yet or migrations deferred."

# Execute Apache in foreground
echo "Starting Apache on port ${PORT}..."
exec apache2-foreground
