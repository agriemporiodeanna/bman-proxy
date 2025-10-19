# Dockerfile
FROM php:8.2-cli
WORKDIR /app
COPY public/ ./public/
CMD php -S 0.0.0.0:${PORT} -t public
