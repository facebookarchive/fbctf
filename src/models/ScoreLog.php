<?hh // strict

class ScoreLog extends Model {

  protected static string $MC_KEY = 'scorelog:';

  protected static Map<string, string>
    $MC_KEYS = Map {"LEVEL_CAPTURES" => "capture_teams"};

  private function __construct(
    private int $id,
    private string $ts,
    private int $team_id,
    private int $points,
    private int $level_id,
    private string $type,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getLevelId(): int {
    return $this->level_id;
  }

  public function getType(): string {
    return $this->type;
  }

  private static function scorelogFromRow(Map<string, string> $row): ScoreLog {
    return new ScoreLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      intval(must_have_idx($row, 'team_id')),
      intval(must_have_idx($row, 'points')),
      intval(must_have_idx($row, 'level_id')),
      must_have_idx($row, 'type'),
    );
  }

  // Get all scores.
  public static async function genAllScores(): Awaitable<array<ScoreLog>> {
    $db = await self::genDb();
    $result = await $db->queryf('SELECT * FROM scores_log ORDER BY ts DESC');

    $scores = array();
    foreach ($result->mapRows() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  // Reset all scores.
  public static async function genResetScores(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM scores_log WHERE id > 0');
  }

  // Check if there is a previous score.
  public static async function genPreviousScore(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $mc_result = self::getMCRecords('LEVEL_CAPTURES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $level_captures = Map {};
      $result = await $db->queryf('SELECT level_id, team_id FROM scores_log');
      foreach ($result->mapRows() as $row) {
        if ($level_captures->contains(intval($row->get("level_id")))) {
          $level_capture_teams =
            $level_captures->get(intval($row->get("level_id")));
          /* HH_IGNORE_ERROR[4064] */
          $level_capture_teams->add(intval($row->get("team_id")));
          $level_captures->set(
            intval($row->get("level_id")),
            $level_capture_teams,
          );
        } else {
          $level_capture_teams = Vector {};
          $level_capture_teams->add(intval($row->get("team_id")));
          $level_captures->add(
            Pair {intval($row->get("level_id")), $level_capture_teams},
          );
        }
      }
      self::setMCRecords('LEVEL_CAPTURES', new Map($level_captures));
    }
    $level_captures = self::getMCRecords('LEVEL_CAPTURES');
    /* HH_IGNORE_ERROR[4062] */
    if ($level_captures->contains($level_id)) {
      if ($any_team) {
        $team_id_key = /* HH_IGNORE_ERROR[4062]: getMCRecords returns a 'mixed' type, HHVM is unsure of the type at this point */
          $level_captures->get($level_id)->linearSearch($team_id);
        if ($team_id_key != -1) {
          /* HH_IGNORE_ERROR[4062] */
          $level_captures->get($level_id)->removeKey($team_id_key);
        }
        /* HH_IGNORE_ERROR[4062] */
        return intval(count($level_captures->get($level_id))) > 0;
      } else {
        /* HH_IGNORE_ERROR[4062] */
        return $level_captures->get($level_id)->linearSearch($team_id) != -1;
      }
    } else {
      return false;
    }
  }

  // Get all scores by team.
  public static async function genAllScoresByTeam(
    int $team_id,
  ): Awaitable<array<ScoreLog>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM scores_log WHERE team_id = %d ORDER BY ts DESC',
      $team_id,
    );

    $scores = array();
    foreach ($result->mapRows() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  // Get all scores by type.
  public static async function genAllScoresByType(
    string $type,
  ): Awaitable<array<ScoreLog>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM scores_log WHERE type = %s ORDER BY ts DESC',
      $type,
    );

    $scores = array();
    foreach ($result->mapRows() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  // Log successful score.
  public static async function genLogValidScore(
    int $level_id,
    int $team_id,
    int $points,
    string $type,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO scores_log (ts, level_id, team_id, points, type) VALUES (NOW(), %d, %d, %d, %s)',
      $level_id,
      $team_id,
      $points,
      $type,
    );
  }
}
