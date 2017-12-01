#!/bin/bash

set -e

if [[ -e /root/tmp/certbot.sh ]]; then
    /bin/bash /root/tmp/certbot.sh
fi

chown -R www-data:www-data /var/www/fbctf
service nginx restart

while true; do
    sleep 5
    service nginx status
done
