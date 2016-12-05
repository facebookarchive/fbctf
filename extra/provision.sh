#!/bin/bash
#
# fbctf provisioning script
#
# Usage: provision.sh [-h|--help] [PARAMETER [ARGUMENT]] [PARAMETER [ARGUMENT]] ...
#
# Parameters:
#   -h, --help            Shows this help message and exit.
#   -m MODE, --mode MODE  Mode of operation. Default value is dev
#   -c TYPE, --cert TYPE  Type of certificate to use. Default value is self
#
# Arguments for MODE:
#   dev    Provision will run in development mode. Certificate will be self-signed.
#   prod   Provision will run in production mode.
#   update Provision will update fbctf running in the machine.
#
# Arguments for TYPE:
#   self   Provision will use a self-signed SSL certificate that will be generated.
#   own    Provision will use the SSL certificate provided by the user.
#   certbot Provision will generate a SSL certificate using letsencrypt/certbot. More info here: https://certbot.eff.org/
#
# Optional Parameters:
#   -U,      --update            Pull from master GitHub branch and sync files to fbctf folder.
#   -R,      --no-repo-mode      Disables HHVM Repo Authoritative mode in production mode.
#   -k PATH, --keyfile PATH      Path to supplied SSL key file.
#   -C PATH, --certfile PATH     Path to supplied SSL certificate pem file.
#   -D DOMAIN, --domain DOMAIN   Domain for the SSL certificate to be generated using letsencrypt.
#   -e EMAIL, --email EMAIL      Domain for the SSL certificate to be generated using letsencrypt.
#   -s PATH, --code PATH         Path to fbctf code.
#   -d PATH, --destination PATH  Destination path to place the fbctf folder.
#
# Examples:
#   Provision fbctf in development mode:
#     provision.sh -m dev -s /home/foobar/fbctf -d /var/fbctf
#   Provision fbctf in production mode using my own certificate:
#     provision.sh -m prod -c own -k /etc/certs/my.key -C /etc/certs/cert.crt -s /home/foobar/fbctf -d /var/fbctf
#   Update current fbctf in development mode, having code in /home/foobar/fbctf and running from /var/fbctf:
#     provision.sh -m dev -U -s /home/foobar/fbctf -d /var/fbctf

# We want the provision script to fail as soon as there are any errors
set -e

DB="fbctf"
U="ctf"
P="ctf"
P_ROOT="root"

# Default values
MODE="dev"
NOREPOMODE=false
TYPE="self"
KEYFILE="none"
CERTFILE="none"
DOMAIN="none"
EMAIL="none"
CODE_PATH="/vagrant"
CTF_PATH="/var/www/fbctf"
HHVM_CONFIG_PATH="/etc/hhvm/server.ini"

# Arrays with valid arguments
VALID_MODE=("dev" "prod")
VALID_TYPE=("self" "own" "certbot")

function usage() {
  printf "\nfbctf provisioning script\n"
  printf "\nUsage: %s [-h|--help] [PARAMETER [ARGUMENT]] [PARAMETER [ARGUMENT]] ...\n" "${0}"
  printf "\nParameters:\n"
  printf "  -h, --help \t\tShows this help message and exit.\n"
  printf "  -m MODE, --mode MODE \tMode of operation. Default value is dev\n"
  printf "  -c TYPE, --cert TYPE \tType of certificate to use. Default value is self\n"
  printf "\nArguments for MODE:\n"
  printf "  dev \tProvision will run in development mode. Certificate will be self-signed.\n"
  printf "  prod \tProvision will run in production mode.\n"
  printf "  update \tProvision will update fbctf running in the machine.\n"
  printf "\nArguments for TYPE:\n"
  printf "  self \tProvision will use a self-signed SSL certificate that will be generated.\n"
  printf "  own \tProvision will use the SSL certificate provided by the user.\n"
  printf "  cerbot Provision will generate a SSL certificate using letsencrypt/certbot. More info here: https://certbot.eff.org/\n"
  printf "\nOptional Parameters:\n"
  printf "  -U,      --update \t\tPull from master GitHub branch and sync files to fbctf folder.\n"
  printf "  -R,      --no-repo-mode \tDisables HHVM Repo Authoritative mode in production mode.\n"
  printf "  -k PATH, --keyfile PATH \tPath to supplied SSL key file.\n"
  printf "  -C PATH, --certfile PATH \tPath to supplied SSL certificate pem file.\n"
  printf "  -D DOMAIN, --domain DOMAIN \tDomain for the SSL certificate to be generated using letsencrypt.\n"
  printf "  -e EMAIL, --email EMAIL \tDomain for the SSL certificate to be generated using letsencrypt.\n"
  printf "  -s PATH, --code PATH \t\tPath to fbctf code. Default is /vagrant\n"
  printf "  -d PATH, --destination PATH \tDestination path to place the fbctf folder. Default is /var/www/fbctf\n"
  printf "\nExamples:\n"
  printf "  Provision fbctf in development mode:\n"
  printf "\t%s -m dev -s /home/foobar/fbctf -d /var/fbctf\n" "${0}"
  printf "  Provision fbctf in production mode using my own certificate:\n"
  printf "\t%s -m prod -c own -k /etc/certs/my.key -C /etc/certs/cert.crt -s /home/foobar/fbctf -d /var/fbctf\n" "${0}"
  printf "  Update current fbctf in development mode, having code in /home/foobar/fbctf and running from /var/fbctf:\n"
  printf "\t%s -m dev -U -s /home/foobar/fbctf -d /var/fbctf\n" "${0}"
}

