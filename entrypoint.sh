#!/bin/bash

# 設定キャッシュのクリア
php artisan config:clear

# マイグレーションの実行
php artisan migrate --force

# Supervisorの起動
/usr/bin/supervisord -n
