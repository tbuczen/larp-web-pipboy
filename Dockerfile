# syntax=docker/dockerfile:1
FROM composer:2 AS composer

FROM php:8.4-cli-bookworm AS development

RUN --mount=type=secret,id=proxy_ca,required=false \
    if [ -s /run/secrets/proxy_ca ]; then \
        cp /run/secrets/proxy_ca /usr/local/share/ca-certificates/environment-proxy-ca.crt; \
        update-ca-certificates; \
    fi \
    && sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update -o APT::Update::Error-Mode=any \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libpq-dev libzip-dev \
    && docker-php-ext-install -j2 intl pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --create-home app \
    && mkdir -p /home/app/.composer/cache \
    && chown -R app:app /home/app/.composer

WORKDIR /app
ENV COMPOSER_HOME=/home/app/.composer
USER app

EXPOSE 8000
CMD ["bash", "scripts/serve", "0.0.0.0:8000"]
