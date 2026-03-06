FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    libsqlite3-dev \
    sqlite3 \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_sqlite curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Nginx config
RUN echo 'server { \
    listen 80; \
    root /var/www/html; \
    index index.php; \
    location / { try_files $uri $uri/ /index.php?$query_string; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
    } \
}' > /etc/nginx/sites-available/default

# Start script — dùng printf để \n hoạt động đúng
RUN printf '#!/bin/bash\nphp-fpm -D\nnginx -g "daemon off;"\n' > /start.sh && chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
