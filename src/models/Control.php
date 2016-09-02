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

  public static async function importGame(): Awaitable<bool> {
    $files = Utils::getFILES();
    if ($files->contains('game_file')) {
      $importer = new JSONImporterController();
      $result = await $importer->importGame($files['game_file']['tmp_name']);
      return $result;
    } else {
      return false;
    }
  }

  public static async function importTeams(): Awaitable<bool> {
    $files = Utils::getFILES();
    if ($files->contains('teams_file')) {
      $importer = new JSONImporterController();
      $result = await $importer->importTeams($files['teams_file']['tmp_name']);
      return $result;
    } else {
      return false;
    }
  }

  public static async function importLogos(): Awaitable<bool> {
    $files = Utils::getFILES();
    if ($files->contains('logos_file')) {
      $importer = new JSONImporterController();
      $result = await $importer->importLogos($files['logos_file']['tmp_name']);
      return $result;
    } else {
      return false;
    }
  }

  public static async function importLevels(): Awaitable<bool> {
    $files = Utils::getFILES();
    if ($files->contains('levels_file')) {
      $importer = new JSONImporterController();
      $result = await $importer->importLevels($files['levels_file']['tmp_name']);
      return $result;
    } else {
      return false;
    }
  }

  public static async function importCategories(): Awaitable<bool> {
    $files = Utils::getFILES();
    if ($files->contains('categories_file')) {
      $importer = new JSONImporterController();
      $result = await $importer->importCategories($files['categories_file']['tmp_name']);
      return $result;
    } else {
      return false;
    }
  }

  public static async function exportGame(): Awaitable<void> {
    $exporter = new JSONExporterController();
    $game = await $exporter->genData('game');
    JSONExporterController::sendJSON($game, 'fbctf_game.json');
    exit();
  }

  public static async function exportTeams(): Awaitable<void> {
    $exporter = new JSONExporterController();
    $teams = await $exporter->genData('teams');
    JSONExporterController::sendJSON($teams, 'fbctf_teams.json');
    exit();
  }

  public static async function exportLogos(): Awaitable<void> {
    $exporter = new JSONExporterController();
    $logos = await $exporter->genData('logos');
    JSONExporterController::sendJSON($logos, 'fbctf_logos.json');
    exit();
  }

  public static async function exportLevels(): Awaitable<void> {
    $exporter = new JSONExporterController();
    $levels = await $exporter->genData('levels');
    JSONExporterController::sendJSON($levels, 'fbctf_levels.json');
    exit();
  }

  public static async function exportCategories(): Awaitable<void> {
    $exporter = new JSONExporterController();
    $categories = await $exporter->genData('categories');
    JSONExporterController::sendJSON($categories, 'fbctf_categories.json');
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
