#!/bin/sh

VER="3.19.1"

#Compile scripts
npm run build

# Copy files to temp dir
if type robocopy > /dev/null; then
    robocopy . woocommerce-mobbex -MIR -XD .git .vscode woocommerce-mobbex node_modules -XF .gitignore build.sh readme.md *.zip
elif type rsync > /dev/null; then
    rsync -r --exclude={'.git','.vscode','woocommerce-mobbex','node_modules','.gitignore','build.sh','readme.md','*.zip'} . ./woocommerce-mobbex
fi

# Compress
if type 7z > /dev/null; then
    7z a -tzip "wc-mobbex.$VER.zip" woocommerce-mobbex
elif type zip > /dev/null; then
    zip wc-mobbex.$VER.zip -r woocommerce-mobbex
fi

# Remove temp dir
rm -r ./woocommerce-mobbex