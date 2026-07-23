#!/bin/sh

VER="5.0.0"

# Remove installed packages
rm -rf vendor composer.lock

# Now, exit on errors
set -e

# Install dependencies
composer install --no-dev

# Copy files to temp dir
if type robocopy > /dev/null; then
    # Absolute source path so that "src" can be anchored to the repo root only
    # (a bare "-XD src" would also exclude vendor/.../src and break the autoloader).
    ROOT=$(pwd -W 2>/dev/null || pwd)
    ROOT=$(printf '%s' "$ROOT" | tr '/' '\134')
    robocopy "$ROOT" woocommerce-mobbex -MIR -XD node_modules .git .vscode .github .claude "$ROOT\\src" woocommerce-mobbex -XF .gitignore build.sh readme.md *.zip .env cli.sh compose.yml package.json package-lock.json webpack.config.js composer.lock  || rc=$?
    if [ "${rc:-0}" -ge 8 ]; then
        exit $rc
    fi
elif type rsync > /dev/null; then
    rsync -r --exclude={'.git','.vscode','.github','.claude','/src','woocommerce-mobbex','node_modules','.gitignore','build.sh','readme.md','*.zip','.env','cli.sh','compose.yml','package.json','package-lock.json','webpack.config.js','composer.lock'} . ./woocommerce-mobbex
fi

# Compress
if type 7z > /dev/null; then
    7z a -tzip "wc-mobbex.$VER.zip" woocommerce-mobbex
elif type zip > /dev/null; then
    zip -r wc-mobbex.$VER.zip woocommerce-mobbex
else
    echo "Compress bin not found"
fi

# Remove temp dir
rm -r ./woocommerce-mobbex vendor composer.lock
