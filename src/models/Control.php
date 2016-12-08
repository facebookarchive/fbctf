<?hh // strict

class Control extends Model {

  protected static string $MC_KEY = 'control:';

  protected static Map<string, string>
    $MC_KEYS = Map {'ALL_ACTIVITY' => 'activity'};

  public static async function genStartScriptLog(
    int $pid,
    string $name,
    string $cmd,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO scripts (ts, pid, name, cmd, status) VALUES (NOW(), %d, %s, %s, 1)',
      $pid,
      $name,
      $cmd,
    );
  }

  public static async function genStopScriptLog(int $pid): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE scripts SET status = 0 WHERE pid = %d LIMIT 1',
      $pid,
    );
  }

  public static async function genScriptPid(string $name): Awaitable<int> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT pid FROM scripts WHERE name = %s AND status = 1 LIMIT 1',
      $name,
    );
    return intval(must_have_idx($result->mapRows()[0], 'pid'));
  }

  public static async function genClearScriptLog(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM scripts WHERE id > 0 AND status = 0');
  }

  public static async function genBegin(): Awaitable<void> {
    // Disable registration
    await Configuration::genUpdate('registration', '0');

    // Reset all points
    await Team::genResetAllPoints();

    // Clear scores log
    await ScoreLog::genResetScores();

    // Clear hints log
    await HintLog::genResetHints();

    // Clear failures log
    await FailureLog::genResetFailures();

    // Clear bases log
    await self::genResetBases();
    await self::genClearScriptLog();

    // Mark game as started
    await Configuration::genUpdate('game', '1');

    // Enable scoring
    await Configuration::genUpdate('scoring', '1');

    // Take timestamp of start
    $start_ts = time();
    await Configuration::genUpdate('start_ts', strval($start_ts));

    // Calculate timestamp of the end
    $config = await Configuration::gen('game_duration_value');
    $duration_value = intval($config->getValue());
    $config = await Configuration::gen('game_duration_unit');
    $duration_unit = $config->getValue();
    switch ($duration_unit) {
      case 'd':
        $duration = $duration_value * 60 * 60 * 24;
        break;
      case 'h':
        $duration = $duration_value * 60 * 60;
        break;
      case 'm':
        $duration = $duration_value * 60;
        break;
    }
    $end_ts = $start_ts + $duration;
    await Configuration::genUpdate('end_ts', strval($end_ts));

    // Set pause to zero
    await Configuration::genUpdate('pause_ts', '0');

    // Set game to not paused
    await Configuration::genUpdate('game_paused', '0');

    // Kick off timer
    await Configuration::genUpdate('timer', '1');

    // Reset and kick off progressive scoreboard
    await Progressive::genReset();
    await Progressive::genRun();

    // Kick off scoring for bases
    await Level::genBaseScoring();
  }

  public static async function genEnd(): Awaitable<void> {
    // Mark game as finished and it stops progressive scoreboard
    await Configuration::genUpdate('game', '0');

    // Disable scoring
    await Configuration::genUpdate('scoring', '0');

    // Put timestampts to zero
    await Configuration::genUpdate('start_ts', '0');
    await Configuration::genUpdate('end_ts', '0');

    // Set pause to zero
    await Configuration::genUpdate('pause_ts', '0');

    // Stop timer
    await Configuration::genUpdate('timer', '0');

    $pause = await Configuration::gen('game_paused');
    $game_paused = $pause->getValue() === '1';

    if (!$game_paused) {
      // Stop bases scoring process
      await Level::genStopBaseScoring();

      // Stop progressive scoreboard process
      await Progressive::genStop();
    } else {
      // Set game to not paused
      await Configuration::genUpdate('game_paused', '0');
    }
  }

  public static async function genPause(): Awaitable<void> {
    // Disable scoring
    await Configuration::genUpdate('scoring', '0');

    // Set pause timestamp
    $pause_ts = time();
    await Configuration::genUpdate('pause_ts', strval($pause_ts));

    // Set gane to paused
    await Configuration::genUpdate('game_paused', '1');

    // Stop timer
    await Configuration::genUpdate('timer', '0');

    // Stop bases scoring process
    await Level::genStopBaseScoring();

    // Stop progressive scoreboard process
    await Progressive::genStop();
  }

  public static async function genUnpause(): Awaitable<void> {
    // Enable scoring
    await Configuration::genUpdate('scoring', '1');

    // Get pause time
    $config_pause_ts = await Configuration::gen('pause_ts');
    $pause_ts = intval($config_pause_ts->getValue());

    // Get start time
    $config_start_ts = await Configuration::gen('start_ts');
    $start_ts = intval($config_start_ts->getValue());

    // Get end time
    $config_end_ts = await Configuration::gen('end_ts');
    $end_ts = intval($config_end_ts->getValue());

    // Calulcate game remaining
    $game_duration = $end_ts - $start_ts;
    $game_played_duration = $pause_ts - $start_ts;
    $remaining_duration = $game_duration - $game_played_duration;
    $end_ts = time() + $remaining_duration;

    // Set new endtime
    await Configuration::genUpdate('end_ts', strval($end_ts));

    // Set pause to zero
    await Configuration::genUpdate('pause_ts', '0');

    // Set gane to not paused
    await Configuration::genUpdate('game_paused', '0');

    // Start timer
    await Configuration::genUpdate('timer', '1');

    // Kick off progressive scoreboard
    await Progressive::genRun();

    // Kick off scoring for bases
    await Level::genBaseScoring();
  }

  public static async function importGame(): Awaitable<bool> {
    $data_game = JSONImporterController::readJSON('game_file');
    if (is_array($data_game)) {
      $logos = array_pop(must_have_idx($data_game, 'logos'));
      if (!$logos) {
        return false;
      }
      $logos_result = await Logo::importAll($logos);
      if (!$logos_result) {
        return false;
      }
      $teams = array_pop(must_have_idx($data_game, 'teams'));
      if (!$teams) {
        return false;
      }
      $teams_result = await Team::importAll($teams);
      if (!$teams_result) {
        return false;
      }
      $categories = array_pop(must_have_idx($data_game, 'categories'));
      if (!$categories) {
        return false;
      }
      $categories_result = await Category::importAll($categories);
      if (!$categories_result) {
        return false;
      }
      $levels = array_pop(must_have_idx($data_game, 'levels'));
      if (!$levels) {
        return false;
      }
      $levels_result = await Level::importAll($levels);
      if (!$levels_result) {
        return false;
      }
      return true;
    }
    return false;
  }

  public static async function importTeams(): Awaitable<bool> {
    $data_teams = JSONImporterController::readJSON('teams_file');
    if (is_array($data_teams)) {
      $teams = must_have_idx($data_teams, 'teams');
      return await Team::importAll($teams);
    }
    return false;
  }

  public static async function importLogos(): Awaitable<bool> {
    $data_logos = JSONImporterController::readJSON('logos_file');
    if (is_array($data_logos)) {
      $logos = must_have_idx($data_logos, 'logos');
      return await Logo::importAll($logos);
    }
    return false;
  }

  public static async function importLevels(): Awaitable<bool> {
    $data_levels = JSONImporterController::readJSON('levels_file');
    if (is_array($data_levels)) {
      $levels = must_have_idx($data_levels, 'levels');
      return await Level::importAll($levels);
    }
    return false;
  }

  public static async function importCategories(): Awaitable<bool> {
    $data_categories = JSONImporterController::readJSON('categories_file');
    if (is_array($data_categories)) {
      $categories = must_have_idx($data_categories, 'categories');
      return await Category::importAll($categories);
    }
    return false;
  }

  public static async function exportGame(): Awaitable<void> {
    $game = array();
    $logos = await Logo::exportAll();
    $game['logos'] = $logos;
    $teams = await Team::exportAll();
    $game['teams'] = $teams;
    $categories = await Category::exportAll();
    $game['categories'] = $categories;
    $levels = await Level::exportAll();
    $game['levels'] = $levels;
    $output_file = 'fbctf_game.json';
    JSONExporterController::sendJSON($game, $output_file);
    exit();
  }

  public static async function exportTeams(): Awaitable<void> {
    $teams = await Team::exportAll();
    $output_file = 'fbctf_teams.json';
    JSONExporterController::sendJSON($teams, $output_file);
    exit();
  }

  public static async function exportLogos(): Awaitable<void> {
    $logos = await Logo::exportAll();
    $output_file = 'fbctf_logos.json';
    JSONExporterController::sendJSON($logos, $output_file);
    exit();
  }

  public static async function exportLevels(): Awaitable<void> {
    $levels = await Level::exportAll();
    $output_file = 'fbctf_levels.json';
    JSONExporterController::sendJSON($levels, $output_file);
    exit();
  }

  public static async function exportCategories(): Awaitable<void> {
    $categories = await Category::exportAll();
    $output_file = 'fbctf_categories.json';
    JSONExporterController::sendJSON($categories, $output_file);
    exit();
  }

  public static function backupDb(): void {
    $filename = 'fbctf-backup-'.date("d-m-Y").'.sql.gz';
    header('Content-Type: application/x-gzip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $cmd = Db::getInstance()->getBackupCmd().' | gzip --best';
    passthru($cmd);
  }

  public static async function genAllActivity(
    bool $refresh = false,
  ): Awaitable<Vector<Map<string, string>>> {
    $mc_result = self::getMCRecords('ALL_ACTIVITY');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $result =
        await $db->queryf(
          'SELECT scores_log.ts AS time, teams.name AS team, countries.iso_code AS country, scores_log.team_id AS team_id FROM scores_log, levels, teams, countries WHERE scores_log.level_id = levels.id AND levels.entity_id = countries.id AND scores_log.team_id = teams.id AND teams.visible = 1 ORDER BY time DESC LIMIT 50',
        );
      self::setMCRecords('ALL_ACTIVITY', $result->mapRows());
      return $result->mapRows();
    }
    invariant(
      $mc_result instanceof Vector,
      'cache return should be of type Vector',
    );
    return $mc_result;
  }

  public static async function genResetBases(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM bases_log WHERE id > 0');
  }
}
