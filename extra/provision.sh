#!/bin/bash
#
# Facebook CTF: Provision script for vagrant dev environment
#
# Usage: ./provision.sh [dev | prod] [path_to_code] [path_to_destination]
#

# We want the provision script to fail as soon as there are any errors
set -e

DB="fbctf"
U="ctf"
P="ctf"
P_ROOT="root"
MODE=${1:-dev}
CODE_PATH=${2:-/vagrant}
CTF_PATH=${3:-/var/www/fbctf}

echo "[+] Provisioning in $MODE mode"

# We only create a new directory and rsync files over if it's different from the
# original code path
if [[ "$CODE_PATH" != "$CTF_PATH" ]]; then
    echo "[+] Creating code folder $CTF_PATH"
    [[ -d "$CTF_PATH" ]] || sudo mkdir -p "$CTF_PATH"

    echo "[+] Copying all CTF code to destination folder"
    sudo rsync -a --exclude node_modules --exclude vendor "$CODE_PATH/" "$CTF_PATH/"

    # This is because sync'ing files is done with unison
    if [[ "$MODE" == "dev" ]]; then
        echo "[+] Setting permissions"
        sudo chmod -R 777 "$CTF_PATH/"
    fi
fi

# There we go!
source "$CTF_PATH/extra/lib.sh"

# Ascii art is always appreciated
set_motd "$CTF_PATH"

# We only run this once so provisioning is faster
sudo apt-get update

# Some people need this language pack installed or HHVM will report errors
package language-pack-en

# Repos to be added in dev mode
if [[ "$MODE" = "dev" ]]; then
    repo_mycli
fi

# Packages to be installed in dev mode
if [[ "$MODE" = "dev" ]]; then
    package mycli
    package emacs
    package htop
fi

# Install memcached
package memcached

# Install MySQL
install_mysql "$P_ROOT"

# Install git
package git

# Install HHVM
install_hhvm "$CTF_PATH"

# Install Composer
install_composer "$CTF_PATH"
composer.phar install

# Install NPM and grunt
package npm
package nodejs-legacy
npm install
sudo npm install -g grunt
sudo npm install -g flow-bin

# Run grunt to generate JS files
run_grunt "$CTF_PATH" "$MODE"

# Install nginx
install_nginx "$CTF_PATH" "$MODE"

# Install unison 2.48.3
install_unison
log "Remember install the same version of unison (2.48.3) in your host machine"

# Database creation
import_empty_db "root" "$P_ROOT" "$DB" "$CTF_PATH" "$MODE"

# Make attachments folder world writable
sudo chmod 777 "$CTF_PATH/src/data/attachments"
sudo chmod 777 "$CTF_PATH/src/data/attachments/deleted"

log 'fbctf deployment is complete! Ready in https://10.10.10.5'

exit 0
