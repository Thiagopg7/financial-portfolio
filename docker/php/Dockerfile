FROM php:8.5-fpm

# UID/GID do usuário do host, para casar permissões do bind mount.
ARG UID=1000
ARG GID=1000

# libonig-dev é exigida pela extensão mbstring desde o PHP 8.4.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        ca-certificates \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# bcmath: precisão arbitrária para valores financeiros.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        exif \
        gd \
        pcntl

# pcov: driver de cobertura de testes (leve, só para esse fim). As build deps são
# temporárias: instaladas para compilar a extensão e removidas em seguida.
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# Node precisa estar nesta imagem porque o Vite (plugin Wayfinder) executa
# `php artisan wayfinder:generate` ao iniciar e depende do binário PHP. Copiamos
# de uma imagem oficial (mesma distro) em vez de baixar/executar script remoto.
COPY --from=node:22-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g "$GID" appuser \
    && useradd -u "$UID" -g appuser -m -s /bin/bash appuser

WORKDIR /var/www/html

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
