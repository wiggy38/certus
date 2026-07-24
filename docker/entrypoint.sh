#!/usr/bin/env bash
set -e

# Démarrer PHP-FPM en arrière-plan
php-fpm -D

# Démarrer Nginx au premier plan
nginx -g "daemon off;"
