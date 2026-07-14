#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/opt/disparador-app"
LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/deploy.log"
LOCK_FILE="/tmp/disparador-deploy.lock"

mkdir -p "$LOG_DIR"

exec 9>"$LOCK_FILE"

if ! flock -n 9; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') deploy já em execução" >> "$LOG_FILE"
    exit 1
fi

{
    echo "=================================================="
    echo "Início: $(date '+%Y-%m-%d %H:%M:%S')"

    cd "$APP_DIR"

    if [ -n "$(git status --porcelain)" ]; then
        echo "ERRO: working tree com alterações locais."
        git status --short
        exit 1
    fi

    git fetch origin
    git remote update
    git checkout main
    git reset --hard origin/main

    php -l index.php
    php -l config/config.php
    php -l public/webhook/meta.php

    curl -fsS https://disparador.net/ >/dev/null
    echo "Commit publicado: $(git rev-parse --short HEAD)"
    echo "Fim: $(date '+%Y-%m-%d %H:%M:%S')"
} >> "$LOG_FILE" 2>&1
