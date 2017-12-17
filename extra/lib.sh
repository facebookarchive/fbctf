#!/bin/bash

# Facebook CTF: Functions for provisioning scripts
#

function log() {
  echo "[+] $@"
}

function print_blank_lines() {
  for i in {1..10}
  do
    echo
  done
}

function error_log() {
  RED='\033[0;31m'
  NORMAL='\033[0m'
  echo -e "${RED} [!] $1 ${NORMAL}"
}

function ok_log() {
  GREEN='\033[0;32m'
  NORMAL='\033[0m'
  echo -e "${GREEN} [+] $1 ${NORMAL}"
}

function dl() {
  local __url=$1
  local __dest=$2
  sudo curl --retry 5 --retry-delay 15 -sSL "$__url" -o "$__dest"
}

function dl_pipe() {
  local __url=$1
  curl --retry 5 --retry-delay 15 -sSL "$__url"
}

function package_repo_update() {
  log "Running apt-get update"
  sudo DEBIAN_FRONTEND=noninteractive apt-get update
}

function package() {
  if [[ -n "$(dpkg --get-selections | grep -P '^$1\s')" ]]; then
    log "$1 is already installed. skipping."
  else
    log "Installing $1"
    sudo DEBIAN_FRONTEND=noninteractive apt-get install $1 -y --no-install-recommends
  fi
}

function install_unison() {
  cd /
  dl_pipe "https://www.archlinux.org/packages/extra/x86_64/unison/download/" | sudo tar Jx
}

function repo_osquery() {
  log "Adding osquery repository keys"
  sudo DEBIAN_FRONTEND=noninteractive apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 1484120AC4E9F8A1A577AEEE97A80C63C9D8B80B
  sudo DEBIAN_FRONTEND=noninteractive add-apt-repository "deb [arch=amd64] https://pkg.osquery.io/deb deb main"
}

function install_mysql() {
  local __pwd=$1

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
  grunt --force

  # grunt watch on the VM will make sure your js files are
  # properly updated when developing 'remotely' with unison.
  # grunt watch might take up to 5 seconds to update a file,
  # give it some time while you are developing.
  if [[ "$__mode" = "dev" ]]; then
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

  if [[ "$__email" == "none" ]]; then
    read -p ' -> What is the email for the SSL Certificate recovery? ' __myemail
  else
    __myemail=$__email
  fi
  if [[ "$__domain" == "none" ]]; then
    read -p ' -> What is the domain for the SSL Certificate? ' __mydomain
  else
    __mydomain=$__domain
  fi

  if [[ "$__docker" = true ]]; then
    mkdir -p /root/tmp
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
  local __multiservers=$7
  local __hhvmserver=$8

  local __certs_path="/etc/nginx/certs"

  log "Deploying certificates"
  sudo mkdir -p "$__certs_path"

  if [[ "$__mode" = "dev" ]]; then
    local __cert="$__certs_path/dev.crt"
    local __key="$__certs_path/dev.key"
    self_signed_cert "$__cert" "$__key"
  elif [[ "$__mode" = "prod" ]]; then
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
        if [[ "$__docker" = true ]]; then
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

  if [[ "$__multiservers" == true ]]; then
      cat "$__path/extra/nginx/nginx.conf" | sed "s|CTFPATH|$__path/src|g" | sed "s|CER_FILE|$__cert|g" | sed "s|KEY_FILE|$__key|g" | sed "s|DHPARAM_FILE|$__dhparam|g" | sed "s|HHVMSERVER|$__hhvmserver|g" | sudo tee /etc/nginx/sites-available/fbctf.conf
  else
      cat "$__path/extra/nginx.conf" | sed "s|CTFPATH|$__path/src|g" | sed "s|CER_FILE|$__cert|g" | sed "s|KEY_FILE|$__key|g" | sed "s|DHPARAM_FILE|$__dhparam|g" | sudo tee /etc/nginx/sites-available/fbctf.conf
  fi

  sudo rm -f /etc/nginx/sites-enabled/default
  sudo ln -sf /etc/nginx/sites-available/fbctf.conf /etc/nginx/sites-enabled/fbctf.conf

  if [[ "$__multiservers" == false ]]; then
      # Restart nginx
      sudo nginx -t
      sudo service nginx restart
  fi
}

