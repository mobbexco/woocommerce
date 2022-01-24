set ver="3.6.3"

:: Create directory with plugin files
robocopy . woocommerce-mobbex /MIR /XD .git .vscode woocommerce-mobbex /XF .gitignore build.bat readme.md *.zip

:: Compress archive
7z a -tzip wc-mobbex.%ver%.zip woocommerce-mobbex

:: Delete directory
rd /s /q woocommerce-mobbex