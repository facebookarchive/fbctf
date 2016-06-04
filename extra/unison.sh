#!/usr/bin/env bash
#
# Facebook CTF: Script to install unison 2.48.3
# https://keylocation.sg/blog/vagrant-and-unison-without-a-plugin/
#
# Usage: ./unison.sh [path_to_ctf_code] [unison_full_path]
#

if [[ "$#" -lt 1 ]]; then
  echo "[!] Need path to CTF folder"
  exit 1
fi

CODE_PATH="$1"
UNISON="$2"

# Make sure the right version is installed
if [[ "$($UNISON -version | awk '{print $3}')" != "2.48.3" ]]; then
  echo "Sorry, you need unison 2.48.3"
  exit 1
fi

# Generate ssh-config file from vagrant
echo "[+] Generating SSH config"
SSH_CONFIG="$CODE_PATH/.vagrant/ssh-config"
vagrant ssh-config > "$SSH_CONFIG"

# Create unison profile
echo "[+] Creating unison profile"
PROFILE="
root = $CODE_PATH
root = ssh://default//var/www/fbctf/
ignore = Name {.vagrant,.DS_Store,.sources,node_modules,settings.ini}

prefer = $CODE_PATH
repeat = 2
terse = true
dontchmod = false
perms = 0
sshargs = -F $SSH_CONFIG
"

# Write profile
if [[ -z ${USERPROFILE+x} ]]; then
  UNISONDIR=$HOME
else
  UNISONDIR=$USERPROFILE
fi

cd $UNISONDIR
[[ -d "$UNISONDIR/.unison" ]] || mkdir "$UNISONDIR/.unison"
echo "$PROFILE" > "$UNISONDIR/.unison/fbctf.prf"

echo "[+] Sync'ing project in the background..."
$UNISON "fbctf" &

echo "[+] Done"
exit 0
#kthxbai
