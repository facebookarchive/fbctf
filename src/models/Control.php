<?hh // strict

class Control extends Model {
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
    $config = await Configuration::gen('game_duration');
    $duration = intval($config->getValue());
    $end_ts = $start_ts + $duration;
    await Configuration::genUpdate('end_ts', strval($end_ts));

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

    // Stop timer
    await Configuration::genUpdate('timer', '0');

    // Stop bases scoring process
    await Level::genStopBaseScoring();

    // Stop progressive scoreboard process
    await Progressive::genStop();
  }

  // Helper function to read JSON file to import
  public static function readJSON(string $file_name): mixed {
    $files = Utils::getFILES();
    if ($files->contains($file_name)) {
      $input_filename = $files[$file_name]['tmp_name'];
      $data_raw = json_decode(file_get_contents($input_filename), true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
      }
      return $data_raw;
    }
    return false;
  }

  public static async function importGame(): Awaitable<bool> {
    $data_game = self::readJSON('game_file');
    if ($data_game) {
      $logos = must_have_idx($data_game, 'logos');
      $logos_result = await Logo::import($logos);
      if (!$logos_result) {
        return false;
      }
      $teams = must_have_idx($data_game, 'teams');
      $teams_result = await Team::import($teams);
      if (!$teams_result) {
        return false;
      }
      $categories = must_have_idx($data_game, 'categories');
      $categories_result = await Category::import($categories);
      if (!$categories_result) {
        return false;
      }
      $levels = must_have_idx($data_game, 'levels');
      $levels_result = await Level::import($levels);
      if (!$levels_result) {
        return false;
      }
      return true;
    }
    return false;
  }

  public static async function importTeams(): Awaitable<bool> {
    $data_teams = self::readJSON('teams_file');
    if ($data_teams) {
      $teams = must_have_idx($data_teams, 'teams');
      return await Team::import($teams);
    }
    return false;
  }

  public static async function importLogos(): Awaitable<bool> {
    $data_logos = self::readJSON('logos_file');
    if ($data_logos) {
      $logos = must_have_idx($data_logos, 'logos');
      return await Logo::import($logos);
    }
    return false;
  }

  public static async function importLevels(): Awaitable<bool> {
    $data_levels = self::readJSON('levels_file');
    if ($data_levels) {
      $levels = must_have_idx($data_levels, 'levels');
      return await Level::import($levels);
    }
    return false;
  }

  public static async function importCategories(): Awaitable<bool> {
    $data_categories = self::readJSON('categories_file');
    if ($data_categories) {
      $categories = must_have_idx($data_categories, 'categories');
      return await Category::import($categories);
    }
    return false;
  }

  public static async function exportGame(): Awaitable<void> {
    $game = array();
    $logos = await Logo::export();
    $game['logos'] = $logos;
    $teams = await Team::export();
    $game['teams'] = $teams;
    $categories = await Category::export();
    $game['categories'] = $categories;
    $levels = await Level::export();
    $game['levels'] = $levels;
    $output_file = 'fbctf_game.json';
    self::sendJSON($game, $output_file);
    exit();
  }

  public static function genJSON(mixed $data): string { 
    return json_encode($data, JSON_PRETTY_PRINT);   
  }
  
  public static function sendJSON(mixed $data, string $json_file='fbctf.json'): void {
    header('Content-Type: application/json;charset=utf-8');
    header('Content-Disposition: attachment; filename='.$json_file);
    echo self::genJSON($data);
  }

  public static async function exportTeams(): Awaitable<void> {
    $teams = await Team::export();
    $output_file = 'fbctf_teams.json';
    self::sendJSON($teams, $output_file);
    exit();
  }

  public static async function exportLogos(): Awaitable<void> {
    $logos = await Logo::export();
    $output_file = 'fbctf_logos.json';
    self::sendJSON($logos, $output_file);
    exit();
  }

  public static async function exportLevels(): Awaitable<void> {
    $levels = await Level::export();
    $output_file = 'fbctf_levels.json';
    self::sendJSON($levels, $output_file);
    exit();
  }

  public static async function exportCategories(): Awaitable<void> {
    $categories = await Category::export();
    $output_file = 'fbctf_categories.json';
    self::sendJSON($categories, $output_file);
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
  ): Awaitable<Vector<Map<string, string>>> {
    $db = await self::genDb();
    $result =
      await $db->queryf(
        'SELECT scores_log.ts AS time, teams.name AS team, countries.iso_code AS country, scores_log.team_id AS team_id FROM scores_log, levels, teams, countries WHERE scores_log.level_id = levels.id AND levels.entity_id = countries.id AND scores_log.team_id = teams.id AND teams.visible = 1 ORDER BY time DESC LIMIT 50',
      );
    return $result->mapRows();
  }

  public static async function genResetBases(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM bases_log WHERE id > 0');
  }
}
