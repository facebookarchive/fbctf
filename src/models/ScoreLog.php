<?hh // strict

class ScoreLog extends Model {

  protected static string $MC_KEY = 'scorelog:';

  protected static Map<string, string>
    $MC_KEYS = Map {'LEVEL_CAPTURES' => 'capture_teams'};

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
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  // Check if there is a previous score.
  public static async function genPreviousScore(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('LEVEL_CAPTURES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $level_captures = Map {};
      $result = await $db->queryf('SELECT level_id, team_id FROM scores_log');
      foreach ($result->mapRows() as $row) {
        if ($level_captures->contains(intval($row->get('level_id')))) {
          $level_capture_teams =
            $level_captures->get(intval($row->get('level_id')));
          invariant(
            $level_capture_teams instanceof Vector,
            'level_capture_teams should of type Vector and not null',
          );
          $level_capture_teams->add(intval($row->get('team_id')));
          $level_captures->set(
            intval($row->get('level_id')),
            $level_capture_teams,
          );
        } else {
          $level_capture_teams = Vector {};
          $level_capture_teams->add(intval($row->get('team_id')));
          $level_captures->add(
            Pair {intval($row->get('level_id')), $level_capture_teams},
          );
        }
      }
      self::setMCRecords('LEVEL_CAPTURES', new Map($level_captures));
      if ($level_captures->contains($level_id)) {
        if ($any_team) {
          $level_capture_teams = $level_captures->get($level_id);
          invariant(
            $level_capture_teams instanceof Vector,
            'level_capture_teams should of type Vector and not null',
          );
          $team_id_key = $level_capture_teams->linearSearch($team_id);
          if ($team_id_key !== -1) {
            $level_capture_teams->removeKey($team_id_key);
          }
          return intval(count($level_capture_teams)) > 0;
        } else {
          $level_capture_teams = $level_captures->get($level_id);
          invariant(
            $level_capture_teams instanceof Vector,
            'level_capture_teams should of type Vector and not null',
          );
          $team_id_key = $level_capture_teams->linearSearch($team_id);
          return $team_id_key !== -1;
        }
      } else {
        return false;
      }
    }
    invariant(
      $mc_result instanceof Map,
      'cache return should of type Map and not null',
    );
    if ($mc_result->contains($level_id)) {
      if ($any_team) {
        $level_capture_teams = $mc_result->get($level_id);
        invariant(
          $level_capture_teams instanceof Vector,
          'level_capture_teams should of type Vector and not null',
        );
        $team_id_key = $level_capture_teams->linearSearch($team_id);
        if ($team_id_key !== -1) {
          $level_capture_teams->removeKey($team_id_key);
        }
        return intval(count($level_capture_teams)) > 0;
      } else {
        $level_capture_teams = $mc_result->get($level_id);
        invariant(
          $level_capture_teams instanceof Vector,
          'level_capture_teams should of type Vector and not null',
        );
        $team_id_key = $level_capture_teams->linearSearch($team_id);
        return $team_id_key !== -1;
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
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }
}
