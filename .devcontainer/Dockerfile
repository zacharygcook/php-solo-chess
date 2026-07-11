FROM composer:2.9.5@sha256:698d3801b2a622ace460c4743c781282fcbcb733a4cbf8b31c44731e846585e8 AS composer

FROM php:8.4.19-cli-bookworm@sha256:3ddb5a91b44a1c922538576d73a6e808fb2438d9d2d65b1fc3ffd55619fac2e3

RUN apt-get update \
    && apt-get install --yes --no-install-recommends curl git jq libxml2-dev nodejs npm unzip \
    && docker-php-ext-install xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /workspace

RUN git config --system --add safe.directory /workspace
