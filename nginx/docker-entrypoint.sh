#!/bin/bash

if [ ! -f /etc/nginx/certs/dhparam.pem ]; then
    bash /etc/nginx/certs/dev-certs.sh
fi

exec "$@"
