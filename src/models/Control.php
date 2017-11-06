<?hh // strict

class Control extends Model {

  protected static string $MC_KEY = 'control:';

  protected static Map<string, string>
    $MC_KEYS = Map {'ALL_ACTIVITY' => 'activity'};

  public static async function genServerAddr(): Awaitable<string> {
    $host = gethostname();
    $ip = gethostbyname($host);
    return strval($ip);
  }

  public static async function genStartScriptLog(
    int $pid,
    string $name,
    string $cmd,
  ): Awaitable<void> {
    $db = await self::genDb();
    $host = await Control::genServerAddr();
    await $db->queryf(
      'INSERT INTO scripts (ts, pid, name, host, cmd, status) VALUES (NOW(), %d, %s, %s, %s, 1)',
      $pid,
      $name,
      $host,
      $cmd,
    );
  }

  public static async function genStopScriptLog(int $pid): Awaitable<void> {
    $db = await self::genDb();
    $host = await Control::genServerAddr();
    await $db->queryf(
      'UPDATE scripts SET status = 0, host = %s WHERE pid = %d LIMIT 1',
      $host,
      $pid,
    );
  }

  public static async function genScriptPid(string $name): Awaitable<int> {
    $db = await self::genDb();
    $host = await Control::genServerAddr();
    $result =
      await $db->queryf(
        'SELECT pid FROM scripts WHERE name = %s AND host = %s AND status = 1 LIMIT 1',
        $name,
        $host,
      );
    $pid = 0;
    if ($result->numRows() > 0) {
      $pid = intval(must_have_idx($result->mapRows()[0], 'pid'));
    }
    return $pid;
  }

  public static async function genClearScriptLog(): Awaitable<void> {
    $db = await self::genDb();
    $host = await Control::genServerAddr();
    await $db->queryf(
      'DELETE FROM scripts WHERE id > 0 AND status = 0 AND host = %s',
      $host,
    );
  }

  public static async function genBegin(): Awaitable<void> {
    await \HH\Asio\va(
      Announcement::genDeleteAll(), // Clear announcements log
      ActivityLog::genDeleteAll(), // Clear activity log
      Team::genResetAllPoints(), // Reset all points
      ScoreLog::genResetScores(), // Clear scores log
      HintLog::genResetHints(), // Clear hints log
      FailureLog::genResetFailures(), // Clear failures log
      self::genResetBases(), // Clear bases log
      self::genClearScriptLog(),
      Configuration::genUpdate('registration', '0'), // Disable registration
    );

    await \HH\Asio\va(
      Announcement::genCreateAuto('Game has started!'), // Announce game starting
      ActivityLog::genCreateGenericLog('Game has started!'), // Log game starting
      Configuration::genUpdate('game', '1'), // Mark game as started
      Configuration::genUpdate('scoring', '1'), // Enable scoring
    );

    // Take timestamp of start
    $start_ts = time();
    await Configuration::genUpdate('start_ts', strval($start_ts));

    // Calculate timestamp of the end or game duration
    $config_end_ts = await Configuration::gen('end_ts');
    $end_ts = intval($config_end_ts->getValue());
    if ($end_ts === 0) {
      list($config_value, $config_unit) = await \HH\Asio\va(
        Configuration::gen('game_duration_value'),
        Configuration::gen('game_duration_unit'),
      );
      $duration_value = intval($config_value->getValue());
      $duration_unit = $config_unit->getValue();
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
    } else {
      $duration_length = ($end_ts - $start_ts) / 60;
      await \HH\Asio\va(
        Configuration::genUpdate(
          'game_duration_value',
          strval($duration_length),
        ),
        Configuration::genUpdate('game_duration_unit', 'm'),
      );
    }

    await \HH\Asio\va(
      Configuration::genUpdate('pause_ts', '0'), // Set pause to zero
      Configuration::genUpdate('game_paused', '0'), // Set game to not paused
      Configuration::genUpdate('timer', '1'), // Kick off timer
      Progressive::genReset(), // Reset and kick off progressive scoreboard
    );

    await \HH\Asio\va(
      Progressive::genRun(),
      Level::genBaseScoring(), // Kick off scoring for bases
    );
  }

