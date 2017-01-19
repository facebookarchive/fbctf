#!/bin/bash

# Facebook CTF: Functions for provisioning scripts
#

function log() {
  echo "[+] $1"
}

function error_log() {
  RED='\033[0;31m'
  NORMAL='\033[0m'
  echo "${RED} [!] $1 ${NORMAL}"
}

function ok_log() {
  GREEN='\033[0;32m'
  NORMAL='\033[0m'
  echo "${GREEN} [+] $1 ${NORMAL}"
}

function dl() {
  local __url=$1
  local __dest=$2

  if [ -n "$(which wget)" ]; then
    sudo wget -q "$__url" -O "$__dest"
  else
    sudo curl -s "$__url" -o "$__dest"
  fi
}

function package() {
  if [[ -n "$(dpkg --get-selections | grep $1)" ]]; then
    log "$1 is already installed. skipping."
  else
    log "Installing $1"
    sudo DEBIAN_FRONTEND=noninteractive apt-get install $1 -y --no-install-recommends
  fi
}

function install_unison() {
  log "Installing Unison 2.48.4"
  cd /
  curl -sL https://www.archlinux.org/packages/extra/x86_64/unison/download/ | sudo tar Jx
}

function repo_osquery() {
  log "Adding osquery repository keys"
  sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 1484120AC4E9F8A1A577AEEE97A80C63C9D8B80B
  sudo add-apt-repository "deb [arch=amd64] https://osquery-packages.s3.amazonaws.com/trusty trusty main"
}

function install_mysql() {
  local __pwd=$1

  log "Installing MySQL"

  echo "mysql-server-5.5 mysql-server/root_password password $__pwd" | sudo debconf-set-selections
  echo "mysql-server-5.5 mysql-server/root_password_again password $__pwd" | sudo debconf-set-selections
  package mysql-server

  # It should be started automatically, but just in case
  sudo service mysql restart
}

function set_motd() {
  local __path=$1

  # If the cloudguest MOTD exists, disable it
  if [[ -f /etc/update-motd.d/51/cloudguest ]]; then
    sudo chmod -x /etc/update-motd.d/51-cloudguest
  fi

  sudo cp "$__path/extra/motd-ctf.sh" /etc/update-motd.d/10-help-text
}

function run_grunt() {
  local __path=$1
  local __mode=$2

  cd "$__path"
  grunt

  # grunt watch on the VM will make sure your js files are
  # properly updated when developing 'remotely' with unison.
  # grunt watch might take up to 5 seconds to update a file,
  # give it some time while you are developing.
  if [[ $__mode = "dev" ]]; then
    grunt watch &
  fi
}

function self_signed_cert() {
  local __csr="/etc/nginx/certs/dev.csr"
  local __devcert=$1
  local __devkey=$2

  sudo openssl req -nodes -newkey rsa:2048 -keyout "$__devkey" -out "$__csr" -subj "/O=Facebook CTF"
  sudo openssl x509 -req -days 365 -in "$__csr" -signkey "$__devkey" -out "$__devcert"
}

function letsencrypt_cert() {
  local __email=$3
  local __domain=$4
  local __docker=$5

  dl "https://dl.eff.org/certbot-auto" /usr/bin/certbot-auto
  sudo chmod a+x /usr/bin/certbot-auto

  if [[ $__email == "none" ]]; then
    read -p ' -> What is the email for the SSL Certificate recovery? ' __myemail
  else
    __myemail=$__email
  fi
  if [[ $__domain == "none" ]]; then
    read -p ' -> What is the domain for the SSL Certificate? ' __mydomain
  else
    __mydomain=$__domain
  fi

  if [[ $__docker = true ]]; then
    cat <<- EOF > /root/tmp/certbot.sh
		#!/bin/bash
		if [[ ! ( -d /etc/letsencrypt && "\$(ls -A /etc/letsencrypt)" ) ]]; then
		    /usr/bin/certbot-auto certonly -n --agree-tos --standalone --standalone-supported-challenges tls-sni-01 -m "$__myemail" -d "$__mydomain"
		fi
		sudo ln -sf "/etc/letsencrypt/live/$__mydomain/fullchain.pem" "$1"
		sudo ln -sf "/etc/letsencrypt/live/$__mydomain/privkey.pem" "$2"
EOF
    sudo chmod +x /root/tmp/certbot.sh
  else
    /usr/bin/certbot-auto certonly -n --agree-tos --standalone --standalone-supported-challenges tls-sni-01 -m "$__myemail" -d "$__mydomain"
    sudo ln -s "/etc/letsencrypt/live/$__mydomain/fullchain.pem" "$1" || true
    sudo ln -s "/etc/letsencrypt/live/$__mydomain/privkey.pem" "$2" || true
  fi
}

