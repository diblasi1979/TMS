FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-install pdo_sqlite mbstring xml bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/start.sh /usr/local/bin/start-backend

RUN chmod +x /usr/local/bin/start-backend

WORKDIR /app

EXPOSE 8000

CMD ["/usr/local/bin/start-backend"]