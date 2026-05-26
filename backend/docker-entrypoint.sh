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

# Best-effort permission fix — may be skipped on bind-mounted volumes where host owns the files
chmod 644 config/jwt/public.pem 2>/dev/null || true
chmod 640 config/jwt/private.pem 2>/dev/null || true
chown root:www-data config/jwt/private.pem 2>/dev/null || true

exec "$@"
