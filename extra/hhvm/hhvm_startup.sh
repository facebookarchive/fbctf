#!/bin/bash

set -e

chown -R www-data:www-data /var/www/fbctf
sudo -u www-data service hhvm restart

while true; do
    if [[ -e /var/run/hhvm/sock ]]; then
        chown www-data:www-data /var/run/hhvm/sock
    fi

    sleep 5

    service hhvm status
done
