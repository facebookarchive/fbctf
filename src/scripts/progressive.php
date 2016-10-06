<?hh

require_once('/var/www/fbctf/src/Db.php');
require_once('/var/www/fbctf/src/Utils.php');
require_once('/var/www/fbctf/src/models/Model.php');
require_once('/var/www/fbctf/src/models/Configuration.php');
require_once('/var/www/fbctf/src/models/Progressive.php');

while (\HH\Asio\join(Progressive::genGameStatus())) {
  \HH\Asio\join(Progressive::genTake());
  sleep(\HH\Asio\join(Progressive::genCycle()));
}
