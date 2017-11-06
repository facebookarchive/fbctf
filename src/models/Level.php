<?hh // strict

class Level extends Model implements Importable, Exportable {

  protected static string $MC_KEY = 'level:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'LEVEL_BY_COUNTRY' => 'level_by_country',
      'ALL_LEVELS' => 'all_levels',
      'ALL_ACTIVE_LEVELS' => 'active_levels',
      'ALL_LEVELS_COUNTRY_MAP' => 'all_levels_country_map',
    };

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
    return mb_convert_encoding($this->title, 'UTF-8');
  }

  public function getDescription(): string {
    return mb_convert_encoding($this->description, 'UTF-8');
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
  public static async function genWhoUses(
    int $country_id,
    bool $refresh = false,
  ): Awaitable<?Level> {
    $mc_result = self::getMCRecords('LEVEL_BY_COUNTRY');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $level_by_country = Map {};
      $result = await $db->queryf('SELECT * FROM levels WHERE active = 1');
      foreach ($result->mapRows() as $row) {
        $level_by_country->add(
          Pair {intval($row->get('entity_id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('LEVEL_BY_COUNTRY', $level_by_country);
      if ($level_by_country->contains($country_id)) {
        return $level_by_country->get($country_id);
      } else {
        return null;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ($mc_result->contains($country_id)) {
        return $mc_result->get($country_id);
      } else {
        return null;
      }
    }
  }

  // Import levels.
  public static async function importAll(
    array<string, array<string, mixed>> $elements,
  ): Awaitable<bool> {
    foreach ($elements as $level) {
      $title = must_have_string($level, 'title');
      $type = must_have_string($level, 'type');
      $entity_iso_code = must_have_string($level, 'entity_iso_code');
      $c = must_have_string($level, 'category');
      $exist = await self::genAlreadyExist($type, $title, $entity_iso_code);
      list($entity_exist, $category_exist) = await \HH\Asio\va(
        Country::genCheckExists($entity_iso_code),
        Category::genCheckExists($c),
      ); // TODO: Combine Awaits
      if (!$exist && $entity_exist && $category_exist) {
        list($entity, $category) = await \HH\Asio\va(
          Country::genCountry($entity_iso_code),
          Category::genSingleCategoryByName($c),
        ); // TODO: Combine Awaits
        $level_id = await self::genCreate(
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
        if (array_key_exists('links', $level)) {
          $links = must_have_idx($level, 'links');
          invariant(is_array($links), 'links must be of type array');
          foreach ($links as $link) {
            await Link::genCreate($link, $level_id); // TODO: Combine Awaits
          }
        }
        if (array_key_exists('attachments', $level)) {
          $attachments = must_have_idx($level, 'attachments');
          invariant(
            is_array($attachments),
            'attachments must be of type array',
          );
          foreach ($attachments as $attachment) {
            await Attachment::genImportAttachments(
              $level_id,
              $attachment['filename'],
              $attachment['type'],
            ); // TODO: Combine Awaits
          }
        }
      }
    }
    return true;
  }

  // Export levels.
  public static async function exportAll(
  ): Awaitable<array<string, array<string, mixed>>> {
    $all_levels_data = array();
    $all_levels = await self::genAllLevels();

    foreach ($all_levels as $level) {
      list($entity, $category, $links, $attachments) = await \HH\Asio\va(
        Country::gen($level->getEntityId()),
        Category::genSingleCategory($level->getCategoryId()),
        Link::genAllLinks($level->getId()),
        Attachment::genAllAttachments($level->getId()),
      ); // TODO: Combine Awaits

      $link_array = array();
      foreach ($links as $link) {
        $link_array[] = $link->getLink();
      }
      $attachment_array = array();
      foreach ($attachments as $attachment) {
        $attachment_array[] = [
          'filename' => $attachment->getFilename(),
          'type' => $attachment->getType(),
        ];
      }
      $one_level = array(
        'type' => $level->getType(),
        'title' => $level->getTitle(),
        'active' => $level->getActive(),
        'description' => $level->getDescription(),
        'entity_iso_code' => $entity->getIsoCode(),
        'category' => $category->getCategory(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonus(),
        'bonus_dec' => $level->getBonusDec(),
        'bonus_fix' => $level->getBonusFix(),
        'flag' => $level->getFlag(),
        'hint' => $level->getHint(),
        'penalty' => $level->getPenalty(),
        'links' => $link_array,
        'attachments' => $attachment_array,
      );
      array_push($all_levels_data, $one_level);
    }
    return array('levels' => $all_levels_data);
  }

  // Check to see if the level is active.
  public static async function genCheckStatus(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $active_levels = Map {};
      $result = await $db->queryf(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
      );
      foreach ($result->mapRows() as $row) {
        $active_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      if ($active_levels->contains($level_id)) {
        return true;
      } else {
        return false;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ($mc_result->contains($level_id)) {
        return true;
      } else {
        return false;
      }
    }
  }

  // Check to see if the level is a base.
  public static async function genCheckBase(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $active_levels = Map {};
      $result = await $db->queryf(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
      );
      foreach ($result->mapRows() as $row) {
        $active_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      if ($active_levels->contains($level_id)) {
        $level = $active_levels->get($level_id);
        invariant($level instanceof Level, 'level should be type of Level');
        if ($level->type == 'base') {
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ($mc_result->contains($level_id)) {
        $level = $mc_result->get($level_id);
        invariant($level instanceof Level, 'level should be type of Level');
        if ($level->type === 'base') {
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
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

    self::invalidateMCRecords();
    invariant($result->numRows() === 1, 'Expected exactly one result');

    $country_id = await self::genCountryIdForLevel(
      intval(must_have_idx($result->mapRows()[0], 'id')),
    );
    $country = await Country::gen($country_id);
    await \HH\Asio\va(
      Announcement::genCreateAuto($country->getName()." added!"),
      ActivityLog::genAdminLog("added", "Country", $country_id),
    );

    ActivityLog::invalidateMCRecords('ALL_ACTIVITY');

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

    $result =
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

    if ($result->numRowsAffected() > 0) {
      $country_id = await self::genCountryIdForLevel($level_id);
      $country = await Country::gen($country_id);
      await \HH\Asio\va(
        ActivityLog::genAdminLog("updated", "Country", $country_id),
        Announcement::genCreateAuto($country->getName()." updated!"),
      );
      self::invalidateMCRecords(); // Invalidate Memcached Level data.
      ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
    }
  }

  // Delete level.
  public static async function genDelete(int $level_id): Awaitable<void> {
    $db = await self::genDb();

    // Free country first.
    $level = await self::gen($level_id);
    await Country::genSetUsed($level->getEntityId(), false);

    // Remove team points for level
    $scores = await ScoreLog::genAllScoresByLevel($level_id);
    $level_delete_queries = Vector {};
    foreach ($scores as $score) {
      $team_id = $score->getTeamId();
      $points = $score->getPoints();
      $level_delete_queries->add(
        sprintf(
          'UPDATE teams SET points = points - %d WHERE id = %d',
          $points,
          $team_id,
        ),
      );
    }

    // Remove hint penalties from teams points for level
    $hints = await HintLog::genAllHintsByLevel($level_id);
    foreach ($hints as $hint) {
      $team_id = $hint->getTeamId();
      $penalty = $hint->getPenalty();
      $level_delete_queries->add(
        sprintf(
          'UPDATE teams SET points = points + %d WHERE id = %d',
          $penalty,
          $team_id,
        ),
      );
    }

    // Delete all references to level
    $level_delete_queries->addAll(
      Set {
        sprintf('DELETE FROM levels WHERE id = %d LIMIT 1', $level_id),
        sprintf('DELETE FROM hints_log WHERE level_id = %d', $level_id),
        sprintf('DELETE FROM scores_log WHERE level_id = %d', $level_id),
        sprintf('DELETE FROM failures_log WHERE level_id = %d', $level_id),
      },
    );
    await $db->multiQuery($level_delete_queries);

    self::invalidateMCRecords();
    Control::invalidateMCRecords();
    MultiTeam::invalidateMCRecords();
    HintLog::invalidateMCRecords();
    ScoreLog::invalidateMCRecords();
  }

  // Enable or disable level by passing 1 or 0.
  public static async function genSetStatus(
    int $level_id,
    bool $active,
  ): Awaitable<void> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'UPDATE levels SET active = %d WHERE id = %d LIMIT 1',
      (int) $active,
      $level_id,
    );

    if ($result->numRowsAffected() > 0) {
      $action = ($active === true) ? "enabled" : "disabled";
      $country_id = await self::genCountryIdForLevel($level_id);
      $country = await Country::gen($country_id);
      await \HH\Asio\va(
        ActivityLog::genAdminLog($action, "Country", $country_id),
        Announcement::genCreateAuto($country->getName().' '.$action.'!'),
      );
      self::invalidateMCRecords();
      ActivityLog::invalidateMCRecords('ALL_ACTIVITY');
    }
  }

  // Enable or disable levels by type.
  public static async function genSetStatusType(
    bool $active,
    string $type,
  ): Awaitable<void> {
    $db = await self::genDb();

    $results = await $db->queryf(
      'UPDATE levels SET active = %d WHERE type = %s',
      (int) $active,
      $type,
    );

    if ($results->numRowsAffected() > 0) {
      self::invalidateMCRecords();
    }
  }

  // Enable or disable all levels.
  public static async function genSetStatusAll(
    bool $active,
    string $type,
  ): Awaitable<void> {
    $db = await self::genDb();

    if ($type === 'all') {
      $result = await $db->queryf(
        'SELECT id FROM levels WHERE active = %d AND id >0',
        (int) !$active,
      );
    } else {
      $result = await $db->queryf(
        'SELECT id FROM levels WHERE active = %d AND type = %s',
        (int) !$active,
        $type,
      );
    }
    foreach ($result->mapRows() as $row) {
      await self::genSetStatus(intval($row->get('id')), $active); // TODO: Combine Awaits
    }
  }

  // All levels.
  public static async function genAllLevels(
    bool $refresh = false,
  ): Awaitable<array<Level>> {
    $mc_result = self::getMCRecords('ALL_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_levels = Map {};
      $result = await $db->queryf('SELECT * FROM levels ORDER BY id');
      foreach ($result->mapRows() as $row) {
        $all_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_LEVELS', new Map($all_levels));
      $levels = array();
      $levels = $all_levels->toValuesArray();
      return $levels;
    } else {
      $levels = array();
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      $levels = $mc_result->toValuesArray();
      return $levels;
    }
  }

  public static async function genAllLevelsCountryMap(
    bool $refresh = false,
  ): Awaitable<Map<int, Level>> {
    $mc_result = self::getMCRecords('ALL_LEVELS_COUNTRY_MAP');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_levels = Map {};
      $result = await $db->queryf('SELECT * FROM levels ORDER BY id');
      foreach ($result->mapRows() as $row) {
        $all_levels->add(
          Pair {intval($row->get('entity_id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_LEVELS_COUNTRY_MAP', new Map($all_levels));
      return $all_levels;
    } else {
      $levels = array();
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      return $mc_result;
    }
  }

  // All levels by status.
  public static async function genAllActiveLevels(
    bool $refresh = false,
  ): Awaitable<array<Level>> {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $active_levels = Map {};
      $result = await $db->queryf(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
      );
      foreach ($result->mapRows() as $row) {
        $active_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      $levels = array();
      $levels = $active_levels->toValuesArray();
      return $levels;
    } else {
      $levels = array();
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      $levels = $mc_result->toValuesArray();
      return $levels;
    }
  }

  // All levels by status.
  public static async function genAllActiveBases(
    bool $refresh = false,
  ): Awaitable<array<Level>> {
    $mc_result = self::getMCRecords('ALL_ACTIVE_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $active_levels = Map {};
      $result = await $db->queryf(
        'SELECT * FROM levels WHERE active = 1 ORDER BY id',
      );
      foreach ($result->mapRows() as $row) {
        $active_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_ACTIVE_LEVELS', $active_levels);
      $levels = array();
      $levels = $active_levels->toValuesArray();
      $bases = array();
      foreach ($levels as $level) {
        if ($level->type === 'base') {
          $bases[] = $level;
        }
      }
      return $bases;
    } else {
      $levels = array();
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      $levels = $mc_result->toValuesArray();
      $bases = array();
      foreach ($levels as $level) {
        if ($level->type === 'base') {
          $bases[] = $level;
        }
      }
      return $bases;
    }
  }

  // All levels by type.
  public static async function genAllTypeLevels(
    string $type,
    bool $refresh = false,
  ): Awaitable<array<Level>> {
    $mc_result = self::getMCRecords('ALL_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_levels = Map {};
      $result = await $db->queryf('SELECT * FROM levels ORDER BY id');
      foreach ($result->mapRows() as $row) {
        $all_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_LEVELS', $all_levels);
      $levels = array();
      $levels = $all_levels->toValuesArray();
      $type_levels = array();
      foreach ($levels as $level) {
        if ($level->type === $type) {
          $type_levels[] = $level;
        }
      }
      return $type_levels;
    } else {
      $levels = array();
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      $levels = $mc_result->toValuesArray();
      $type_levels = array();
      foreach ($levels as $level) {
        if ($level->type === $type) {
          $type_levels[] = $level;
        }
      }
      return $type_levels;
    }
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
  public static async function gen(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<Level> {
    $mc_result = self::getMCRecords('ALL_LEVELS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_levels = Map {};
      $result = await $db->queryf('SELECT * FROM levels ORDER BY id');
      foreach ($result->mapRows() as $row) {
        $all_levels->add(
          Pair {intval($row->get('id')), self::levelFromRow($row)},
        );
      }
      self::setMCRecords('ALL_LEVELS', $all_levels);
      invariant(
        $all_levels->contains($level_id) !== false,
        'level not found',
      );
      invariant(
        $all_levels->contains($level_id) !== false,
        'level not found',
      );
      $level = $all_levels->get($level_id);
      invariant($level instanceof Level, 'level should be of type Level');
      return $level;
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      invariant($mc_result->contains($level_id) !== false, 'level not found');
      $level = $mc_result->get($level_id);
      invariant($level instanceof Level, 'level should be of type Level');
      return $level;
    }
  }

  // Check if flag is correct.
  public static async function genCheckAnswer(
    int $level_id,
    string $answer,
  ): Awaitable<bool> {
    $level = await self::gen($level_id);
    $type = $level->getType();
    if ($type === "flag") {
      return trim($level->getFlag()) === trim($answer); // case sensitive
    } else {
      return
        strtoupper(trim($level->getFlag())) === strtoupper(trim($answer)); // case insensitive
    }
  }

  // Adjust bonus.
  public static async function genAdjustBonus(int $level_id): Awaitable<void> {
    $db = await self::genDb();

    await $db->queryf(
      'UPDATE levels SET bonus = GREATEST(bonus - bonus_dec, 0) WHERE id = %d LIMIT 1',
      $level_id,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Level data.
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

    self::invalidateMCRecords(); // Invalidate Memcached Level data.
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
      error_log('Failed to open lock file '.$lock_name);
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
            await ScoreLog::genAllPreviousScore($level_id, $team_id, false);
          if ($previous_score) {
            return false;
          }

          $level = await self::gen($level_id);

          // Calculate points to give
          $points = $level->getPoints() + $level->getBonus();

          // Log the score
          $captured = await ScoreLog::genLogValidScore(
            $level_id,
            $team_id,
            $points,
            $level->getType(),
          );

          if ($captured === true) {
            // Adjust bonus
            await self::genAdjustBonus($level_id);

            // Score!
            await $db->queryf(
              'UPDATE teams SET points = points + %d, last_score = NOW() WHERE id = %d LIMIT 1',
              $points,
              $team_id,
            );
          }

          self::invalidateMCRecords(); // Invalidate Memcached Level data.

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
            await ScoreLog::genAllPreviousScore($level_id, $team_id, false);
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

          self::invalidateMCRecords();

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
          list($hint, $score) = await \HH\Asio\va(
            HintLog::genPreviousHint($level_id, $team_id, false),
            ScoreLog::genAllPreviousScore($level_id, $team_id, false),
          );
          if ($hint || $score) {
            $penalty = 0;
          }

          // Make sure team has enough points to pay
          $team = await MultiTeam::genTeam($team_id);
          if ($team->getPoints() < $penalty) {
            return null;
          }

          // Adjust points and log the hint
          await \HH\Asio\va(
            $db->queryf(
              'UPDATE teams SET points = points - %d WHERE id = %d LIMIT 1',
              $penalty,
              $team_id,
            ),
            HintLog::genLogGetHint($level_id, $team_id, $penalty),
          );

          ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
          MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
          MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
          MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
          $completed_level = await MultiTeam::genCompletedLevel($level_id);
          if (count($completed_level) === 0) {
            MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
          }
          MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.

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
    string $entity_iso_code,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT COUNT(*) FROM levels WHERE type = %s AND title = %s AND entity_id IN (SELECT id FROM countries WHERE iso_code = %s)',
        $type,
        $title,
        $entity_iso_code,
      );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a level already exists by type, title and entity.
  public static async function genAlreadyExistById(
    int $level_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM levels WHERE id = %d',
      $level_id,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a level already exists by type, title and entity.
  public static async function genCountryIdForLevel(
    int $level_id,
  ): Awaitable<int> {
    $level = await self::gen($level_id);
    return $level->getEntityId();
  }

  public static async function getLevelIdByTypeTitleCountry(
    string $type,
    string $title,
    string $entity_iso_code,
  ): Awaitable<int> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT id FROM levels WHERE type = %s AND title = %s AND entity_id IN (SELECT id FROM countries WHERE iso_code = %s)',
        $type,
        $title,
        $entity_iso_code,
      );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(must_have_idx($result->mapRows()[0], 'id'));
  }

  public static async function genAlreadyExistUnknownCountry(
    string $type,
    string $title,
    string $description,
    int $points,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $result =
      await $db->queryf(
        'SELECT COUNT(*) FROM levels WHERE type = %s AND title = %s AND description = %s AND points = %d',
        $type,
        $title,
        $description,
        $points,
      );
    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  public static async function genLevelIdUnknownCountry(
    string $type,
    string $title,
    string $description,
    int $points,
  ): Awaitable<int> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT id FROM levels WHERE type = %s AND title = %s AND description = %s AND points = %d',
        $type,
        $title,
        $description,
        $points,
      );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(must_have_idx($result->mapRows()[0], 'id'));
  }

  public static async function genLevelUnknownCountry(
    string $type,
    string $title,
    string $description,
    int $points,
  ): Awaitable<Level> {

    $level_id = await self::genLevelIdUnknownCountry(
      $type,
      $title,
      $description,
      $points,
    );

    $level = await self::gen($level_id);
    return $level;
  }
}
