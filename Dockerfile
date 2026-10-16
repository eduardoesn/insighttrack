
FROM php:8.4-apache

# pdo_pgsql conecta ao banco e mbstring trata nomes acentuados nos formulários.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY app/ /var/www/app/
COPY public/ /var/www/html/
COPY tests/ /var/www/tests/
