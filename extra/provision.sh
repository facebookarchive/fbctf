#!/bin/bash
#
# FBCTF provisioning script
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
#   update Provision will update FBCTF running in the machine.
#
# Arguments for TYPE:
#   self   Provision will use a self-signed SSL certificate that will be generated.
#   own    Provision will use the SSL certificate provided by the user.
#   certbot Provision will generate a SSL certificate using letsencrypt/certbot. More info here: https://certbot.eff.org/
#
# Optional Parameters:
#   -U,         --update             Pull from master GitHub branch and sync files to fbctf folder.
#   -R,         --no-repo-mode       Disables HHVM Repo Authoritative mode in production mode.
#   -k PATH,    --keyfile PATH       Path to supplied SSL key file.
#   -C PATH,    --certfile PATH      Path to supplied SSL certificate pem file.
#   -D DOMAIN,  --domain DOMAIN      Domain for the SSL certificate to be generated using letsencrypt.
#   -e EMAIL,   --email EMAIL        Domain for the SSL certificate to be generated using letsencrypt.
#   -s PATH,    --code PATH          Path to fbctf code.
#   -d PATH,    --destination PATH   Destination path to place the fbctf folder.
#               --multiple-servers     Utilize multiple servers for installation. Server must be specified with --server-type
#               --server-type  SERVER  Server to provision. 'hhvm', 'nginx', 'mysql', or 'cache' can be used.
#               --hhvm-server  SERVER  HHVM Server IP when utilizing multiple servers. Call from 'nginx' server container.
#               --mysql-server SERVER  MySQL Server IP when utilizing multiple servers. Call from 'hhvm' server container.
#               --cache-server SERVER  Memcached Server IP when utilizing multiple servers. Call from 'hhvm' server container.
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
DOCKER=false
MULTIPLE_SERVERS=false
SERVER_TYPE="none"
HHVM_SERVER="hhvm"
MYSQL_SERVER="mysql"
CACHE_SERVER="cache"

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
  printf "  dev \tProvision will run in Development mode. Certificate will be self-signed.\n"
  printf "  prod \tProvision will run in Production mode.\n"
  printf "  update \tProvision will update FBCTF running in the machine.\n"
  printf "\nArguments for TYPE:\n"
  printf "  self \tProvision will use a self-signed SSL certificate that will be generated.\n"
  printf "  own \tProvision will use the SSL certificate provided by the user.\n"
  printf "  certbot Provision will generate a SSL certificate using letsencrypt/certbot. More info here: https://certbot.eff.org/\n"
  printf "\nOptional Parameters:\n"
  printf "  -U          --update \t\tPull from master GitHub branch and sync files to fbctf folder.\n"
  printf "  -R          --no-repo-mode \tDisables HHVM Repo Authoritative mode in production mode.\n"
  printf "  -k PATH     --keyfile PATH \tPath to supplied SSL key file.\n"
  printf "  -C PATH     --certfile PATH \tPath to supplied SSL certificate pem file.\n"
  printf "  -D DOMAIN   --domain DOMAIN \tDomain for the SSL certificate to be generated using letsencrypt.\n"
  printf "  -e EMAIL    --email EMAIL \tDomain for the SSL certificate to be generated using letsencrypt.\n"
  printf "  -s PATH     --code PATH \t\tPath to fbctf code. Default is /vagrant\n"
  printf "  -d PATH     --destination PATH \tDestination path to place the fbctf folder. Default is /var/www/fbctf\n"
  printf "  --multiple-servers    --utilize multiple servers for installation. Server must be specified with -st\n"
  printf "  --server-type SERVER  --specify server to provision. 'hhvm', 'nginx', 'mysql', or 'cache' can be used.\n"
  printf "  --hhvm-server SERVER  --specify HHVM Server IP when utilizing multiple servers. Call from 'nginx' container.\n"
  printf "  --mysql-server SERVER --specify MySQL Server IP when utilizing multiple servers. Call from 'hhvm' container.\n"
  printf "  --cache-server SERVER --memcached Server IP when utilizing multiple servers. Call from 'hhvm' server container.\n"
  printf "\nExamples:\n"
  printf "  Provision FBCTF in development mode:\n"
  printf "\t%s -m dev -s /home/foobar/fbctf -d /var/fbctf\n" "${0}"
  printf "  Provision FBCTF in production mode using my own certificate:\n"
  printf "\t%s -m prod -c own -k /etc/certs/my.key -C /etc/certs/cert.crt -s /home/foobar/fbctf -d /var/fbctf\n" "${0}"
  printf "  Update current FBCTF in development mode, having code in /home/foobar/fbctf and running from /var/fbctf:\n"
  printf "\t%s -m dev -U -s /home/foobar/fbctf -d /var/fbctf\n" "${0}"
}

