#!/bin/bash
#
# Facebook CTF: script to start tests and code coverage
#
# Usage: ./run_tests.sh [path_to_code]
#

set -e

DB="fbctftests"
CODE_PATH=${1:-/vagrant}
DB_USER=${2:-root}
DB_PWD=${3:-root}

echo "[+] Starting tests setup in $CODE_PATH"

mysql -u "$DB_USER" --password="$DB_PWD" -e "CREATE DATABASE $DB;"
mysql -u "$DB_USER" --password="$DB_PWD" -e "FLUSH PRIVILEGES;"
mysql -u "$DB_USER" --password="$DB_PWD" "$DB" -e "source $CODE_PATH/database/test_schema.sql;"
mysql -u "$DB_USER" --password="$DB_PWD" "$DB" -e "source $CODE_PATH/database/logos.sql;"
mysql -u "$DB_USER" --password="$DB_PWD" "$DB" -e "source $CODE_PATH/database/countries.sql;"

echo "[+] DB Connection file"
cat "$CODE_PATH/extra/settings.ini.example" | sed "s/DATABASE/$DB/g" | sed "s/MYUSER/$DB_USER/g" | sed "s/MYPWD/$DB_PWD/g" > "$CODE_PATH/settings.ini"

echo "[+] Starting tests"
hhvm vendor/phpunit/phpunit/phpunit tests

echo "[+] Deleting test database"
mysql -u "$DB_USER" --password="$DB_PWD" -e "DROP DATABASE IF EXISTS $DB;"
mysql -u "$DB_USER" --password="$DB_PWD" -e "FLUSH PRIVILEGES;"
