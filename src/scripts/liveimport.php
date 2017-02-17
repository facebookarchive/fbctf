<?hh

if (php_sapi_name() !== 'cli') {
  http_response_code(405); // method not allowed
  exit(0);
}

require_once (__DIR__.'/../Db.php');
require_once (__DIR__.'/../Utils.php');
require_once (__DIR__.'/../models/Model.php');
require_once (__DIR__.'/../models/Importable.php');
require_once (__DIR__.'/../models/Exportable.php');
require_once (__DIR__.'/../models/Level.php');
require_once (__DIR__.'/../models/Team.php');
require_once (__DIR__.'/../models/ScoreLog.php');
require_once (__DIR__.'/../models/HintLog.php');
require_once (__DIR__.'/../models/Category.php');
require_once (__DIR__.'/../models/Configuration.php');
require_once (__DIR__.'/../models/Country.php');
require_once (__DIR__.'/../models/Control.php');
require_once (__DIR__.'/../models/MultiTeam.php');
require_once (__DIR__.'/../models/Model.php');

if ($argc < 2) {
  print
    'Usage:\n\thhvm -vRepo.Central.Path=/var/run/hhvm/.hhvm.hhbc_liveimport '.
    $argv[0].
    ' <Space Seperated Sync URLs> <Time to Sleep Between Cycles> [Disable SSL Certification] [Debug]\n'
  ;
  exit;
}

class LiveSyncImport {
  public static async function genProcess(
    string $urls_string,
    bool $check_certificates,
    bool $debug,
  ): Awaitable<void> {
    $urls = explode(' ', $urls_string);
    foreach ($urls as $url) {
      $json = await self::genDownloadData($url, $check_certificates);
      $data = json_decode($json);
      if (!empty($data)) {
        foreach ($data as $level) {
          $level_id = await self::genLevel($url, $level, $debug);
          $teams =
            await self::genTeamCaptures($url, $level, $level_id, $debug);
          await self::genRecalculateScores(
            $url,
            $level,
            $level_id,
            $teams,
            $debug,
          );
        }
      }
    }
  }

  public static async function genDownloadData(
    string $url,
    bool $check_certificates,
  ): Awaitable<string> {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if ($check_certificates === false) {
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    }
    $json = curl_exec($curl);
    curl_close($curl);
    if ($json === false) {
      self::debug(true, $url, '!!!', "Download Error: ".curl_error($curl));
      return '';
    }
    return $json;
  }

  public static async function genLevel(
    string $url,
    stdClass $level,
    bool $debug,
  ): Awaitable<int> {
    $level_exists = await self::genLevelExists($level);
    if ($level_exists === false) {
      $level->entity_iso_code = await self::genCountry($url, $level, $debug);
    }
    $level_exists = await self::genLevelExists($level);
    if ($level_exists === false) {
      $category_id = await self::genCategory($url, $level, $debug);
      $country = await Country::genCountry(strval($level->entity_iso_code));
      $country_id = $country->getId();
      $level_id = await Level::genCreate(
        strval($level->type),
        strval($level->title),
        strval($level->description),
        $country_id,
        $category_id,
        intval($level->points),
        intval($level->bonus),
        intval($level->bonus_dec),
        intval($level->bonus),
        Team::generateHash(random_bytes(100)),
        '',
        intval($level->penalty),
      );
      $level_active = (intval($level->active) === 1) ? true : false;
      await Level::genSetStatus($level_id, $level_active);
      self::debug(
        $debug,
        $url,
        '+++',
        "Level Created: ".strval($level->title),
      );
    } else {
      $level_id = await Level::getLevelIdByTypeTitleCountry(
        strval($level->type),
        strval($level->title),
        strval($level->entity_iso_code),
      );
      $level_active = (intval($level->active) === 1) ? true : false;
      await Level::genSetStatus($level_id, $level_active);
      self::debug(
        $debug,
        $url,
        '===',
        "Level Exists: ".strval($level->title),
      );
    }
    return intval($level_id);
  }