ARGS=$(getopt -n "$0" -o hm:c:URk:C:D:e:s:d: -l "help,mode:,cert:,update,repo-mode,keyfile:,certfile:,domain:,email:,code:,destination:,docker,multiple-servers,server-type:,hhvm-server:,mysql-server:,cache-server:" -- "$@")

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
    --multiple-servers)
      MULTIPLE_SERVERS=true
      shift
      ;;
    --server-type)
      SERVER_TYPE=$2
      shift 2
      ;;
    --hhvm-server)
      HHVM_SERVER=$2
      shift 2
      ;;
    --mysql-server)
      MYSQL_SERVER=$2
      shift 2
      ;;
    --cache-server)
      CACHE_SERVER=$2
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

# Source library script for subprocesses
source "$CODE_PATH/extra/lib.sh"

package_repo_update

package git
package curl
package rsync

# Check for available memory, should be over 1GB
AVAILABLE_RAM=`free -mt | grep Total | awk '{print $2}'`

if [ $AVAILABLE_RAM -lt 1024 ]; then
    log "FBCTF is likely to fail to install without 1GB or more of RAM."
    log "Sleeping for 5 seconds."
    sleep 5
fi

# We only create a new directory and rsync files over if it's different from the original code path
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
        sudo chmod -R 755 "$CTF_PATH/"
    fi
fi

# If multiple servers are being utilized, ensure provision was called from the "nginx" or "hhvm" servers
    if [[ "$MULTIPLE_SERVERS" == false || "$SERVER_TYPE" = "nginx" || $SERVER_TYPE = "hhvm" ]]; then

    if [[ "$UPDATE" == true ]] ; then
        log "Updating repo"
        update_repo "$MODE" "$CODE_PATH" "$CTF_PATH"
        exit 0
    fi

    log "Provisioning in $MODE mode"
    log "Using $TYPE certificate"
    log "Source code folder $CODE_PATH"
    log "Destination folder $CTF_PATH"

    log "Setting Message of the Day (MOTD)"
    set_motd "$CTF_PATH"

    # If multiple servers are being utilized, ensure provision was called from the "hhvm" server
    if [[ "$MULTIPLE_SERVERS" == false || "$SERVER_TYPE" = "hhvm" ]]; then
        log "Installing HHVM"
        install_hhvm "$CTF_PATH" "$HHVM_CONFIG_PATH" "$MULTIPLE_SERVERS"

        log "Installing Composer"
        install_composer "$CTF_PATH"
        log "Installing Composer in /usr/bin"
        hhvm /usr/bin/composer.phar install

        # In production, enable HHVM Repo Authoritative mode by default.
        # More info here: https://docs.hhvm.com/hhvm/advanced-usage/repo-authoritative
        if [[ "$MODE" == "prod" ]] && [[ "$NOREPOMODE" == false ]]; then
            log "Enabling HHVM Repo Authoritative Mode"
            hhvm_performance "$CTF_PATH" "$HHVM_CONFIG_PATH"
        else
            log "HHVM Repo Authoritative mode NOT enabled"
        fi

        log "Creating DB Connection file"
        if [[ $MULTIPLE_SERVERS == true ]]; then
          cat "$CTF_PATH/extra/settings.ini.example" | sed "s/DBHOST/$MYSQL_SERVER/g" | sed "s/DATABASE/$DB/g" | sed "s/MYUSER/$U/g" | sed "s/MYPWD/$P/g" | sed "s/MCHOST/$CACHE_SERVER/g" | sudo tee "$CTF_PATH/settings.ini"
        else
          cat "$CTF_PATH/extra/settings.ini.example" | sed "s/DBHOST/127.0.0.1/g" | sed "s/DATABASE/$DB/g" | sed "s/MYUSER/$U/g" | sed "s/MYPWD/$P/g" | sed "s/MCHOST/127.0.0.1/g" | sudo tee "$CTF_PATH/settings.ini"
        fi
    fi

    # If multiple servers are being utilized, ensure provision was called from the "nginx" server
    if [[ "$MULTIPLE_SERVERS" == false || "$SERVER_TYPE" = "nginx" ]]; then
        # Packages to be installed in Dev mode
        if [[ "$MODE" == "dev" ]]; then
            package build-essential
            package libssl-dev
            package python-all-dev
            package python-setuptools
            package python-pip
            log "Upgrading pip"
            sudo -H pip install --upgrade pip
            log "Installing pip - mycli"
            sudo -H pip install mycli
            package emacs
            package htop
        fi

        package ca-certificates

        install_nodejs

        log "Installing all required npm node_modules"
        sudo npm install --prefix "$CTF_PATH"
        sudo npm install -g grunt
        sudo npm install -g flow-bin

        log "Running grunt to generate JS files"
        run_grunt "$CTF_PATH" "$MODE"

        log "Installing nginx and certificates"
        install_nginx "$CTF_PATH" "$MODE" "$TYPE" "$EMAIL" "$DOMAIN" "$DOCKER" "$MULTIPLE_SERVERS" "$HHVM_SERVER"

        log "Installing unison 2.48.3. Remember to install the same version on your host machine"
        package xz-utils
        install_unison
    fi

    log "Creating attachments folder, and setting ownership to www-data"
    sudo sudo mkdir -p "$CTF_PATH/attachments"
    sudo sudo mkdir -p "$CTF_PATH/attachments/deleted"
    sudo chown -R www-data:www-data "$CTF_PATH/attachments"
    sudo chown -R www-data:www-data "$CTF_PATH/attachments/deleted"

    log "Creating custom logos folder, and setting ownership to www-data"
    sudo mkdir -p "$CTF_PATH/src/data/customlogos"
    sudo chown -R www-data:www-data "$CTF_PATH/src/data/customlogos"
