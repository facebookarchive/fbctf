#!/bin/bash

set -e

service mysql restart
myisamchk --safe-recover /var/lib/mysql/*/*.MYI

while true; do
    sleep 5
    service mysql status
done