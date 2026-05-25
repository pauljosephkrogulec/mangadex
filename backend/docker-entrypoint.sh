#!/bin/sh
set -e

if [ ! -f config/jwt/private.pem ]; then
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA \
        -out config/jwt/private.pem \
        -pkeyopt rsa_keygen_bits:4096 \
        -pass pass:"${JWT_PASSPHRASE}"
    openssl pkey \
        -in config/jwt/private.pem \
        -out config/jwt/public.pem \
        -pubout \
        -passin pass:"${JWT_PASSPHRASE}"
    echo "JWT keys generated."
fi

# Ensure www-data workers can read the keys
chmod 644 config/jwt/public.pem
chmod 640 config/jwt/private.pem
chown root:www-data config/jwt/private.pem

exec "$@"