  public static async function genEnd(): Awaitable<void> {
    await \HH\Asio\va(
      Announcement::genCreateAuto('Game has ended!'), // Announce game ending
      ActivityLog::genCreateGenericLog('Game has ended!'), // Log game ending
      Configuration::genUpdate('game', '0'), // Mark game as finished and stop progressive scoreboard
      Configuration::genUpdate('scoring', '0'), // Disable scoring
      Configuration::genUpdate('start_ts', '0'), // Put timestamps to zero
      Configuration::genUpdate('end_ts', '0'),
      Configuration::genUpdate('next_game', '0'),
      Configuration::genUpdate('pause_ts', '0'), // Set pause to zero
      Configuration::genUpdate('timer', '0'), // Stop timer
    );

    $pause = await Configuration::gen('game_paused');
    $game_paused = $pause->getValue() === '1';

    if (!$game_paused) {
      // Stop bases scoring process
      // Stop progressive scoreboard process
      await \HH\Asio\va(Level::genStopBaseScoring(), Progressive::genStop());
    } else {
      // Set game to not paused
      await Configuration::genUpdate('game_paused', '0');
    }
  }

  public static async function genPause(): Awaitable<void> {
    await \HH\Asio\va(
      Announcement::genCreateAuto('Game has been paused!'), // Announce game paused
      ActivityLog::genCreateGenericLog('Game has been paused!'), // Log game paused
      Configuration::genUpdate('scoring', '0'), // Disable scoring
    );

    $pause_ts = time();
    await \HH\Asio\va(
      Configuration::genUpdate('pause_ts', strval($pause_ts)), // Set pause timestamp
      Configuration::genUpdate('game_paused', '1'), // Set gane to paused
      Configuration::genUpdate('timer', '0'), // Stop timer
      Level::genStopBaseScoring(), // Stop bases scoring process
      Progressive::genStop(), // Stop progressive scoreboard process
    );
  }

  public static async function genUnpause(): Awaitable<void> {
    await Configuration::genUpdate('scoring', '1'); // Enable scoring
    list($config_pause_ts, $config_start_ts, $config_end_ts) =
      await \HH\Asio\va(
        Configuration::gen('pause_ts'), // Get pause time
        Configuration::gen('start_ts'), // Get start time
        Configuration::gen('end_ts'), // Get end time
      );
    $pause_ts = intval($config_pause_ts->getValue());
    $start_ts = intval($config_start_ts->getValue());
    $end_ts = intval($config_end_ts->getValue());

    // Calulcate game remaining
    $game_duration = $end_ts - $start_ts;
    $game_played_duration = $pause_ts - $start_ts;
    $remaining_duration = $game_duration - $game_played_duration;
    $end_ts = time() + $remaining_duration;

    await \HH\Asio\va(
      Configuration::genUpdate('end_ts', strval($end_ts)), // Set new endtime
      Configuration::genUpdate('pause_ts', '0'), // Set pause to zero
      Configuration::genUpdate('game_paused', '0'), // Set gane to not paused
      Configuration::genUpdate('timer', '1'), // Start timer
      Progressive::genRun(), // Kick off progressive scoreboard
      Level::genBaseScoring(), // Kick off scoring for bases
      Announcement::genCreateAuto('Game has resumed!'), // Announce game resumed
      ActivityLog::genCreateGenericLog('Game has resumed!'), // Log game resumed
    );
  }

