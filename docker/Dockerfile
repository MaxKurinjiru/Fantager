FROM node:24-alpine AS node

# Build stage for frontend assets
FROM node:24-alpine AS asset-builder
WORKDIR /app
COPY package*.json webpack.config.js postcss.config.cjs tailwind.config.cjs ./
COPY assets/ ./assets/
COPY templates/ ./templates/
COPY translations/ ./translations/
RUN npm ci && npm run build

FROM alpine:3.23

ENV TZ=Europe/Prague

RUN apk update && apk upgrade \
    && apk add curl tzdata \
        php85 php85-apache2 php85-phar php85-mysqli php85-pdo php85-pdo_mysql \
        php85-gd php85-intl php85-mbstring php85-exif php85-bcmath php85-zip \
        php85-session php85-ctype php85-tokenizer php85-xml php85-dom \
        php85-curl php85-openssl php85-fileinfo php85-iconv php85-simplexml \
        php85-xmlwriter php85-common \
        apache2 apache2-proxy \
    && ln -sf /usr/bin/php85 /usr/local/bin/php \
    && mkdir -p /var/log/apache2 /run/apache2 /var/www/opcache \
    && sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf

# Copy Node.js from builder stage (before composer so it doesn't get overwritten)
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Install Composer after node copy
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY --link \
    --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

# PHP configuration
COPY docker/php/php.ini /etc/php85/php.ini

# Apache configuration
COPY docker/apache/localhost.conf /etc/apache2/conf.d/localhost.conf

WORKDIR /var/www/html

# Copy application code
COPY --chown=apache:apache . /var/www/html

# Copy pre-compiled frontend assets
COPY --chown=apache:apache --from=asset-builder /app/public/build /var/www/html/public/build

# Install dependencies for production
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Warm up Symfony cache for production
RUN mkdir -p var/cache var/log var/sessions var/share \
    && chown -R apache:apache var \
    && APP_ENV=prod php bin/console cache:warmup --env=prod \
    && chown -R apache:apache var

# Copy reusable entrypoint script and make it executable
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
