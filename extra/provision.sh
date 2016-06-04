#!/bin/bash
#
# fbctf provisioning script
#
# Usage: usage [-h|--help] [PARAMETER [ARGUMENT]] [PARAMETER [ARGUMENT]] ...
#
# Parameters:
#   -h, --help            Shows this help message and exit.
#   -m MODE, --mode MODE  Mode of operation. Default value is dev
#   -c TYPE, --cert TYPE  Type of certificate to use. Default value is self
#
# Arguments:
#   dev    Provision will run in development mode. Certificate will be self-signed.
#   prod   Provision will run in production mode.
#   self   Provision will use a self-signed SSL certificate that will be generated.
#   own    Provision will use the SSL certificate provided by the user.
#   certbot Provision will generate a SSL certificate using letsencrypt/certbot. More info here: https://certbot.eff.org/
#
# Optional Parameters:
#   -k PATH, --keyfile PATH      Path to supplied SSL key file.
#   -C PATH, --certfile PATH     Path to supplied SSL certificate pem file.
#   -d DOMAIN, --domain DOMAIN   Domain for the SSL certificate to be generated using letsencrypt.
#   -m EMAIL, --email EMAIL      Domain for the SSL certificate to be generated using letsencrypt.
#   -s PATH, --code PATH         Path to fbctf code.
#   -D PATH, --destination PATH  Destination path to place the fbctf folder.
#
# Examples:
#   Provision fbctf in development mode:
#     usage -m dev -s /home/foobar/fbctf -D /var/fbctf
#   Provision fbctf in production mode using my own certificate:
#     usage -m prod -c own -k /etc/certs/my.key -C /etc/certs/cert.crt -s /home/foobar/fbctf -D /var/fbctf

# We want the provision script to fail as soon as there are any errors
set -e

DB="fbctf"
U="ctf"
P="ctf"
P_ROOT="root"

# Default values
MODE="dev"
TYPE="self"
KEYFILE="none"
CERTFILE="none"
DOMAIN="none"
EMAIL="none"
CODE_PATH="/vagrant"
CTF_PATH="/var/www/fbctf"

function usage() {
  printf "\nfbctf provisioning script\n"
  printf "\nUsage: %s [-h|--help] [PARAMETER [ARGUMENT]] [PARAMETER [ARGUMENT]] ...\n" "${0}"
  printf "\nParameters:\n"
  printf "  -h, --help \t\tShows this help message and exit.\n"
  printf "  -m MODE, --mode MODE \tMode of operation. Default value is dev\n"
  printf "  -c TYPE, --cert TYPE \tType of certificate to use. Default value is self\n"
  printf "\nArguments:\n"
  printf "  dev \tProvision will run in development mode. Certificate will be self-signed.\n"
  printf "  prod \tProvision will run in production mode.\n"
  printf "  self \tProvision will use a self-signed SSL certificate that will be generated.\n"
  printf "  own \tProvision will use the SSL certificate provided by the user.\n"
  printf "  cerbot Provision will generate a SSL certificate using letsencrypt/certbot. More info here: https://certbot.eff.org/\n"
  printf "\nOptional Parameters:\n"
  printf "  -k PATH, --keyfile PATH \tPath to supplied SSL key file.\n"
  printf "  -C PATH, --certfile PATH \tPath to supplied SSL certificate pem file.\n"
  printf "  -d DOMAIN, --domain DOMAIN \tDomain for the SSL certificate to be generated using letsencrypt.\n"
  printf "  -m EMAIL, --email EMAIL \tDomain for the SSL certificate to be generated using letsencrypt.\n"
  printf "  -s PATH, --code PATH \t\tPath to fbctf code. Default is /vagrant\n"
  printf "  -D PATH, --destination PATH \tDestination path to place the fbctf folder. Default is /var/www/fbctf\n"
  printf "\nExamples:\n"
  printf "  Provision fbctf in development mode:\n" 
  printf "\t%s -m dev -s /home/foobar/fbctf -D /var/fbctf\n" "${0}"
  printf "  Provision fbctf in production mode using my own certificate:\n" 
  printf "\t%s -m prod -c own -k /etc/certs/my.key -C /etc/certs/cert.crt -s /home/foobar/fbctf -D /var/fbctf\n" "${0}"
}

ARGS=$(getopt -n "$0" -o hm:c:k:C:d:m:s:D: -l "help,mode:,cert:,keyfile:,certfile:,domain:,email:,code:,destination:" -- "$@")

eval set -- "$ARGS"

while true; do
  case "$1" in
    -h|--help)
      usage
      exit 0
      ;;
    -m|-mode)
      MODE=$2
      shift 2
      ;;
    -c|--cert)
      TYPE=$2
      shift 2
      ;;
    -k|--keyfile)
      KEYFILE=$2
      shift 2
      ;;
    -C|--certfile)
      CERTFILE=$2
      shift 2
      ;;
    -d|--domain)
      DOMAIN=$2
      shift 2
      ;;
    -m|--email)
      EMAIL=$2
      shift 2
      ;;
    -s|--code)
      CODE_PATH=$2
      shift 2
      ;;
    -D|--destination)
      CTF_PATH=$2
      shift 2
      ;;
    --)
      shift
      break
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

echo "[+] Provisioning in $MODE mode"
echo "[+] Using $TYPE certificate"

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

# Some people need this language pack installed or HHVM will report errors
package language-pack-en

# Repos to be added in dev mode
if [[ "$MODE" == "dev" ]]; then
    repo_mycli
fi

# We only run this once so provisioning is faster
sudo apt-get update

# Packages to be installed in dev mode
if [[ "$MODE" == "dev" ]]; then
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

# Install nginx and certificates
install_nginx "$CTF_PATH" "$MODE" "$TYPE" "$EMAIL" "$DOMAIN"

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
