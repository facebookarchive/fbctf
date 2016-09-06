<?hh // strict

class Level extends Model implements Importable, Exportable {
  private function __construct(
    private int $id,
    private int $active,
    private string $type,
    private string $title,
    private string $description,
    private int $entity_id,
    private int $category_id,
    private int $points,
    private int $bonus,
    private int $bonus_dec,
    private int $bonus_fix,
    private string $flag,
    private string $hint,
    private int $penalty,
    private string $created_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getActive(): bool {
    return $this->active === 1;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getTitle(): string {
    return $this->title;
  }

  public function getDescription(): string {
    return $this->description;
  }

  public function getEntityId(): int {
    return $this->entity_id;
  }

  public function getCategoryId(): int {
    return $this->category_id;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getBonus(): int {
    return $this->bonus;
  }

  public function getBonusDec(): int {
    return $this->bonus_dec;
  }

  public function getBonusFix(): int {
    return $this->bonus_fix;
  }

  public function getFlag(): string {
    return $this->flag;
  }

  public function getHint(): string {
    return $this->hint;
  }

  public function getPenalty(): int {
    return $this->penalty;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  private static function levelFromRow(Map<string, string> $row): Level {
    return new Level(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'active')),
      must_have_idx($row, 'type'),
      must_have_idx($row, 'title'),
      must_have_idx($row, 'description'),
      intval(must_have_idx($row, 'entity_id')),
      intval(must_have_idx($row, 'category_id')),
      intval(must_have_idx($row, 'points')),
      intval(must_have_idx($row, 'bonus')),
      intval(must_have_idx($row, 'bonus_dec')),
      intval(must_have_idx($row, 'bonus_fix')),
      must_have_idx($row, 'flag'),
      must_have_idx($row, 'hint'),
      intval(must_have_idx($row, 'penalty')),
      must_have_idx($row, 'created_ts'),
    );
  }

  // Retrieve the level that is using one country
  public static async function genWhoUses(int $country_id): Awaitable<?Level> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM levels WHERE entity_id = %d AND active = 1 LIMIT 1',
      $country_id,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return self::levelFromRow($result->mapRows()[0]);
    } else {
      return null;
    }
  }

  // Import levels.
  public static async function import(
    array<string, array<string, mixed>> $elements
  ): Awaitable<bool> {
    foreach ($elements as $level) {
      $title = must_have_string($level, 'title');
      $type = must_have_string($level, 'type');
      $entity_name = must_have_string($level, 'entity_name');
      $c = must_have_string($level, 'category');
      $exist = await self::genAlreadyExist($type, $title, $entity_name);
      $entity_exist = await Country::genCheckExists($entity_name);
      $category_exist = await Category::genCheckExists($c);
      if (!$exist && $entity_exist && $category_exist) {
        $entity = await Country::genCountry($entity_name);
        $category = await Category::genSingleCategoryByName($c);
        await self::genCreate(
          $type,
          $title,
          must_have_string($level, 'description'),
          $entity->getId(),
          $category->getId(),
          must_have_int($level, 'points'),
          must_have_int($level, 'bonus'),
          must_have_int($level, 'bonus_dec'),
          must_have_int($level, 'bonus_fix'),
          must_have_string($level, 'flag'),
          must_have_string($level, 'hint'),
          must_have_int($level, 'penalty'),
        );
      }
    }
    return true;
  }

