#!/bin/bash

set -e

service memcached restart

while true; do
    sleep 5

    service memcached status
done
