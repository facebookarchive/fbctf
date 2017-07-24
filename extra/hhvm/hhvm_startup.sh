#!/bin/bash

set -e

service hhvm restart

while true; do
    if [[ -e /var/run/hhvm/sock ]]; then
        chown www-data:www-data /var/run/hhvm/sock
    fi

    sleep 5

    service hhvm status
done
