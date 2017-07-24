#!/bin/bash

set -e

service mysql restart

while true; do
    sleep 5
    service mysql status
done
