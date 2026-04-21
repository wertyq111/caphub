FROM node:20-bookworm-slim AS copilot-cli

RUN npm install -g @github/copilot@1.0.32

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        libzip-dev \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        zip \
        unzip \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql zip opcache gd \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/caphub.conf \
    && a2enconf caphub \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=copilot-cli /usr/local/bin/node /usr/local/bin/node
COPY --from=copilot-cli /usr/local/bin/copilot /usr/local/bin/copilot
COPY --from=copilot-cli /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY docker/apache-entrypoint.sh /usr/local/bin/apache-entrypoint

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/apache-entrypoint \
    && chown -R www-data:www-data /var/www/html || true

ENTRYPOINT ["apache-entrypoint"]
CMD ["apache2-foreground"]