# TODO: We should split this function into one where the repo is added, and a
# second where the repo is installed
function install_hhvm() {
  local __path=$1
  local __config=$2
  local __multiservers=$3

  package software-properties-common

  log "Adding HHVM keys"
  sudo DEBIAN_FRONTEND=noninteractive apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0x5a16e7281be7a449
  sudo DEBIAN_FRONTEND=noninteractive apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0xB4112585D386EB94

  log "Adding HHVM repo"
  sudo DEBIAN_FRONTEND=noninteractive add-apt-repository "deb http://dl.hhvm.com/ubuntu xenial-lts-3.21 main"

  package_repo_update
  package hhvm

  log "Enabling HHVM to start by default"
  sudo update-rc.d hhvm defaults

  log "Copying HHVM configuration"
  if [[ "$__multiservers" == true ]]; then
    cat "$__path/extra/hhvm.conf" | sed "s|CTFPATH|$__path/|g" | sed "s|hhvm.server.ip|;hhvm.server.ip|g" | sed "s|hhvm.server.file_socket|;hhvm.server.file_socket|g" | sudo tee "$__config"
  else
    cat "$__path/extra/hhvm.conf" | sed "s|CTFPATH|$__path/|g" | sed "s|hhvm.server.port|;hhvm.server.port|g" | sudo tee "$__config"
  fi

  log "HHVM as PHP systemwide"
  sudo /usr/bin/update-alternatives --install /usr/bin/php php /usr/bin/hhvm 60

  log "PHP Alternaives:"
  sudo /usr/bin/update-alternatives --display php

  log "Restarting HHVM"
  sudo service hhvm restart

  log "PHP/HHVM Version:"
  php -v
  hhvm --version
}

function hhvm_performance() {
  local __path=$1
  local __config=$2
  local __oldrepo="/var/run/hhvm/hhvm.hhbc"
  local __repofile="/var/cache/hhvm/hhvm.hhbc"

  cat "$__config" | sed "s|$__oldrepo|$__repofile|g" | sudo tee "$__config"
  sudo hhvm-repo-mode enable "$__path"
  sudo chown www-data:www-data "$__repofile"
  sudo service hhvm restart
}

function install_composer() {
  local __path=$1

  cd $__path
  dl_pipe "https://getcomposer.org/installer" | php
  hhvm composer.phar install
  sudo mv composer.phar /usr/bin
  sudo chmod +x /usr/bin/composer.phar
}

function install_nodejs() {
  log "Downloading and setting node.js version 6.x repo information"
  dl_pipe "https://deb.nodesource.com/setup_6.x" | sudo -E bash -

  log "Installing node.js"
  package nodejs
}

function import_empty_db() {
  local __u="ctf"
  local __p="ctf"
  local __user=$1
  local __pwd=$2
  local __db=$3
  local __path=$4
  local __mode=$5
  local __multiservers=$6

  log "Creating DB - $__db"
  mysql -u "$__user" --password="$__pwd" -e "CREATE DATABASE IF NOT EXISTS \`$__db\`;"

  log "Importing schema..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/schema.sql;"
  log "Importing countries..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/countries.sql;"
  log "Importing logos..."
  mysql -u "$__user" --password="$__pwd" "$__db" -e "source $__path/database/logos.sql;"

  log "Creating user..."
  if [[ "$__multiservers == true" ]]; then
      mysql -u "$__user" --password="$__pwd" -e "CREATE USER '$__u'@'%' IDENTIFIED BY '$__p';" || true # don't fail if the user exists
      mysql -u "$__user" --password="$__pwd" -e "GRANT ALL PRIVILEGES ON \`$__db\`.* TO '$__u'@'%';"
  else
      mysql -u "$__user" --password="$__pwd" -e "CREATE USER '$__u'@'localhost' IDENTIFIED BY '$__p';" || true # don't fail if the user exists
      mysql -u "$__user" --password="$__pwd" -e "GRANT ALL PRIVILEGES ON \`$__db\`.* TO '$__u'@'localhost';"
  fi
  mysql -u "$__user" --password="$__pwd" -e "FLUSH PRIVILEGES;"

  local PASSWORD
  log "Adding default admin user"
  if [[ $__mode = "dev" ]]; then
    PASSWORD='password'
  else
    PASSWORD=$(head -c 500 /dev/urandom | md5sum | cut -d" " -f1)
  fi

  set_password "$PASSWORD" "$__user" "$__pwd" "$__db" "$__path" "$__multiservers"

  print_blank_lines
  ok_log "The password for admin is: $PASSWORD"
  if [[ "$__multiservers" == true ]]; then
      echo
      ok_log "Please note password as it will not be displayed again..."
      echo
      sleep 10
  fi
  print_blank_lines
}

