<?hh // strict

class ActivityLog extends Model {

  protected static string $MC_KEY = 'activitylog:';

  protected static Map<string, string>
    $MC_KEYS = Map {'ALL_ACTIVITY' => 'activity'};

  private function __construct(
    private int $id,
    private string $subject,
    private string $action,
    private string $entity,
    private string $message,
    private string $arguments,
    private string $ts,
    private string $formatted_subject = '',
    private string $formatted_entity = '',
    private string $formatted_message = '',
    private bool $visible = true,
  ) {
    $formatted_subject =
      \HH\Asio\join(self::genFormatString("%s", $this->subject));
    $this->formatted_subject = $formatted_subject;
    $formatted_entity =
      \HH\Asio\join(self::genFormatString("%s", $this->entity));
    $this->formatted_entity = $formatted_entity;
    $formatted_message =
      \HH\Asio\join(self::genFormatString($this->message, $this->arguments));
    $this->formatted_message = $formatted_message;
    $visible =
      \HH\Asio\join(self::genLogEntryVisible($this->subject, $this->action));
    $this->visible = $visible;
  }

  public function getId(): int {
    return $this->id;
  }

  public function getSubject(): string {
    return $this->subject;
  }

  public function getAction(): string {
    return $this->action;
  }

  public function getEntity(): string {
    return $this->entity;
  }

  public function getMessage(): string {
    return $this->message;
  }

  public function getArguments(): string {
    return $this->arguments;
  }

  public function getFormattedSubject(): string {
    return $this->formatted_subject;
  }

  public function getFormattedEntity(): string {
    return $this->formatted_entity;
  }

  public function getFormattedMessage(): string {
    return $this->formatted_message;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getVisible(): bool {
    return $this->visible;
  }

  private static function activitylogFromRow(
    Map<string, string> $row,
  ): ActivityLog {
    return new ActivityLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'subject'),
      must_have_idx($row, 'action'),
      must_have_idx($row, 'entity'),
      must_have_idx($row, 'message'),
      must_have_idx($row, 'arguments'),
      must_have_idx($row, 'ts'),
    );
  }

  public static async function genFormatString(
    string $string,
    string $arguments,
  ): Awaitable<string> {
    if ($arguments !== '') {
      $variables = array();
      $values_array = explode(',', $arguments);
      foreach ($values_array as $value) {
        list($class, $id) = explode(':', $value);
        switch ($class) {
          case "Team":
            $team_exists = await Team::genTeamExistById(intval($id));
            if ($team_exists === true) {
              $team = await MultiTeam::genTeam(intval($id));
              $variables[] = $team->getName();
            } else {
              return '';
            }
            break;
          case "Level":
            $level_exists = await Level::genAlreadyExistById(intval($id));
            if ($level_exists === true) {
              $level = await Level::gen(intval($id));
              $variables[] = $level->getTitle();
            } else {
              return '';
            }
            break;
          case "Country":
            $country_exists = await Country::genCheckExistsById(intval($id));
            if ($country_exists === true) {
              $country = await Country::gen(intval($id));
              $variables[] = $country->getIsoCode();
            } else {
              return '';
            }
            break;
            // FALLTHROUGH
          default:
            return '';
            break;
        }
      }
      $formatted = vsprintf($string, $variables);
      return $formatted;
    }
    return $string;
  }

  public static async function genLogEntryVisible(
    string $subject,
    string $action,
  ): Awaitable<bool> {
    if ($subject === '') {
      return true;
    }
    list($class, $id) = explode(':', $subject);
    if ($class === 'Team' && $action === 'captured') {
      $team_exists = await Team::genTeamExistById(intval($id));
      if ($team_exists === true) {
        $team = await MultiTeam::genTeam(intval($id));
        return $team->getVisible();
      } else
        return false;
    }
    return true;
  }

  public static async function genCreate(
    string $subject,
    string $action,
    string $entity,
    string $message,
    string $arguments,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO activity_log (ts, subject, action, entity, message, arguments) (SELECT NOW(), %s, %s, %s, %s, %s) LIMIT 1',
      $subject,
      $action,
      $entity,
      $message,
      $arguments,
    );

    self::invalidateMCRecords(); // Invalidate Memcached ActivityLog data.
  }

  public static async function genCaptureLog(
    int $team_id,
    int $level_id,
  ): Awaitable<void> {
    //$level = await Level::gen($level_id);
    $country_id = await Level::genCountryIdForLevel($level_id);
    await self::genCreateActionLog(
      "Team",
      $team_id,
      "captured",
      "Country",
      $country_id,
    );
  }

  public static async function genCreateActionLog(
    string $subject_class,
    int $subject_id,
    string $action,
    string $entity_class,
    int $entity_id,
  ): Awaitable<void> {
    await self::genCreate(
      "$subject_class:$subject_id",
      $action,
      "$entity_class:$entity_id",
      '',
      '',
    );
  }

  public static async function genCreateGameActionLog(
    string $subject_class,
    int $subject_id,
    string $action,
    string $entity_class,
    int $entity_id,
  ): Awaitable<void> {
    list($config_game, $config_pause) = await \HH\Asio\va(
      Configuration::gen('game'),
      Configuration::gen('game_paused'),
    );
    if ((intval($config_game->getValue()) === 1) &&
        (intval($config_pause->getValue()) === 0)) {
      await self::genCreate(
        "$subject_class:$subject_id",
        $action,
        "$entity_class:$entity_id",
        '',
        '',
      );
    }
  }

  public static async function genAdminLog(
    string $action,
    string $entity_class,
    int $entity_id,
  ): Awaitable<void> {
    if (SessionUtils::sessionActive() === false) {
      return;
    }
    await self::genCreateGameActionLog(
      "Team",
      SessionUtils::sessionTeam(),
      $action,
      $entity_class,
      $entity_id,
    );
  }

  public static async function genCreateGenericLog(
    string $message,
    string $arguments = '',
  ): Awaitable<void> {
    await self::genCreate('', '', '', $message, $arguments);
  }

  public static async function genDelete(
    int $activity_log_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'DELETE FROM activity_log WHERE id = %d LIMIT 1',
      $activity_log_id,
    );

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static async function genDeleteAll(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('TRUNCATE TABLE activity_log');

    self::invalidateMCRecords(); // Invalidate Memcached Announcement data.
  }

  public static async function genAllActivity(
    bool $refresh = false,
  ): Awaitable<array<ActivityLog>> {
    $mc_result = self::getMCRecords('ALL_ACTIVITY');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $activity_log_lines = array();
      $result = await $db->query(
        'SELECT * FROM activity_log ORDER BY ts DESC LIMIT 100',
      );
      foreach ($result->mapRows() as $row) {
        $activity_log = self::activitylogFromRow($row);
        if (($activity_log->getFormattedMessage() !== '') ||
            (($activity_log->getFormattedSubject() !== '') &&
             ($activity_log->getFormattedEntity() !== ''))) {
          $activity_log_lines[] = $activity_log;
        }
      }
      self::setMCRecords('ALL_ACTIVITY', $activity_log_lines);
      return $activity_log_lines;
    }
    invariant(
      is_array($mc_result),
      'cache return should be an array of ActivityLog',
    );
    return $mc_result;
  }
}
