<?hh

if (php_sapi_name() !== 'cli') {
  http_response_code(405); // method not allowed
  exit(0);
}

require_once ('/var/www/fbctf/vendor/autoload.php');

while (\HH\Asio\join(Progressive::genGameStatus())) {
  \HH\Asio\join(Progressive::genTake());
  sleep(\HH\Asio\join(Progressive::genCycle()));
}