function set_password() {
  local __admin_pwd=$1
  local __user=$2
  local __db_pwd=$3
  local __db=$4
  local __path=$5
  local __multiservers=$6

  if [[ "$__multiservers" == true ]]; then
      HASH=$(php "$__path/extra/hash.php" "$__admin_pwd")
  else
      HASH=$(hhvm -f "$__path/extra/hash.php" "$__admin_pwd")
  fi

  # First try to delete the existing admin user
  mysql -u "$__user" --password="$__db_pwd" "$__db" -e "DELETE FROM teams WHERE name='admin' AND admin=1;"

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
          sudo chmod -R 755 "$__ctf_path/"
      fi
  fi

  cd "$__ctf_path"
  composer.phar install

  run_grunt "$__ctf_path" "$__mode"
}

function quick_setup() {
  local __type=$1
  local __mode=$2
  local __ip=$3
  local __ip2=$4

  if [[ "$__type" = "install" ]]; then
    ./extra/provision.sh -m $__mode -s $PWD
  elif [[ "$__type" = "install_multi_mysql" ]]; then
    ./extra/provision.sh -m $__mode -s $PWD --multiple-servers --server-type mysql
  elif [[ "$__type" = "install_multi_hhvm" ]]; then
    ./extra/provision.sh -m $__mode -s $PWD --multiple-servers --server-type hhvm --mysql-server $__ip --cache-server $__ip2
  elif [[ "$__type" = "install_multi_nginx" ]]; then
    ./extra/provision.sh -m $__mode -s $PWD --multiple-servers --server-type nginx --hhvm-server $__ip
  elif [[ "$__type" = "install_multi_cache" ]]; then
    ./extra/provision.sh -m $__mode -s $PWD --multiple-servers --server-type cache
  elif [[ "$__type" = "start_docker" ]]; then
    package_repo_update
    package docker-ce
    sudo docker build --build-arg MODE=$__mode -t="fbctf-image" .
    sudo docker run --name fbctf -p 80:80 -p 443:443 fbctf-image
  elif [[ "$__type" = "start_docker_multi" ]]; then
    package_repo_update
    package python-pip
    sudo pip install docker-compose
    if [[ "$__mode" = "prod" ]]; then
      sed -i -e 's|      #  MODE: prod|        MODE: prod|g' ./docker-compose.yml
      sed -i -e 's|      #args|      args|g' ./docker-compose.yml
    elif [[ "$__mode" = "dev" ]]; then
      sed -i -e 's|        MODE: prod|      #  MODE: prod|g' ./docker-compose.yml
      sed -i -e 's|      args|      #args|g' ./docker-compose.yml
    fi
    sudo docker-compose up
  elif [[ "$__type" = "start_vagrant" ]]; then
    cp Vagrantfile-single Vagrantfile
    export FBCTF_PROVISION_ARGS="-m $__mode"
    vagrant up
  elif [[ "$__type" = "start_vagrant_multi" ]]; then
    cp Vagrantfile-multi Vagrantfile
    export FBCTF_PROVISION_ARGS="-m $__mode"
    vagrant up
  fi
}