  // Export levels.
  public static async function export(): Awaitable<array<string, array<string, mixed>>> {
    $all_levels_data = array();
    $all_levels = await self::genAllLevels();

    foreach ($all_levels as $level) {
      $entity = await Country::gen($level->getEntityId());
      $category = await Category::genSingleCategory($level->getCategoryId());
      $one_level = array(
        'type' => $level->getType(),
        'title' => $level->getTitle(),
        'active' => $level->getActive(),
        'description' => $level->getDescription(),
        'entity_name' => $entity->getName(),
        'category' => $category->getCategory(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonus(),
        'bonus_dec' => $level->getBonusDec(),
        'bonus_fix' => $level->getBonusFix(),
        'flag' => $level->getFlag(),
        'hint' => $level->getHint(),
        'penalty' => $level->getPenalty()
      );
      array_push($all_levels_data, $one_level);
    }
    return array(
      'levels' => $all_levels_data
    );
  }

  // Check to see if the level is active.
  public static async function genCheckStatus(int $level_id): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM levels WHERE id = %d AND active = 1 LIMIT 1',
      $level_id,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval($result->mapRows()[0]['COUNT(*)']) > 0);
    } else {
      return false;
    }
  }

  // Check to see if the level is a base.
  public static async function genCheckBase(int $level_id): Awaitable<bool> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT COUNT(*) FROM levels WHERE id = %d AND active = 1 AND type = %s LIMIT 1',
        $level_id,
        'base',
      );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval($result->mapRows()[0]['COUNT(*)']) > 0);
    } else {
      return false;
    }
  }

  // Create a team and return the created level id.
  public static async function genCreate(
    string $type,
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    int $bonus_fix,
    string $flag,
    string $hint,
    int $penalty,
  ): Awaitable<int> {
    $db = await self::genDb();

    if ($entity_id === 0) {
      $ent_id = await Country::genRandomAvailableCountryId();
    } else {
      $ent_id = $entity_id;
    }
    await $db->queryf(
      'INSERT INTO levels '.
      '(type, title, description, entity_id, category_id, points, bonus, bonus_dec, bonus_fix, flag, hint, penalty, active, created_ts) '.
      'VALUES (%s, %s, %s, %d, %d, %d, %d, %d, %d, %s, %s, %d, %d, NOW())',
      $type,
      $title,
      $description,
      $ent_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus_fix,
      $flag,
      $hint,
      $penalty,
      0, // active
    );

    // Mark entity as used
    await Country::genSetUsed($ent_id, true);

    // Return the newly created level_id
    $result =
      await $db->queryf(
        'SELECT id FROM levels WHERE title = %s AND description = %s AND entity_id = %d AND flag = %s AND category_id = %d LIMIT 1',
        $title,
        $description,
        $ent_id,
        $flag,
        $category_id,
      );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(must_have_idx($result->mapRows()[0], 'id'));
  }

  // Create a flag level.
  public static async function genCreateFlag(
    string $title,
    string $description,
    string $flag,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
  ): Awaitable<int> {
    return await self::genCreate(
      'flag',
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $flag,
      $hint,
      $penalty,
    );
  }

  // Update a flag level.
  public static async function genUpdateFlag(
    string $title,
    string $description,
    string $flag,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
    int $level_id,
  ): Awaitable<void> {
    await self::genUpdate(
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $flag,
      $hint,
      $penalty,
      $level_id,
    );
  }

  // Create a quiz level.
  public static async function genCreateQuiz(
    string $title,
    string $question,
    string $answer,
    int $entity_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
  ): Awaitable<int> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT id FROM categories WHERE category = %s LIMIT 1',
      'Quiz',
    );

    $category_id = intval(must_have_idx($result->mapRows()[0], 'id'));
    return await self::genCreate(
      'quiz',
      $title,
      $question,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $answer,
      $hint,
      $penalty,
    );
  }

  // Update a quiz level.
  public static async function genUpdateQuiz(
    string $title,
    string $question,
    string $answer,
    int $entity_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    string $hint,
    int $penalty,
    int $level_id,
  ): Awaitable<void> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT id FROM categories WHERE category = %s LIMIT 1',
      'Quiz',
    );

    $category_id = intval(must_have_idx($result->mapRows()[0], 'id'));
    await self::genUpdate(
      $title,
      $question,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus,
      $answer,
      $hint,
      $penalty,
      $level_id,
    );
  }

  // Create a base level.
  public static async function genCreateBase(
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    string $hint,
    int $penalty,
  ): Awaitable<int> {
    return await self::genCreate(
      'base',
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      0,
      $bonus,
      '',
      $hint,
      $penalty,
    );
  }

  // Update a base level.
  public static async function genUpdateBase(
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    string $hint,
    int $penalty,
    int $level_id,
  ): Awaitable<void> {
    await self::genUpdate(
      $title,
      $description,
      $entity_id,
      $category_id,
      $points,
      $bonus,
      0,
      $bonus,
      '',
      $hint,
      $penalty,
      $level_id,
    );
  }

  // Update level.
  public static async function genUpdate(
    string $title,
    string $description,
    int $entity_id,
    int $category_id,
    int $points,
    int $bonus,
    int $bonus_dec,
    int $bonus_fix,
    string $flag,
    string $hint,
    int $penalty,
    int $level_id,
  ): Awaitable<void> {
    $db = await self::genDb();

    if ($entity_id === 0) {
      $ent_id = await Country::genRandomAvailableCountryId();
    } else {
      $ent_id = $entity_id;
    }

    await $db->queryf(
      'UPDATE levels SET title = %s, description = %s, entity_id = %d, category_id = %d, points = %d, '.
      'bonus = %d, bonus_dec = %d, bonus_fix = %d, flag = %s, hint = %s, '.
      'penalty = %d WHERE id = %d LIMIT 1',
      $title,
      $description,
      $ent_id,
      $category_id,
      $points,
      $bonus,
      $bonus_dec,
      $bonus_fix,
      $flag,
      $hint,
      $penalty,
      $level_id,
    );

    // Make sure entities are consistent
    await Country::genUsedAdjust();
  }

  // Delete level.
  public static async function genDelete(int $level_id): Awaitable<void> {
    $db = await self::genDb();

    // Free country first.
    $level = await self::gen($level_id);
    await Country::genSetUsed($level->getEntityId(), false);

    await $db->queryf('DELETE FROM levels WHERE id = %d LIMIT 1', $level_id);
  }

  // Enable or disable level by passing 1 or 0.
  public static async function genSetStatus(
    int $level_id,
    bool $active,
  ): Awaitable<void> {
    $db = await self::genDb();

    await $db->queryf(
      'UPDATE levels SET active = %d WHERE id = %d LIMIT 1',
      (int) $active,
      $level_id,
    );
  }

  // Enable or disable levels by type.
  public static async function genSetStatusType(
    bool $active,
    string $type,
  ): Awaitable<void> {
    $db = await self::genDb();

    await $db->queryf(
      'UPDATE levels SET active = %d WHERE type = %s',
      (int) $active,
      $type,
    );
  }

  // Enable or disable all levels.
  public static async function genSetStatusAll(
    bool $active,
    string $type,
  ): Awaitable<void> {
    $db = await self::genDb();

    if ($type === 'all') {
      await $db->queryf(
        'UPDATE levels SET active = %d WHERE id > 0',
        (int) $active,
      );
    } else {
      await $db->queryf(
        'UPDATE levels SET active = %d WHERE type = %s',
        (int) $active,
        $type,
      );
    }
  }

  // All levels.
  public static async function genAllLevels(): Awaitable<array<Level>> {
    $db = await self::genDb();

    $result = await $db->queryf('SELECT * FROM levels');

    $levels = array();
    foreach ($result->mapRows() as $row) {
      $levels[] = self::levelFromRow($row);
    }

    return $levels;
  }

  // All levels by status.
  public static async function genAllActiveLevels(): Awaitable<array<Level>> {
    $db = await self::genDb();

    $result =
      await $db->queryf('SELECT * FROM levels WHERE active = 1 ORDER BY id');

    $levels = array();
    foreach ($result->mapRows() as $row) {
      $levels[] = self::levelFromRow($row);
    }

    return $levels;
  }

  // All levels by status.
  public static async function genAllActiveBases(): Awaitable<array<Level>> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM levels WHERE active = 1 AND type = %s ORDER BY id',
      'base',
    );

    $bases = array();
    foreach ($result->mapRows() as $row) {
      $bases[] = self::levelFromRow($row);
    }

    return $bases;
  }

  // All levels by type.
  public static async function genAllTypeLevels(
    string $type,
  ): Awaitable<array<Level>> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM levels WHERE type = %s ORDER BY id',
      $type,
    );

    $levels = array();
    foreach ($result->mapRows() as $row) {
      $levels[] = self::levelFromRow($row);
    }

    return $levels;
  }

  // All quiz levels.
  public static async function genAllQuizLevels(): Awaitable<array<Level>> {
    return await self::genAllTypeLevels('quiz');
  }

  // All base levels.
  public static async function genAllBaseLevels(): Awaitable<array<Level>> {
    return await self::genAllTypeLevels('base');
  }

  // All flag levels.
  public static async function genAllFlagLevels(): Awaitable<array<Level>> {
    return await self::genAllTypeLevels('flag');
  }

  // Get a single level.
  public static async function gen(int $level_id): Awaitable<Level> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM levels WHERE id = %d LIMIT 1',
      $level_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    $level = self::levelFromRow($result->mapRows()[0]);

    return $level;
  }

  // Check if flag is correct.
  public static async function genCheckAnswer(
    int $level_id,
    string $answer,
  ): Awaitable<bool> {
    $level = await self::gen($level_id);
    return strtoupper(trim($level->getFlag())) === strtoupper(trim($answer));
  }

  // Adjust bonus.
  public static async function genAdjustBonus(int $level_id): Awaitable<void> {
    $db = await self::genDb();

    await $db->queryf(
      'UPDATE levels SET bonus = GREATEST(bonus - bonus_dec, 0) WHERE id = %d LIMIT 1',
      $level_id,
    );
  }

  // Log base request.
  public static async function genLogBaseEntry(
    int $level_id,
    int $code,
    string $response,
  ): Awaitable<void> {
    $db = await self::genDb();

    await $db->queryf(
      'INSERT INTO bases_log (ts, level_id, code, response) VALUES (NOW(), %d, %d, %s)',
      $level_id,
      $code,
      $response,
    );
  }

  private static async function withLock(
    string $lock_name,
    int $team_id,
    (function(): Awaitable<mixed>) $thunk,
  ): Awaitable<mixed> {
    $lock_name =
      sprintf('%s/%s_%d', sys_get_temp_dir(), $lock_name, $team_id);
    $lock = fopen($lock_name, 'w');

    if ($lock === false) {
      error_log('Failed to open lock file $lock_name');
      return null;
    }
    if (!flock($lock, LOCK_EX)) {
      fclose($lock);
      return null;
    }

    $result = await $thunk();

    // Release the scoring lock
    flock($lock, LOCK_UN);
    fclose($lock);

    return $result;
  }

  // Score level. Works for quiz and flags.
  public static async function genScoreLevel(
    int $level_id,
    int $team_id,
  ): Awaitable<bool> {
    $result =
      await self::withLock(
        'score_level_lock',
        $team_id,
        async function(): Awaitable<mixed> use ($level_id, $team_id) {
          $db = await self::genDb();

          // Check if team has already scored this level
          $previous_score =
            await ScoreLog::genPreviousScore($level_id, $team_id, false);
          if ($previous_score) {
            return false;
          }

          $level = await self::gen($level_id);

          // Calculate points to give
          $points = $level->getPoints() + $level->getBonus();

          // Adjust bonus
          await self::genAdjustBonus($level_id);

          // Score!
          await $db->queryf(
            'UPDATE teams SET points = points + %d, last_score = NOW() WHERE id = %d LIMIT 1',
            $points,
            $team_id,
          );

          // Log the score
          await ScoreLog::genLogValidScore(
            $level_id,
            $team_id,
            $points,
            $level->getType(),
          );

          return true;
        },
      );

    return boolval($result);
  }

  // Score base.
  public static async function genScoreBase(
    int $level_id,
    int $team_id,
  ): Awaitable<bool> {
    $result =
      await self::withLock(
        'score_base_lock',
        $team_id,
        async function(): Awaitable<mixed> use ($level_id, $team_id) {
          $db = await self::genDb();

          $level = await self::gen($level_id);

          // Calculate points to give
          $score =
            await ScoreLog::genPreviousScore($level_id, $team_id, false);
          if ($score) {
            $points = $level->getPoints();
          } else {
            $points = $level->getPoints() + $level->getBonus();
          }

          // Score!
          await $db->queryf(
            'UPDATE teams SET points = points + %d, last_score = NOW() WHERE id = %d LIMIT 1',
            $points,
            $team_id,
          );

          // Log the score...
          await ScoreLog::genLogValidScore(
            $level_id,
            $team_id,
            $points,
            $level->getType(),
          );

          return true;
        },
      );

    return boolval($result);
  }

  // Get hint.
  public static async function genLevelHint(
    int $level_id,
    int $team_id,
  ): Awaitable<?string> {
    $result =
      await self::withLock(
        'hint_lock',
        $team_id,
        async function(): Awaitable<mixed> use ($level_id, $team_id) {
          $db = await self::genDb();

          $level = await self::gen($level_id);
          $penalty = $level->getPenalty();

          // Check if team has already gotten this hint or if the team has scored this already
          // If so, hint is free
          $hint = await HintLog::genPreviousHint($level_id, $team_id, false);
          $score =
            await ScoreLog::genPreviousScore($level_id, $team_id, false);
          if ($hint || $score) {
            $penalty = 0;
          }

          // Make sure team has enough points to pay
          $team = await Team::genTeam($team_id);
          if ($team->getPoints() < $penalty) {
            return null;
          }

          // Adjust points
          await $db->queryf(
            'UPDATE teams SET points = points - %d WHERE id = %d LIMIT 1',
            $penalty,
            $team_id,
          );

          // Log the hint
          await HintLog::genLogGetHint($level_id, $team_id, $penalty);

          // Hint!
          return $level->getHint();
        },
      );

    return $result !== null ? strval($result) : null;
  }

  // Get the IP from a base level.
  public static async function genBaseIP(int $base_id): Awaitable<string> {
    $links = await Link::genAllLinks($base_id);
    $link = $links[0];
    $ip = explode(':', $link->getLink())[0];

    return $ip;
  }

  // Request all bases
  public static function getBasesResponses(
    array<int, array<string, mixed>> $bases,
  ): array<int, string> {
    // Iterates and request all the bases endpoints for owner
    $responses = array();
    $curl_handlers = array();
    $multi_handler = curl_multi_init();

    // Create the list of request handlers
    foreach ($bases as $base) {
      $base_id = intval(must_have_idx($base, 'id'));
      $base_url = must_have_idx($base, 'url');
      $curl_handlers[$base_id] = curl_init();
      curl_setopt($curl_handlers[$base_id], CURLOPT_URL, $base_url);
      curl_setopt($curl_handlers[$base_id], CURLOPT_HEADER, 0);
      curl_setopt($curl_handlers[$base_id], CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl_handlers[$base_id], CURLOPT_PORT, 12345);
      curl_setopt($curl_handlers[$base_id], CURLOPT_TIMEOUT, 3);
      curl_multi_add_handle($multi_handler, $curl_handlers[$base_id]);
    }

    // Run each request by executing all the handlers
    $running = 0;
    do {
      curl_multi_exec($multi_handler, $running);
    } while ($running > 0);

    // Get responses and remove handlers
    foreach ($curl_handlers as $id => $c) {
      $r = array(
        'id' => intval($id),
        'response' => curl_multi_getcontent($c),
      );
      curl_multi_remove_handle($multi_handler, $c);
      array_push($responses, $r);
    }

    curl_multi_close($multi_handler);

    // Return the responses
    return $responses;
  }

  // Bases processing and scoring.
  public static async function genBaseScoring(): Awaitable<void> {
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $cmd =
      'hhvm -vRepo.Central.Path=/var/run/hhvm/.hhvm.hhbc_bases '.
      $document_root.
      '/scripts/bases.php > /dev/null 2>&1 & echo $!';
    $pid = shell_exec($cmd);
    await Control::genStartScriptLog(intval($pid), 'bases', $cmd);
  }

  // Stop bases processing and scoring process.
  public static async function genStopBaseScoring(): Awaitable<void> {
    // Kill running process
    $pid = await Control::genScriptPid('bases');
    if ($pid > 0) {
      exec('kill -9 '.escapeshellarg(strval($pid)));
    }
    // Mark process as stopped
    await Control::genStopScriptLog($pid);
  }

  // Check if a level already exists by type, title and entity.
  public static async function genAlreadyExist(
    string $type,
    string $title,
    string $entity_name,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM levels WHERE type = %s AND title = %s AND entity_id IN (SELECT id FROM countries WHERE name = %s)',
      $type,
      $title,
      $entity_name,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }
}