function own_cert() {
  local __owncert=$1
  local __ownkey=$2

  read -p ' -> SSL Certificate file location? ' __mycert
  read -p ' -> SSL Key Certificate file location? ' __mykey
  sudo cp "$__mycert" "$__owncert"
  sudo cp "$__mykey" "$__ownkey"
}

function install_nginx() {
  local __path=$1
  local __mode=$2
  local __certs=$3
  local __email=$4
  local __domain=$5
  local __docker=$6

  local __certs_path="/etc/nginx/certs"

  log "Deploying certificates"
  sudo mkdir -p "$__certs_path"

  if [[ $__mode = "dev" ]]; then
    local __cert="$__certs_path/dev.crt"
    local __key="$__certs_path/dev.key"
    self_signed_cert "$__cert" "$__key"
  elif [[ $__mode = "prod" ]]; then
    local __cert="$__certs_path/fbctf.crt"
    local __key="$__certs_path/fbctf.key"
    case "$__certs" in
      self)
        self_signed_cert "$__cert" "$__key"
      ;;
      own)
        own_cert "$__cert" "$__key"
      ;;
      certbot)
        if [[ $__docker = true ]]; then
          self_signed_cert "$__cert" "$__key"
        fi
        letsencrypt_cert "$__cert" "$__key" "$__email" "$__domain" "$__docker"
      ;;
      *)
        error_log "Unrecognized type of certificate"
        exit 1
      ;;
    esac
  fi

  # We make sure to install nginx after installing the cert, because if we use
  # letsencrypt, we need to be sure nothing is listening on that port
  package nginx

  __dhparam="/etc/nginx/certs/dhparam.pem"
  sudo openssl dhparam -out "$__dhparam" 2048

  cat "$__path/extra/nginx.conf" | sed "s|CTFPATH|$__path/src|g" | sed "s|CER_FILE|$__cert|g" | sed "s|KEY_FILE|$__key|g" | sed "s|DHPARAM_FILE|$__dhparam|g" | sudo tee /etc/nginx/sites-available/fbctf.conf

  sudo rm -f /etc/nginx/sites-enabled/default
  sudo ln -sf /etc/nginx/sites-available/fbctf.conf /etc/nginx/sites-enabled/fbctf.conf

  # Restart nginx
  sudo nginx -t
  sudo service nginx restart
}

