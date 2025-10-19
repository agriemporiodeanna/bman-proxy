# Dockerfile
FROM php:8.2-cli

# Install optional extensions (curl is included by default)
RUN docker-php-ext-install pcntl || true

WORKDIR /app
COPY public/ ./public/

# Start PHP built-in server on the PORT provided by Render
CMD php -S 0.0.0.0:${PORT} -t public
