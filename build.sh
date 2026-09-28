#!/bin/sh

# Version is read from the plugin header, never hardcoded here. WordPress parses
# that header statically, so it is the one copy of the version that cannot be
# derived from anywhere else.
VER=$(sed -n 's/^Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' mobbex-for-woocommerce.php)

if [ -z "$VER" ]; then
    echo "error: could not read Version from mobbex-for-woocommerce.php" >&2
    exit 1
fi

# MOBBEX_VERSION is what the integrity attestation sends, and what the server
# uses to look up the artifact it compares the install against. Publishing it
# out of step with the header would ship a release that fails its own check.
DEFINED_VER=$(sed -n "s/.*MOBBEX_VERSION'[[:space:]]*,[[:space:]]*'\([^']*\)'.*/\1/p" utils/defines.php)

if [ "$VER" != "$DEFINED_VER" ]; then
    echo "error: version mismatch: plugin header says '$VER', MOBBEX_VERSION says '$DEFINED_VER'." >&2
    echo "       Both must equal the release tag." >&2
    exit 1
fi

echo "Building mobbex $VER"

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

echo
echo "Built wc-mobbex.$VER.zip"
echo "Publish it as the asset of tag $VER."