  public static async function genLevelExists(
    stdClass $level,
  ): Awaitable<bool> {
    $level_exists = await Level::genAlreadyExist(
      strval($level->type),
      strval($level->title),
      strval($level->entity_iso_code),
    );
    return $level_exists;
  }

  public static async function genCountry(
    string $url,
    stdClass $level,
    bool $debug,
  ): Awaitable<string> {
    $country = await Country::genCountry(strval($level->entity_iso_code));
    $country_used = $country->getUsed();
    if ($country_used === true) {
      $level_exists = await Level::genAlreadyExistUnknownCountry(
        strval($level->type),
        strval($level->title),
        strval($level->description),
        intval($level->points),
      );
      if ($level_exists === false) {
        $countries = await Country::genAllAvailableCountries();
        $new_country = $countries[array_rand($countries)];
        self::debug(
          $debug,
          $url,
          '+++',
          "Country Selected: ".
          strval($level->title).
          " - ".
          strval($new_country->getIsoCode()),
        );
        return strval($new_country->getIsoCode());
      } else {
        $level_exists = true;
        $existing_level = await Level::genLevelUnknownCountry(
          strval($level->type),
          strval($level->title),
          strval($level->description),
          intval($level->points),
        );
        $new_country = await Country::gen($existing_level->getEntityId());
        self::debug(
          $debug,
          $url,
          '===',
          "Country Found: ".
          strval($level->title).
          " - ".
          strval($new_country->getIsoCode()),
        );
        return strval($new_country->getIsoCode());
      }
    }
    return strval($level->entity_iso_code);
  }

  public static async function genCategory(
    string $url,
    stdClass $level,
    bool $debug,
  ): Awaitable<int> {
    $category_exists =
      await Category::genCheckExists(strval($level->category));
    if ($category_exists === false) {
      $category_id =
        await Category::genCreate(strval($level->category), false);
      self::debug(
        $debug,
        $url,
        '+++',
        "Category Created: ".strval($level->category),
      );
    } else {
      $category =
        await Category::genSingleCategoryByName(strval($level->category));
      $category_id = $category->getId();
      self::debug(
        $debug,
        $url,
        '===',
        "Category Exists: ".strval($level->category),
      );
    }
    return intval($category_id);
  }