  public static async function genAutoBegin(): Awaitable<void> {
    // Prevent autorun.php from storing timestamps in local cache, forever (the script runs continuously).
    Configuration::deleteLocalCache('CONFIGURATION');
    list($config_start_ts, $config_end_ts, $config_game_paused) =
      await \HH\Asio\va(
        Configuration::gen('start_ts'), // Get start time
        Configuration::gen('end_ts'), // Get end time
        Configuration::gen('game_paused'), // Get paused status
      );
    $start_ts = intval($config_start_ts->getValue());
    $end_ts = intval($config_end_ts->getValue());
    $game_paused = intval($config_game_paused->getValue());

    if (($game_paused === 0) && ($start_ts <= time()) && ($end_ts > time())) {
      await Control::genBegin(); // Start the game
    }
  }

  public static async function genAutoEnd(): Awaitable<void> {
    // Prevent autorun.php from storing timestamps in local cache, forever (the script runs continuously).
    Configuration::deleteLocalCache('CONFIGURATION');
    list($config_start_ts, $config_end_ts, $config_game_paused) =
      await \HH\Asio\va(
        Configuration::gen('start_ts'), // Get start time
        Configuration::gen('end_ts'), // Get end time
        Configuration::gen('game_paused'), // Get paused status
      );
    $start_ts = intval($config_start_ts->getValue());
    $end_ts = intval($config_end_ts->getValue());
    $game_paused = intval($config_game_paused->getValue());

    if (($game_paused === 0) && ($end_ts <= time())) {
      await Control::genEnd(); // End the game
    }
  }

  public static async function genAutoRun(): Awaitable<void> {
    // Prevent autorun.php from storing timestamps in local cache, forever (the script runs continuously).
    Configuration::deleteLocalCache('CONFIGURATION');
    $config_game = await Configuration::gen('game'); // Get start time
    $game = intval($config_game->getValue());

    if ($game === 0) {
      await Control::genAutoBegin(); // Check and start the game
    } else {
      await Control::genAutoEnd(); // Check and stop the game
    }
  }

  public static async function genRunAutoRunScript(): Awaitable<void> {
    $autorun_status = await Control::checkScriptRunning('autorun');
    if ($autorun_status === false) {
      $autorun_location = escapeshellarg(
        must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT').
        '/scripts/autorun.php',
      );
      $cmd =
        'hhvm -vRepo.Central.Path=/var/run/hhvm/.hhvm.hhbc_autorun '.
        $autorun_location.
        ' > /dev/null 2>&1 & echo $!';
      $pid = shell_exec($cmd);
      await Control::genStartScriptLog(intval($pid), 'autorun', $cmd);
    }
  }