ARGS=$(getopt -n "$0" -o hm:c:URk:C:D:e:s:d: -l "help,mode:,cert:,update,repo-mode,keyfile:,certfile:,domain:,email:,code:,destination:,docker" -- "$@")

eval set -- "$ARGS"

while true; do
  case "$1" in
    -h|--help)
      usage
      exit 0
      ;;
    -m|-mode)
      GIVEN_ARG=$2
      if [[ "${VALID_MODE[@]}" =~ "${GIVEN_ARG}" ]]; then
        MODE=$2
        shift 2
      else
        usage
        exit 1
      fi
      ;;
    -c|--cert)
      GIVEN_ARG=$2
      if [[ "${VALID_TYPE[@]}" =~ "${GIVEN_ARG}" ]]; then
        TYPE=$2
        shift 2
      else
        usage
        exit 1
      fi
      ;;
    -U|--update)
      UPDATE=true
      shift
      ;;
    -R|--no-repo-mode)
      NOREPOMODE=true
      shift
      ;;
    -k|--keyfile)
      KEYFILE=$2
      shift 2
      ;;
    -C|--certfile)
      CERTFILE=$2
      shift 2
      ;;
    -D|--domain)
      DOMAIN=$2
      shift 2
      ;;
    -e|--email)
      EMAIL=$2
      shift 2
      ;;
    -s|--code)
      CODE_PATH=$2
      shift 2
      ;;
    -d|--destination)
      CTF_PATH=$2
      shift 2
      ;;
    --docker)
      DOCKER=true
      shift
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

source "$CODE_PATH/extra/lib.sh"

# Install git first
package git

# Are we just updating a running fbctf?
if [[ "$UPDATE" == true ]] ; then
    update_repo "$MODE" "$CODE_PATH" "$CTF_PATH"
    exit 0
fi

AVAILABLE_RAM=`free -mt | grep Total | awk '{print $2}'`

if [ $AVAILABLE_RAM -lt 1024 ]; then
    log "FBCTF is likely to fail to install without 1GB or more of RAM."
    log "Sleeping for 5 seconds."
    sleep 5
fi

log "Provisioning in $MODE mode"
log "Using $TYPE certificate"
log "Source code folder $CODE_PATH"
log "Destination folder $CTF_PATH"

# We only create a new directory and rsync files over if it's different from the
# original code path
if [[ "$CODE_PATH" != "$CTF_PATH" ]]; then
    log "Creating code folder $CTF_PATH"
    [[ -d "$CTF_PATH" ]] || sudo mkdir -p "$CTF_PATH"

    log "Copying all CTF code to destination folder"
    sudo rsync -a --exclude node_modules --exclude vendor "$CODE_PATH/" "$CTF_PATH/"

    # This is because sync'ing files is done with unison
    if [[ "$MODE" == "dev" ]]; then
        log "Configuring git to ignore permission changes"
        git -C "$CTF_PATH/" config core.filemode false
        log "Setting permissions"
        sudo chmod -R 777 "$CTF_PATH/"
    fi
fi

# There we go!

# Ascii art is always appreciated
set_motd "$CTF_PATH"

# Some Ubuntu distros don't come with curl installed
package curl

# We only run this once so provisioning is faster
sudo apt-get update

# Some people need this language pack installed or HHVM will report errors
package language-pack-en

# Packages to be installed in dev mode
if [[ "$MODE" == "dev" ]]; then
    sudo apt-get install -y build-essential python-all-dev python-setuptools
    package python-pip
    sudo -H pip install --upgrade pip
    sudo -H pip install mycli
    package emacs
    package htop
fi

# Install memcached
package memcached

# Install MySQL
install_mysql "$P_ROOT"

# Install HHVM
install_hhvm "$CTF_PATH" "$HHVM_CONFIG_PATH"

# Install Composer
install_composer "$CTF_PATH"
# This step has done `cd "$CTF_PATH"`
composer.phar install

# In production, enable HHVM Repo Authoritative mode by default.
# More info here: https://docs.hhvm.com/hhvm/advanced-usage/repo-authoritative
if [[ "$MODE" == "prod" ]] && [[ "$NOREPOMODE" == false ]]; then
  hhvm_performance "$CTF_PATH" "$HHVM_CONFIG_PATH"
else
  log "HHVM Repo Authoritative mode NOT enabled"
fi

# Install and update NPM
package npm
# Update NPM with itself: https://github.com/npm/npm/issues/14610
sudo npm install -g npm@lts

# Install node
package nodejs-legacy

# Install all required node_modules in the CTF folder
sudo npm install --prefix "$CTF_PATH"
sudo npm install -g grunt
sudo npm install -g flow-bin

# Run grunt to generate JS files
run_grunt "$CTF_PATH" "$MODE"

# Install nginx and certificates
install_nginx "$CTF_PATH" "$MODE" "$TYPE" "$EMAIL" "$DOMAIN" "$DOCKER"

# Install unison 2.48.3
install_unison
log "Remember install the same version of unison (2.48.3) in your host machine"

# Database creation
import_empty_db "root" "$P_ROOT" "$DB" "$CTF_PATH" "$MODE"

# Make attachments folder world writable
sudo chmod 777 "$CTF_PATH/src/data/attachments"
sudo chmod 777 "$CTF_PATH/src/data/attachments/deleted"

# Display the final message, depending on the context
if [[ -d "/vagrant" ]]; then
  log 'fbctf deployment is complete! Ready in https://10.10.10.5'
else
  ok_log 'fbctf deployment is complete!'
fi

exit 0
