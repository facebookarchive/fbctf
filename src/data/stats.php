<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class StatsController extends DataController {

  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();
    SessionUtils::enforceAdmin();

    $stats = array();

    $awaitables = Map {
      'team_stats' => MultiTeam::genAllTeamsCache(),
      'session_stats' => Session::genAllSessions(),
      'level_stats' => Level::genAllLevels(),
      'active_level_stats' => Level::genAllActiveLevels(),
      'hint_stats' => HintLog::genAllHints(),
      'capture_stats' => ScoreLog::genAllScores(),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $team_stats = $awaitables['team_stats'];
    $session_stats = $awaitables['session_stats'];
    $level_stats = $awaitables['level_stats'];
    $active_level_stats = $awaitables['active_level_stats'];
    $hint_stats = $awaitables['hint_stats'];
    $capture_stats = $awaitables['capture_stats'];

    // Number of teams
    $stats['teams'] = count($team_stats);

    // Number of active sessions
    $stats['sessions'] = count($session_stats);

    // Number of levels
    $stats['levels'] = count($level_stats);

    // Number of active levels
    $stats['active_levels'] = count($active_level_stats);

    // Number of captures
    $stats['hints'] = count($hint_stats);

    // Number of hints
    $stats['captures'] = count($capture_stats);

    // AsyncMysqlConnectionPool Stats
    $stats['database'] = Db::getDatabaseStats();

    // Memcached Stats
    $stats['memcached'] = Model::getMemcachedStats();

    // System load average
    $stats['load'] = sys_getloadavg();

    // System CPU stats
    $cpu_stats_1 = file('/proc/stat');
    sleep(1);
    $cpu_stats_2 = file('/proc/stat');
    $cpu_info_1 = explode(" ", preg_replace("!cpu +!", "", $cpu_stats_1[0]));
    $cpu_info_2 = explode(" ", preg_replace("!cpu +!", "", $cpu_stats_2[0]));
    $cpu_diff = array();
    $cpu_diff['user'] = $cpu_info_2[0] - $cpu_info_1[0];
    $cpu_diff['nice'] = $cpu_info_2[1] - $cpu_info_1[1];
    $cpu_diff['sys'] = $cpu_info_2[2] - $cpu_info_1[2];
    $cpu_diff['idle'] = $cpu_info_2[3] - $cpu_info_1[3];
    $cpu_total = array_sum($cpu_diff);
    $cpu_stats = array();
    foreach ($cpu_diff as $x => $y)
      $cpu_stats[$x] = round($y / $cpu_total * 100, 1);
    $stats['cpu'] = $cpu_stats;

    $this->jsonSend($stats);
  }
}

/* HH_IGNORE_ERROR[1002] */
$statsController = new StatsController();
$statsController->sendData();