  public static async function checkScriptRunning(
    string $name,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $host = await Control::genServerAddr();
    $result = await $db->queryf(
      'SELECT pid FROM scripts WHERE name = %s AND host = %s AND status = 1',
      $name,
      $host,
    );
    $status = false;
    if ($result->numRows() >= 1) {
      foreach ($result->mapRows() as $row) {
        $pid = intval(must_have_idx($row, 'pid'));
        $status = file_exists("/proc/$pid");
        if ($status === false) {
          await Control::genStopScriptLog($pid);
          await Control::genClearScriptLog();
        }
      }
      return $status;
    } else {
      return false;
    }
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
      await self::genFlushMemcached();
      return true;
    }
    return false;
  }

  public static async function importTeams(): Awaitable<bool> {
    $data_teams = JSONImporterController::readJSON('teams_file');
    if (is_array($data_teams)) {
      $teams = must_have_idx($data_teams, 'teams');
      await self::genFlushMemcached();
      return await Team::importAll($teams);
    }
    return false;
  }

  public static async function importLogos(): Awaitable<bool> {
    $data_logos = JSONImporterController::readJSON('logos_file');
    if (is_array($data_logos)) {
      $logos = must_have_idx($data_logos, 'logos');
      await self::genFlushMemcached();
      return await Logo::importAll($logos);
    }
    return false;
  }

  public static async function importLevels(): Awaitable<bool> {
    $data_levels = JSONImporterController::readJSON('levels_file');
    if (is_array($data_levels)) {
      $levels = must_have_idx($data_levels, 'levels');
      await self::genFlushMemcached();
      return await Level::importAll($levels);
    }
    return false;
  }

  public static async function importCategories(): Awaitable<bool> {
    $data_categories = JSONImporterController::readJSON('categories_file');
    if (is_array($data_categories)) {
      $categories = must_have_idx($data_categories, 'categories');
      await self::genFlushMemcached();
      return await Category::importAll($categories);
    }
    return false;
  }

  public static async function importAttachments(): Awaitable<bool> {
    $output = array();
    $status = 0;
    $filename =
      strval(BinaryImporterController::getFilename('attachments_file'));
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $directory = Attachment::attachmentsDir;
    $cmd = "tar -zx --mode=600 -C $directory -f $filename";
    exec($cmd, $output, $status);
    if (intval($status) !== 0) {
      return false;
    }
    $directory_files = array_slice(scandir($directory), 2);
    foreach ($directory_files as $file) {
      if (is_dir($file) === true) {
        continue;
      }
      $chmod = chmod($directory.$file, 0600);
      invariant(
        $chmod === true,
        'Failed to set attachment file permissions to 0600',
      );
    }
    await self::genFlushMemcached();
    return true;
  }

  public static async function restoreDb(): Awaitable<bool> {
    $output = array();
    $status = 0;
    $filename =
      strval(BinaryImporterController::getFilename('database_file'));
    $cmd = "cat $filename | gunzip - ";
    exec($cmd, $output, $status);
    if (intval($status) !== 0) {
      return false;
    }
    $cmd = "cat $filename | gunzip - | ".Db::getInstance()->getRestoreCmd();
    exec($cmd, $output, $status);
    if (intval($status) !== 0) {
      return false;
    }
    await self::genFlushMemcached();
    return true;
  }

  public static async function exportGame(): Awaitable<void> {
    $game = array();
    $awaitables = Map {
      'logos' => Logo::exportAll(),
      'teams' => Team::exportAll(),
      'categories' => Category::exportAll(),
      'levels' => Level::exportAll(),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $game['logos'] = $awaitables['logos'];
    $game['teams'] = $awaitables['teams'];
    $game['categories'] = $awaitables['categories'];
    $game['levels'] = $awaitables['levels'];

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

  public static async function exportAttachments(): Awaitable<void> {
    $filename = 'fbctf-attachments-'.date("d-m-Y").'.tgz';
    header('Content-Type: application/x-tgz');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $directory = Attachment::attachmentsDir;
    $cmd = "tar -cz -C $directory . ";
    passthru($cmd);
    exit();
  }

  public static async function backupDb(): Awaitable<void> {
    $filename = 'fbctf-backup-'.date("d-m-Y").'.sql.gz';
    header('Content-Type: application/x-gzip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $cmd = Db::getInstance()->getBackupCmd().' | gzip --best';
    passthru($cmd);
    exit();
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

  public static async function genFlushMemcached(): Awaitable<bool> {
    return self::flushMCCluster();
  }

  private static async function genLoadDatabaseFile(
    string $file,
  ): Awaitable<bool> {
    $contents = file_get_contents($file);
    if ($contents) {
      $schema = explode(";", $contents);
      $db = await self::genDb();
      $result = await $db->multiQuery($schema);
      return $result ? true : false;
    }
    return false;
  }

  public static async function genResetDatabase(): Awaitable<bool> {
    $admins = await MultiTeam::genAllAdmins();
    $schema = await self::genLoadDatabaseFile('../database/schema.sql');
    $countries = await self::genLoadDatabaseFile('../database/countries.sql');
    $logos = await self::genLoadDatabaseFile('../database/logos.sql');
    if ($schema && $countries && $logos) {
      foreach ($admins as $admin) {
        $team_id = await Team::genCreate(
          $admin->getName(),
          $admin->getPasswordHash(),
          $admin->getLogo(),
        );
        await Team::genSetAdmin($team_id, true);
        if ($admin->getProtected() === true) {
          await Team::genSetProtected($team_id, true);
        }
      }
      await self::genFlushMemcached();
      return true;
    }
    return false;
  }
}
