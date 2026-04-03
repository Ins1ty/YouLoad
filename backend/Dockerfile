FROM php:8.2-fpm-alpine

RUN apk add --no-cache ffmpeg yt-dlp curl git unzip redis

RUN docker-php-ext-install pdo

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan config:cache

RUN chmod +x yt-dlp.exe || true

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# For Railway:
# - Set PORT=8000 env variable
# - Add yt-dlp.exe to the container