# TODO: We should split this function into one where the repo is added, and a
# second where the repo is installed
function install_hhvm() {
  local __path=$1
  local __config=$2

  package software-properties-common

  log "Adding HHVM key"
  sudo apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0x5a16e7281be7a449

  log "Adding HHVM repo"
  sudo add-apt-repository "deb http://dl.hhvm.com/ubuntu $(lsb_release -sc) main"

  log "Installing HHVM"
  sudo apt-get update
  # Installing the package so the dependencies are installed too
  package hhvm
  # The HHVM package version 3.15 is broken and crashes. See: https://github.com/facebook/hhvm/issues/7333
  # Until this is fixed, install manually closest previous version, 3.14.5
  sudo apt-get remove hhvm -y
  # Clear old files
  sudo rm -Rf /var/run/hhvm/*
  sudo rm -Rf /var/cache/hhvm/*

  local __package="hhvm_3.14.5~$(lsb_release -sc)_amd64.deb"
  dl "http://dl.hhvm.com/ubuntu/pool/main/h/hhvm/$__package" "/tmp/$__package"
  sudo dpkg -i "/tmp/$__package"

  log "Copying HHVM configuration"
  cat "$__path/extra/hhvm.conf" | sed "s|CTFPATH|$__path/|g" | sudo tee "$__config"

  log "HHVM as PHP systemwide"
  sudo /usr/bin/update-alternatives --install /usr/bin/php php /usr/bin/hhvm 60

  log "Enabling HHVM to start by default"
  sudo update-rc.d hhvm defaults

  log "Restart HHVM"
  sudo service hhvm restart
}

function hhvm_performance() {
  local __path=$1
  local __config=$2
  local __oldrepo="/var/run/hhvm/hhvm.hhbc"
  local __repofile="/var/cache/hhvm/hhvm.hhbc"

  log "Enabling HHVM RepoAuthoritative mode"
  cat "$__config" | sed "s|$__oldrepo|$__repofile|g" | sudo tee "$__config"
  sudo hhvm-repo-mode enable "$__path"
  sudo chown www-data:www-data "$__repofile"
}

function install_composer() {
  local __path=$1

  log "Installing composer"
  cd $__path
  curl -sS https://getcomposer.org/installer | php
  php composer.phar install
  sudo mv composer.phar /usr/bin
  sudo chmod +x /usr/bin/composer.phar
}

function import_empty_db() {
  local __u="ctf"
  local __p="ctf"
  local __user=$1
  local __pwd=$2
  local __db=$3
  local __path=$4
  local __mode=$5

  log "Creating DB - $__db"
  mysql -u "$__user" --password="$__pwd" -e "CREATE DATABASE IF NOT EXISTS \`$__db\`;"

  log "Importing schema..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/schema.sql;"
  log "Importing countries..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/countries.sql;"
  log "Importing logos..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/logos.sql;"

  log "Creating user..."
  mysql -u "$__user" --password="$__pwd" -e "CREATE USER '$__u'@'localhost' IDENTIFIED BY '$__p';" || true # don't fail if the user exists
  mysql -u "$__user" --password="$__pwd" -e "GRANT ALL PRIVILEGES ON \`$__db\`.* TO '$__u'@'localhost';"
  mysql -u "$__user" --password="$__pwd" -e "FLUSH PRIVILEGES;"

  log "DB Connection file"
  cat "$__path/extra/settings.ini.example" | sed "s/DATABASE/$__db/g" | sed "s/MYUSER/$__u/g" | sed "s/MYPWD/$__p/g" > "$__path/settings.ini"

  local PASSWORD
  log "Adding default admin user"
  if [[ $__mode = "dev" ]]; then
    PASSWORD='password'
  else
    PASSWORD=$(head -c 500 /dev/urandom | md5sum | cut -d" " -f1)
  fi

  set_password "$PASSWORD" "$__user" "$__pwd" "$__db" "$__path"
  log "The password for admin is: $PASSWORD"
}

function create_replication_user() {
  local __user=$1
  local __pwd=$2

  log "Creating replication user..."
  mysql -u "$__user" --password="$__pwd" -e "CREATE USER 'replicator'@'%' IDENTIFIED BY 'replicator';" || true # don't fail if the user exists
  #Unfortunately, I need to grant SUPER to replicator, because vagrant does not let us run a second script after the other server is finished setting up
  #There is a "trigger" vagrant plugin that could resolve this issue, and help ensure we do not grant more permissions then we should be
  mysql -u "$__user" --password="$__pwd" -e "GRANT SUPER ON *.* TO 'replicator'@'%'"
}

function setup_db_replication() {
  NUMBER_OF_SERVERS=$1
  CURRENT_SERVER_NUMBER=$2
  PREVIOUS_SERVER_IP=$(($CURRENT_SERVER_NUMBER+3))
  CURRENT_SERVER_IP=$((CURRENT_SERVER_NUMBER+4))
  log "Adding replication settings to my.cnf..."
  sed -i '/bind-address\s\+= 127.0.0.1/c\#bind-address		= 127.0.0.1' "/etc/mysql/my.cnf"
  sed -i "/#server-id\s\+= 1/c\server-id		= $CURRENT_SERVER_NUMBER" "/etc/mysql/my.cnf"
  sed -i '/#binlog_do_db\s\+= include_database_name/c\binlog_do_db		= fbctf' "/etc/mysql/my.cnf"
  sed -i '/#log_bin\s\+= \/var\/log\/mysql\/mysql-bin.log/c\log_bin		= /var/log/mysql/mysql-bin.log' "/etc/mysql/my.cnf"

  #This value should always be however many databsase servers are going to be setup, value N
  echo "auto_increment_increment = $NUMBER_OF_SERVERS" >> "/etc/mysql/my.cnf"

  #This value should be different for every database server. The first server should be 1, incrementing until the final server has the same value as N.
  #Example: 5 servers. Server 1 has an offset of 1, Server 2 an offset of 2, up to Server 5 with an offset of 5.
  echo "auto_increment_offset = $CURRENT_SERVER_NUMBER" >> "/etc/mysql/my.cnf"



  #Unfortunately Vagrant does not allow for a provision script to be run AFTER all machines have been created
  #So I have to configure the setting in this script

  if [ "$CURRENT_SERVER_NUMBER" -eq 1 ]; then
    log "This is the first server, the next server will connect to mysql and set it up..."
    log "Restarting mysql..."
    sudo service mysql restart
  elif [ "$NUMBER_OF_SERVERS" -ne "$CURRENT_SERVER_NUMBER"  -a  "$CURRENT_SERVER_NUMBER" -ne 1 ]; then 
    log "Restarting mysql so it generates bin_log info..."
    sudo service mysql restart
    log "Retrieve bin_log information to give to the other server..."
    SMS=/tmp/show_master_status.txt
    mysql -ureplicator -preplicator -ANe "SHOW MASTER STATUS" > ${SMS}
    CURRENT_LOG=`cat ${SMS} | awk '{print $1}'`
    CURRENT_POS=`cat ${SMS} | awk '{print $2}'`

    log "Configuring Master info onto remote mysql database..."
    mysql -h 10.10.10.${PREVIOUS_SERVER_IP} -u replicator --password=replicator -e "CHANGE MASTER TO MASTER_HOST = '10.10.10.${CURRENT_SERVER_IP}', MASTER_USER = 'replicator', MASTER_PASSWORD = 'replicator', MASTER_LOG_FILE = '${CURRENT_LOG}', MASTER_LOG_POS = ${CURRENT_POS}"
  else
    log "Restarting mysql so it generates bin_log info..."
    sudo service mysql restart
    log "Retrieve bin_log information to give to the other server..."
    SMS2=/tmp/show_master_status2.txt
    mysql -ureplicator -preplicator -ANe "SHOW MASTER STATUS" > ${SMS2}
    CURRENT_LOG2=`cat ${SMS2} | awk '{print $1}'`
    CURRENT_POS2=`cat ${SMS2} | awk '{print $2}'`

    log "Configuring Master info onto remote mysql database..."
    mysql -h 10.10.10.${PREVIOUS_SERVER_IP} -u "replicator" --password="replicator" -e "CHANGE MASTER TO MASTER_HOST = '10.10.10.${CURRENT_SERVER_IP}', MASTER_USER = 'replicator', MASTER_PASSWORD = 'replicator', MASTER_LOG_FILE = '${CURRENT_LOG2}', MASTER_LOG_POS = ${CURRENT_POS2}"


   log "Connecting to other master server to retrieve bin_log information..."
   SMS=/tmp/show_master_status.txt
   mysql -h 10.10.10.5 -ureplicator -preplicator -ANe "SHOW MASTER STATUS" > ${SMS}
   CURRENT_LOG=`cat ${SMS} | awk '{print $1}'`
   CURRENT_POS=`cat ${SMS} | awk '{print $2}'`

   log "Configuring Master info onto local mysql database..."
   mysql -u "root" --password="root" -e "CHANGE MASTER TO MASTER_HOST = '10.10.10.5', MASTER_USER = 'replicator', MASTER_PASSWORD = 'replicator', MASTER_LOG_FILE = '${CURRENT_LOG}', MASTER_LOG_POS = ${CURRENT_POS}"
  fi
}

function set_password() {
  local __admin_pwd=$1
  local __user=$2
  local __db_pwd=$3
  local __db=$4
  local __path=$5

  HASH=$(hhvm -f "$__path/extra/hash.php" "$__admin_pwd")

  # First try to delete the existing admin user
  mysql -u "$__user" --password="$__db_pwd" "$__db" -e "DELETE FROM teams WHERE name='admin' AND admin=1"

  # Then insert the new admin user with ID 1 (just as a convention, we shouldn't rely on this in the code)
  mysql -u "$__user" --password="$__db_pwd" "$__db" -e "INSERT INTO teams (id, name, password_hash, admin, protected, logo, created_ts) VALUES (1, 'admin', '$HASH', 1, 1, 'admin', NOW());"
}

function update_repo() {
  local __mode=$1
  local __code_path=$2
  local __ctf_path=$3

  if pgrep -x "grunt" > /dev/null
  then
    killall -9 grunt
  fi

  log "Pulling from remote repository"
  git pull --rebase https://github.com/facebook/fbctf.git

  log "Starting sync to $__ctf_path"
  if [[ "$__code_path" != "$__ctf_path" ]]; then
      [[ -d "$__ctf_path" ]] || sudo mkdir -p "$__ctf_path"

      log "Copying all CTF code to destination folder"
      sudo rsync -a --exclude node_modules --exclude vendor "$__code_path/" "$__ctf_path/"

      # This is because sync'ing files is done with unison
      if [[ "$__mode" == "dev" ]]; then
          log "Configuring git to ignore permission changes"
          git -C "$CTF_PATH/" config core.filemode false
          log "Setting permissions"
          sudo chmod -R 777 "$__ctf_path/"
      fi
  fi

  cd "$__ctf_path"
  composer.phar install

  run_grunt "$__ctf_path" "$__mode"
}
