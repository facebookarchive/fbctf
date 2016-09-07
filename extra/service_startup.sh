#!/bin/bash

set -e

if [[ -e /root/tmp/certbot.sh ]]; then
    /bin/bash /root/tmp/certbot.sh
fi

service hhvm restart
service nginx restart
service mysql restart

chown www-data:www-data /var/run/hhvm/sock

while true; do
    if [[ -e /var/log/nginx/access.log ]]; then
        exec tail -F /var/log/nginx/access.log
    else
        exec sleep 10
    fi
done
