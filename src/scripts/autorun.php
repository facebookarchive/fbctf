<?hh

if (php_sapi_name() !== 'cli') {
  http_response_code(405); // method not allowed
  exit(0);
}

require_once (__DIR__.'/../Db.php');
require_once (__DIR__.'/../Utils.php');
require_once (__DIR__.'/../models/Model.php');
require_once (__DIR__.'/../models/Cache.php');
require_once (__DIR__.'/../models/Importable.php');
require_once (__DIR__.'/../models/Exportable.php');
require_once (__DIR__.'/../models/Level.php');
require_once (__DIR__.'/../models/Progressive.php');
require_once (__DIR__.'/../models/Configuration.php');
require_once (__DIR__.'/../models/Control.php');
require_once (__DIR__.'/../models/Team.php');
require_once (__DIR__.'/../models/MultiTeam.php');
require_once (__DIR__.'/../models/ScoreLog.php');
require_once (__DIR__.'/../models/HintLog.php');
require_once (__DIR__.'/../models/FailureLog.php');
require_once (__DIR__.'/../models/Announcement.php');
require_once (__DIR__.'/../models/ActivityLog.php');

while (1) {
  \HH\Asio\join(Control::genAutoRun());

  $conf_sleep = \HH\Asio\join(Configuration::gen('autorun_cycle'));
  $conf_sleep_secs = intval($conf_sleep->getValue());
  $sleep = $conf_sleep_secs;
  $conf_game = \HH\Asio\join(Configuration::gen('game'));
  $config_start_ts = \HH\Asio\join(Configuration::gen('start_ts'));
  $start_ts = intval($config_start_ts->getValue());
  $config_end_ts = \HH\Asio\join(Configuration::gen('end_ts'));
  $end_ts = intval($config_end_ts->getValue());

  if (($conf_game->getValue() === '1') &&
      (($end_ts - time()) < $conf_sleep_secs)) {
    $sleep = $end_ts - time();
  } else if (($conf_game->getValue() === '0') &&
             (($start_ts - time()) < $conf_sleep_secs)) {
    $sleep = $start_ts - time();
  }

  if ($sleep < 0) {
    $sleep = $conf_sleep_secs;
  }

  sleep($sleep);
}
