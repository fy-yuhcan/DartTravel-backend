FROM php:8.2-fpm

# 作業ディレクトリを設定
WORKDIR /var/www/html

# パッケージのインストール
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_mysql zip

# Composerのインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# アプリケーションコードをコピー
COPY . .

# 依存関係のインストール
RUN composer install --no-dev --optimize-autoloader

# アプリケーションのキャッシュを生成
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Nginxの設定ファイルをコピー
COPY .docker/nginx/nginx.conf /etc/nginx/sites-available/default

# Supervisorの設定ファイルをコピー
COPY .docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# パーミッションの設定
RUN chown -R www-data:www-data storage bootstrap/cache

# ポートの公開(8080)
EXPOSE 8080

# 起動コマンド
CMD ["/usr/bin/supervisord"]
