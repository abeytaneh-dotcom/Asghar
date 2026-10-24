FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg \
        libcurl4-openssl-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" curl exif gd mbstring pdo_mysql pdo_sqlite \
    && a2enmod rewrite headers expires deflate \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
COPY .user.ini /usr/local/etc/php/conf.d/zz-niazestan.ini
COPY railway-start.sh /usr/local/bin/railway-start

RUN chmod +x /usr/local/bin/railway-start \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["railway-start"]
