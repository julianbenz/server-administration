#!/usr/bin/env bash
set -euo pipefail

if [[ $(id -u) -ne 0 ]]; then
    echo "This installer must be run as root." >&2
    exit 1
fi

APP_DIR="/var/www/server-admin"
APACHE_SITE="server-admin"

apt-get update
apt-get install -y apache2 php libapache2-mod-php docker.io docker-compose-plugin rsync

systemctl enable --now docker
systemctl enable --now apache2

mkdir -p "${APP_DIR}"
rsync -a --delete --exclude='.git' --exclude='install.sh' "$(pwd)/" "${APP_DIR}/"
chown -R www-data:www-data "${APP_DIR}"

cat <<APACHECONF >/etc/apache2/sites-available/${APACHE_SITE}.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${APACHE_SITE}_error.log
    CustomLog \${APACHE_LOG_DIR}/${APACHE_SITE}_access.log combined
</VirtualHost>
APACHECONF

a2ensite ${APACHE_SITE}.conf >/dev/null
if a2query -s 000-default.conf >/dev/null 2>&1; then
    a2dissite 000-default.conf >/dev/null || true
fi
systemctl reload apache2

echo "Installation complete. Access the dashboard via http://<server-ip>/"
