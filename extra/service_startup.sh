#!/bin/bash

set -e

if [[ -e /root/tmp/certbot.sh ]]; then
    /bin/bash /root/tmp/certbot.sh
fi

if [[ -e /var/run/hhvm/sock ]]; then
    rm -f /var/run/hhvm/sock
fi

chown -R mysql:mysql /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld
chown -R mysql:mysql /var/log/mysql
chown -R www-data:www-data /var/www/fbctf

sudo -u www-data service hhvm restart
service nginx restart
service mysql restart
service memcached restart

while true; do
    if [[ -e /var/run/hhvm/sock ]]; then
        chown www-data:www-data /var/run/hhvm/sock
    fi

    sleep 5

    service hhvm status
    service nginx status
    service mysql status
    service memcached status
done
