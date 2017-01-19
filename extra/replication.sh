#!/bin/bash
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

ARGS=$(getopt -n "$0" -o hm:c:URk:C:D:e:s:d:r:N:P: -l "help,mode:,cert:,update,repo-mode,keyfile:,certfile:,domain:,email:,code:,destination:,replication,server-number,replicator-password,docker" -- "$@")

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
    -r|--replication)
      NUMBER_OF_SERVERS=$(($2+1))
      shift 2
      ;;
    -N|--server-number)
      CURRENT_SERVER_NUMBER=$2
      shift 2
      ;;
    -P|--replicator-password)
      REPLICATOR_PASSWORD=$2
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

if [[ "$CURRENT_SERVER_NUMBER" -eq 1 ]] ; then

#Create the replication user
create_replication_user "root" "$P_ROOT"

setup_db_replication "$NUMBER_OF_SERVERS" "$CURRENT_SERVER_NUMBER"

  log "Restarting mysql to enable bin_log and the replication..."
  sudo service mysql restart


else

 # Install git first
 package git

 # Are we just updating a running fbctf?
 if [[ "$UPDATE" == true ]] ; then
    update_repo "$MODE" "$CODE_PATH" "$CTF_PATH"
    exit 0
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

 # Database creation
 import_empty_db "root" "$P_ROOT" "$DB" "$CTF_PATH" "$MODE"

 #Create the replication user
 create_replication_user "root" "$P_ROOT" "$DB" "$REPLICATOR_PASSWORD"

 setup_db_replication "$NUMBER_OF_SERVERS" "$CURRENT_SERVER_NUMBER" "$REPLICATOR_PASSWORD"

  log "Restarting mysql to enable bin_log and the replication..."
  sudo service mysql restart

fi
  exit 0
