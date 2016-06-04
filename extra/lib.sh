#!/bin/bash

# Facebook CTF: Functions for provisioning scripts
#

function log() {
  echo "[+] $1"
}

function error() {
  echo "[!] $1" 
}

function dl() {
  local __url=$1
  local __dest=$2

  if [ -n "$(which wget)" ]; then
    wget -q "$__url" -O "$__dest"
  else
    curl -s "$__url" -o "$__dest"
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
  log "Installing Unison 2.48.3"
  cd /
  curl -sL https://www.archlinux.org/packages/extra/x86_64/unison/download/ | sudo tar Jx
}

function repo_osquery() {
  log "Adding osquery repository keys"
  sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 1484120AC4E9F8A1A577AEEE97A80C63C9D8B80B
  sudo add-apt-repository "deb [arch=amd64] https://osquery-packages.s3.amazonaws.com/trusty trusty main"
}

function repo_mycli() {
  log "Adding MyCLI repository keys"
  curl -s https://packagecloud.io/gpg.key | sudo apt-key add -
  package apt-transport-https
  echo "deb https://packagecloud.io/amjith/mycli/ubuntu/ trusty main" | sudo tee -a /etc/apt/sources.list
}

function install_mysql() {
  local __pwd=$1

  log "Installing MySQL"

  echo "mysql-server-5.5 mysql-server/root_password password $__pwd" | sudo debconf-set-selections
  echo "mysql-server-5.5 mysql-server/root_password_again password $__pwd" | sudo debconf-set-selections
  package mysql-server
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

  grunt

  # grunt watch on the VM will make sure your js files are
  # properly updated when developing 'remotely' with unison.
  # grunt watch might take up to 5 seconds to update a file,
  # give it some time while you are developing.
  if [[ $__mode = "dev" ]]; then
    cd $__path
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

  sudo dl "https://dl.eff.org/certbot-auto" /usr/bin/certbot-auto
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
  
  /usr/bin/certbot-auto certonly -n --standalone --standalone-supported-challenges tls-sni-01 -m "$__myemail" -d "$__mydomain"
  sudo ln -s "/etc/letsencrypt/live/$__mydomain/cert.pem" "$1"
  sudo ln -s "/etc/letsencrypt/live/$__mydomain/privkey.pem" "$2"
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

  local __certs_path="/etc/nginx/certs"

  package nginx

  log "Deploying certificates"
  sudo mkdir -p "$__certs_path"

  if [[ $__mode = "dev" ]]; then
    local __cert="$__certs_path/dev.crt"
    local __key="$__certs_path/dev.key"
    self_signed_cert "$__cert" "$__key"
  elif [[ $__mode = "prod" ]]; then
    local __cert="$__certs_path/fbctf.crt"
    local __key="__certs_path/fbctf.key"
    case "$__certs" in
      self)
        self_signed_cert "$__cert" "$__key"
        break
      ;;
      own)
        own_cert "$__cert" "$__key"
        break
      ;;
      certbot)
        letsencrypt_cert "$__cert" "$__key" "$__email" "$__domain"
        break
      ;;
      *)
        error "Unrecognized type of certificate"
        exit 1
      ;;
    esac
  fi

  __dhparam="/etc/nginx/certs/dhparam.pem"
  sudo openssl dhparam -out "$__dhparam" 2048
  
  cat "$__path/extra/nginx.conf" | sed "s|CTFPATH|$__path/src|g" | sed "s|CER_FILE|$__cert|g" | sed "s|KEY_FILE|$__key|g" | sed "s|DHPARAM_FILE|$__dhparam|g" | sudo tee /etc/nginx/sites-available/fbctf.conf
  
  sudo rm /etc/nginx/sites-enabled/default
  sudo ln -s /etc/nginx/sites-available/fbctf.conf /etc/nginx/sites-enabled/fbctf.conf

  # Restart nginx
  sudo nginx -t
  sudo service nginx restart
}

# TODO: We should split this function into one where the repo is added, and a
# second where the repo is installed
function install_hhvm() {
  local __path=$1

  package software-properties-common

  log "Adding HHVM key"
  sudo apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0x5a16e7281be7a449

  log "Adding HHVM repo"
  sudo add-apt-repository "deb http://dl.hhvm.com/ubuntu $(lsb_release -sc) main"

  log "Installing HHVM"
  sudo apt-get update
  package hhvm

  log "Copying HHVM configuration"
  cat "$__path/extra/hhvm.conf" | sed "s|CTFPATH|$__path/|g" | sudo tee /etc/hhvm/server.ini

  log "HHVM as PHP systemwide"
  sudo /usr/bin/update-alternatives --install /usr/bin/php php /usr/bin/hhvm 60

  log "Enabling HHVM to start by default"
  sudo update-rc.d hhvm defaults

  log "Restart HHVM"
  sudo service hhvm restart
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
  mysql -u "$__user" --password="$__pwd" -e "CREATE DATABASE \`$__db\`;"

  log "Importing schema..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/schema.sql;"
  log "Importing countries..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/countries.sql;"
  log "Importing logos..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/logos.sql;"

  log "Creating user..."
  mysql -u "$__user" --password="$__pwd" -e "CREATE USER '$__u'@'localhost' IDENTIFIED BY '$__p';"
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
