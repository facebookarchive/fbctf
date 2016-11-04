#!/usr/bin/env python2

import requests
import grequests
import json
import time

interval = 1
url = 'https://10.10.10.5'

verify = False

endpoints = [
    '/index.php?p=game',
    '/index.php?p=scoreboard&modal=scoreboard',
    '/data/scores.php',
    '/data/configuration.php',
    '/data/country-data.php',
    '/data/map-data.php',
    '/data/teams.php',
    '/data/command-line.php',
    '/inc/gameboard/modules/announcements.php',
    '/inc/gameboard/modules/filter.php',
    '/inc/gameboard/modules/activity.php',
    '/inc/gameboard/modules/teams.php',
    '/inc/gameboard/modules/leaderboard.php',
    '/inc/gameboard/modules/game-clock.php',
]

def check_ok(r, *args, **kwargs):
    if r.status_code != 200:
        print '[!] Received bad status code: ' + r.status_code

def exception(r, e):
    print '[!] Request failed: ' + str(e)

def login():
    s = requests.Session()
    uri = url + '/index.php?ajax=true'
    data = {
        'action': 'login_team',
        'team_id': '1',
        'teamname': 'admin',
        'password': 'password',
    }
    r = s.post(uri, data=data, verify=verify)
    res = json.loads(r.content)
    if res['result'] == 'OK':
        print '[+] Logged in successfully'
    else:
        print '[!] Log in failed, exiting'
        exit(1)

    return s

def main():
    s = login()

    rs = [grequests.get(url + endpoint, callback=check_ok, session=s, verify=verify)
          for endpoint in endpoints]
    while True:
        print '[+] Sending %d requests...' % len(rs)
        start_time = time.time()
        grequests.map(rs)
        duration = time.time() - start_time
        print '[+] done in %d seconds' % duration
        time.sleep(interval)

if __name__ == '__main__':
    main()
