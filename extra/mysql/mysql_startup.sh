#!/bin/bash

set -e

chown -R mysql:mysql /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld
chown -R mysql:mysql /var/log/mysql

service mysql restart

while true; do
    sleep 5
    service mysql status
done
