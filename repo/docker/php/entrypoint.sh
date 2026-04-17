#!/bin/sh
set -e

# Generate cryptographically unique secrets at container startup when the
# operator has not injected them. Values are scoped to this container
# lifetime; set them explicitly in the environment for persistence across
# restarts or when encrypted data must survive a redeploy.
if [ -z "${PANTRYPILOT_GATEWAY_HMAC_SECRET}" ]; then
    PANTRYPILOT_GATEWAY_HMAC_SECRET="$(openssl rand -hex 32)"
    export PANTRYPILOT_GATEWAY_HMAC_SECRET
fi

if [ -z "${PANTRYPILOT_CRYPTO_KEY}" ]; then
    PANTRYPILOT_CRYPTO_KEY="$(openssl rand -hex 32)"
    export PANTRYPILOT_CRYPTO_KEY
fi

if [ -z "${PANTRYPILOT_CRYPTO_IV}" ]; then
    PANTRYPILOT_CRYPTO_IV="$(openssl rand -hex 8)"
    export PANTRYPILOT_CRYPTO_IV
fi

# Write resolved secrets to a runtime env file so docker-exec sessions can
# source them — exec processes don't inherit exports from PID 1.
printf 'PANTRYPILOT_GATEWAY_HMAC_SECRET=%s\nPANTRYPILOT_CRYPTO_KEY=%s\nPANTRYPILOT_CRYPTO_IV=%s\n' \
    "$PANTRYPILOT_GATEWAY_HMAC_SECRET" \
    "$PANTRYPILOT_CRYPTO_KEY" \
    "$PANTRYPILOT_CRYPTO_IV" \
    > /run/pantrypilot-runtime.env
chmod 644 /run/pantrypilot-runtime.env

exec "$@"