  public static async function genTeamCaptures(
    string $url,
    stdClass $level,
    int $level_id,
    bool $debug,
  ): Awaitable<array> {
    $teams = json_decode(json_encode($level->teams), true);
    uasort(
      $teams,
      function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
      },
    );
    $teams_array = array();
    foreach ($teams as $team_livesync_key => $team_data) {
      $team_exists =
        await Team::genLiveSyncKeyExists(strval($team_livesync_key));
      if ($team_exists === true) {
        $team =
          await Team::genTeamFromLiveSyncKey(strval($team_livesync_key));
        $team_id = $team->getId();
        $hint_used = await self::genLogHint(
          $url,
          $level,
          $level_id,
          $team_id,
          intval($team_data['hint']),
          $debug,
        );
        await self::genScoreLevel(
          $url,
          $level,
          $level_id,
          $team_id,
          intval($team_data['capture']),
          strval($team_data['timestamp']),
          $debug,
        );
        await self::genUpdateTeamScores(
          $url,
          $level,
          $team_id,
          intval($team_data['hint']),
          $hint_used,
          $debug,
        );
        $teams_array[$team_id] = $team_data;
      } else {
        self::debug(
          $debug,
          $url,
          '!!!',
          "Team Not Found: Key: (".
          strval($team_livesync_key).
          ") ".
          strval($level->title),
        );
      }
    }
    return $teams_array;
  }

  public static async function genLogHint(
    string $url,
    stdClass $level,
    int $level_id,
    int $team_id,
    int $hint,
    bool $debug,
  ): Awaitable<bool> {
    $team = await Team::genTeam($team_id);
    $team_name = $team->getName();
    $hint_used = false;
    if ($hint === 1) {
      $hint_used = await HintLog::genPreviousHint($level_id, $team_id, false);
      if ($hint_used === false) {
        await HintLog::genLogGetHint(
          $level_id,
          $team_id,
          intval($level->penalty),
        );
        self::debug(
          $debug,
          $url,
          '+++',
          "Hint Used: ".strval($team_name)." - ".strval($level->title),
        );
        return true;
      } else {
        self::debug(
          $debug,
          $url,
          '===',
          "Hint Already Used: ".
          strval($team_name).
          " - ".
          strval($level->title),
        );
        return false;
      }
    }
    return false;
  }

  public static async function genScoreLevel(
    string $url,
    stdClass $level,
    int $level_id,
    int $team_id,
    int $capture,
    string $timestamp,
    bool $debug,
  ): Awaitable<void> {
    if ($capture === 1) {
      $team = await Team::genTeam($team_id);
      $team_name = $team->getName();
      $level_capture = await Level::genScoreLevel($level_id, $team_id);
      if ($level_capture === true) {
        $scorelog = await ScoreLog::genLevelScoreByTeam($team_id, $level_id);
        await ScoreLog::genScoreLogUpdate(
          $level_id,
          $team_id,
          $scorelog->getPoints(),
          $level->type,
          $timestamp,
        );
      }
      if ($level_capture === true) {
        self::debug(
          $debug,
          $url,
          '+++',
          "Level Captured: ".strval($team_name)." - ".strval($level->title),
        );
      } else {
        self::debug(
          $debug,
          $url,
          '===',
          "Level Already Captured: ".
          strval($team_name).
          " - ".
          strval($level->title),
        );
      }
    }
  }

  public static async function genUpdateTeamScores(
    string $url,
    stdClass $level,
    int $team_id,
    int $hint,
    bool $hint_used,
    bool $debug,
  ): Awaitable<void> {
    if (($hint === 1) && ($hint_used === true)) {
      $team = await Team::genTeam($team_id);
      $team_name = $team->getName();
      await Team::genUpdate(
        strval($team_name),
        $team->getLogo(),
        $team->getPoints() - intval($level->penalty),
        $team_id,
      );
    }
  }

  public static async function genRecalculateScores(
    string $url,
    stdClass $level,
    int $level_id,
    array $teams,
    bool $debug,
  ): Awaitable<void> {
    $level_captured = 0;
    $current_level = await Level::gen($level_id);
    foreach ($teams as $team_id => $team_data) {
      if (intval($team_data['capture']) === 0) {
        continue;
      }
      $team = await Team::genTeam($team_id);
      $team_name = $team->getName();
      $current_bonus =
        $level->bonus - (intval($level->bonus_dec) * $level_captured);
      if ($current_bonus < 0) {
        $current_bonus = 0;
      }
      $points = intval($level->points) + $current_bonus;
      $scorelog = await ScoreLog::genLevelScoreByTeam($team_id, $level_id);
      $existing_points = $scorelog->getPoints();
      $total_points = $team->getPoints();
      $total_points += $points - $existing_points;
      await Team::genTeamUpdatePoints($team_id, $total_points);
      await ScoreLog::genUpdateScoreLogBonus($level_id, $team_id, $points);
      $level_captured++;
    }
    if ($level_captured > 0) {
      self::debug(
        $debug,
        $url,
        '+++',
        "Level Bonuses Recalculated: ".strval($level->title),
      );
    } else {
      self::debug(
        $debug,
        $url,
        '===',
        "Level Bonuses Correct: ".strval($level->title),
      );
    }
  }

  public static function debug(
    bool $debug,
    string $url,
    string $indicator,
    string $message,
  ): void {
    if ($debug === true) {
      print "[$url] $indicator $message\n";
    }
  }
}

$urls_string = $argv[1];
$sleep = (isset($argv[2])) ? intval($argv[2]) : 300;
$check_certificates = (isset($argv[3])) ? false : true;
$debug = (isset($argv[4])) ? true : false;

while (1) {
  \HH\Asio\join(
    LiveSyncImport::genProcess($urls_string, $check_certificates, $debug),
  );
  sleep($sleep);
}