fi

# If multiple servers are being utilized, ensure provision was called from the "cache" server
if [[ "$MULTIPLE_SERVERS" == false || "$SERVER_TYPE" = "cache" ]]; then
    # Install Memcached
    package memcached

    # If cache server is running standalone, enable memcached for all interfaces.
    if [[ "$MULTIPLE_SERVERS" == true ]]; then
        sudo sed -i 's/^-l/#-l/g' /etc/memcached.conf
        sudo service memcached restart
    else
        sudo sed -i 's/^#-l/-l/g' /etc/memcached.conf
        sudo service memcached restart
    fi
fi

# If multiple servers are being utilized, ensure provision was called from the "mysql" server
if [[ "$MULTIPLE_SERVERS" == false || "$SERVER_TYPE" = "mysql" ]]; then
    log "Installing MySQL"
    install_mysql "$P_ROOT"

    # Configuration for MySQL
    if [[ "$MULTIPLE_SERVERS" == true ]] && [[ "$SERVER_TYPE" = "mysql" ]]; then
        # This is required in order to generate password hash (since HHVM is not being installed)
        package php7.0-cli

        sudo sed -e '/^bind-address/ s/^#*/#/' -i /etc/mysql/mysql.conf.d/mysqld.cnf
        sudo sed -e '/^skip-external-locking/ s/^#*/#/' -i /etc/mysql/mysql.conf.d/mysqld.cnf
	fi

    # Database creation
    log "Creating database"
    import_empty_db "root" "$P_ROOT" "$DB" "$CTF_PATH" "$MODE" "$MULTIPLE_SERVERS"
fi

# Display the final message, depending on the context
if [[ "$MULTIPLE_SERVERS" == true ]]; then
    if [[ "$DOCKER" == true ]]; then
        :
    else
        if [[ "$SERVER_TYPE" = "hhvm" ]]; then
            sudo service hhvm restart
        elif [[ "$SERVER_TYPE" = "nginx" ]]; then
            sudo service nginx restart
            if [[ -d "/vagrant" ]]; then
                ok_log 'FBCTF deployment is complete! Cleaning up... FBCTF will be Ready at https://10.10.10.5'
            fi
        elif [[ "$SERVER_TYPE" = "mysql" ]]; then
            sudo service mysql restart
        elif [[ "$SERVER_TYPE" = "cache" ]]; then
            sudo service memcached restart
        fi
    fi
elif [[ -d "/vagrant" ]]; then
    ok_log 'FBCTF deployment is complete! Cleaning up... FBCTF will be Ready at https://10.10.10.5'
else
    ok_log 'FBCTF deployment is complete! Cleaning up...'
fi

exit 0
