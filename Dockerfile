FROM php:8.5.9-fpm-alpine

# The official PHP 8.5 FPM Alpine image already includes core DOM/XML and
# mbstring support. Rebuilding DOM against Alpine's system Lexbor can fail
# when the distro Lexbor API lags the PHP source bundled in the image.
# Only compile the extensions this CMS actually needs in addition to the base.
RUN apk add --no-cache icu-dev libzip-dev freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev libavif-dev aom-dev dav1d-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql zip

WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/99-talvoro.ini
COPY . /var/www/html

RUN mkdir -p storage/cache storage/logs storage/sessions storage/theme-imports public/uploads/themes public/uploads/site \
    && chown -R www-data:www-data storage public/uploads

EXPOSE 9000
# Re-create writable runtime directories after bind mounts are attached.
CMD ["sh", "-c", "mkdir -p storage/cache storage/logs storage/sessions storage/theme-imports public/uploads/themes public/uploads/site && chown -R www-data:www-data storage public/uploads && exec php-fpm"]
