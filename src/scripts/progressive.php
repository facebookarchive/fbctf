<?hh

if (php_sapi_name() !== 'cli') {
  http_response_code(405); // method not allowed
  exit(0);
}
require_once (__DIR__.'/../Db.php');
require_once (__DIR__.'/../Utils.php');
require_once (__DIR__.'/../models/Model.php');
require_once (__DIR__.'/../models/Cache.php');
require_once (__DIR__.'/../models/Configuration.php');
require_once (__DIR__.'/../models/Progressive.php');

while (\HH\Asio\join(Progressive::genGameStatus())) {
  \HH\Asio\join(Progressive::genTake());
  sleep(\HH\Asio\join(Progressive::genCycle()));
}
