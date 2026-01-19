# FrankenPHP Static Build Dockerfile for harphp
# Produces a standalone CLI executable with embedded PHP runtime

ARG BUILDER_IMAGE=dunglas/frankenphp:static-builder

FROM --platform=$BUILDPLATFORM ${BUILDER_IMAGE}

# Set working directory for the app
WORKDIR /go/src/app/dist/app

# Copy the application source
COPY composer.json composer.lock ./
COPY bin/ bin/
COPY src/ src/

# Install production dependencies only
RUN composer install \
    --ignore-platform-reqs \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --classmap-authoritative

# Switch to the FrankenPHP source directory
WORKDIR /go/src/app

# Configure the build
# Minimal PHP extensions needed for a CLI tool
ENV PHP_EXTENSIONS="ctype,iconv,mbstring,phar,posix,tokenizer"

# Point to our embedded application
ENV EMBED="/go/src/app/dist/app/"

# Skip Caddy modules we don't need for CLI-only usage
ENV XCADDY_ARGS=""

# Ignore platform requirements for the builder's internal tooling
ENV COMPOSER_FLAGS="--ignore-platform-reqs"

# Build the static binary
RUN EMBED="${EMBED}" \
    PHP_EXTENSIONS="${PHP_EXTENSIONS}" \
    COMPOSER_FLAGS="${COMPOSER_FLAGS}" \
    ./build-static.sh
