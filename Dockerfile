# Laravel API image. One image runs the web server, the queue worker or the scheduler
# (CONTAINER_ROLE, see docker/start.sh):
#   - production: docker-compose.prod.yml (MySQL, persistent uploads, worker and scheduler)
#   - free public demo: render.yaml (DEMO_MODE=true, SQLite re-seeded on every start)
FROM php:8.2-apache

# OPcache (the code is baked into the image, so no timestamp checks), the MySQL driver, pcntl so
# queue:work can stop a stuck job at its timeout, GD (JPEG/PNG/WebP) + EXIF so uploaded images are
# compressed to WebP (App\Support\ImageOptimizer), zip for Excel report export (App\Reports\XlsxWriter),
# and no PHP or Apache version in response headers.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install opcache pdo_mysql pcntl gd exif zip \
    && a2enmod rewrite headers \
    && printf '%s\n' 'ServerTokens Prod' 'ServerSignature Off' > /etc/apache2/conf-available/zz-hardening.conf \
    && a2enconf zz-hardening \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' expose_php=Off opcache.enable=1 opcache.memory_consumption=128 \
        opcache.max_accelerated_files=20000 opcache.validate_timestamps=0 > "$PHP_INI_DIR/conf.d/zz-app.ini"

# Serve /public and listen on $PORT (Apache expands ${...} from the environment at startup).
# Logs go to the container's output; a log file inside the container would vanish with it.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=10000 \
    LOG_CHANNEL=stderr
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so code-only changes reuse this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY docker/start.sh /usr/local/bin/start.sh
RUN sed -i 's/\r$//' /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 10000
CMD ["start.sh"]
