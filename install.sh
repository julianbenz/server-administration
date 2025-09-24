#!/bin/bash


echo "Starting installation..."
read -p "Sollte der Webserver mit Domain installiert werden? (y/n) " install_webserver

if [[ "$install_webserver" == "y" ]]; then
    read -p "Bitte geben Sie die Domain ein (z.B. example.com): " domain
    read -p "Bitte geben Sie die E-Mail-Adresse für SSL-Zertifikate ein: " email
fi

read -p "Sollte die Datenbank installiert werden? (y/n) " install_database
if [[ "$install_database" == "y" ]]; then
    read -p "Bitte geben Sie den Datenbanknamen ein: " db_name
    read -p "Bitte geben Sie den Datenbankbenutzernamen ein: " db_user
    read -sp "Bitte geben Sie das Datenbankpasswort ein: " db_pass
    echo
fi

echo "Aktualisiere Paketlisten..."
sudo apt update 
sudo apt upgrade -y

if [[ "$install_webserver" == "y" ]]; then
    echo "Installiere Nginx..."
    sudo apt install nginx -y
    echo "Starte und aktiviere Nginx..."
    sudo systemctl start nginx
    sudo systemctl enable nginx

    echo "Installiere Certbot für SSL-Zertifikate..."
    sudo apt install certbot python3-certbot-nginx -y

    echo "Konfiguriere Nginx für die Domain $domain..."
    sudo tee /etc/nginx/sites-available/$domain > /dev/null <<EOL
server {
    listen 80; 
    server_name $domain www.$domain;
    root /var/www/$domain/html;
    index index.html index.htm index.php;
    location / {
        try_files \$uri \$uri/ =404;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
    location ~ /\.ht {
        deny all;
    }
}
EOL 
    sudo mkdir -p /var/www/$domain/html
    sudo chown -R $USER:$USER /var/www/$domain/html
    sudo chmod -R 755 /var/www/$domain

    echo "<html><head><title>Welcome to $domain!</title></head><body><h1>Success! The $domain server block is working!</h1></body></html>" | sudo tee /var/www/$domain/html/index.html

    sudo ln -s /etc/nginx/sites-available/$domain /etc/nginx/sites-enabled/
    sudo nginx -t
    sudo systemctl reload nginx

    echo "Fordere SSL-Zertifikat für $domain an..."
    sudo certbot --nginx -d $domain -d www.$domain --non-interactive --agree-tos -m $email
fi

if [[ "$install_database" == "y" ]]; then
    echo "Installiere MySQL..."
    sudo apt install mysql-server -y
    echo "Starte und aktiviere MySQL..."
    sudo systemctl start mysql
    sudo systemctl enable mysql


echo "Die Webseite ist nun unter http://$domain erreichbar."
    echo "Die Datenbank $db_name wurde mit dem Benutzer $db_user erstellt."
    echo "Installation abgeschlossen."
