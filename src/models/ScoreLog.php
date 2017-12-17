<?hh // strict

class ScoreLog extends Model {

  protected static string $MC_KEY = 'scorelog:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'LEVEL_CAPTURES' => 'capture_teams',
      'ALL_SCORES' => 'all_scores',
      'SCORES_BY_TEAM' => 'scores_by_team',
      'ALL_LEVEL_CAPTURES' => 'all_capture_teams',
    };

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
    $mc_result = self::getMCRecords('ALL_SCORES');
    if (!$mc_result || count($mc_result) === 0) {
      $result =
        await $db->queryf('SELECT * FROM scores_log ORDER BY ts DESC');

      $scores = array();
      foreach ($result->mapRows() as $row) {
        $scores[] = self::scorelogFromRow($row);
      }

      self::setMCRecords('ALL_SCORES', $scores);
      return $scores;
    } else {
      invariant(
        is_array($mc_result),
        'cache return should be an array of type ScoreLog and not null',
      );
      return $mc_result;
    }
  }

  // Reset all scores.
  public static async function genResetScores(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM scores_log WHERE id > 0');
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  // Check if there is a previous score. - honors team visibility
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
      $result =
        await $db->queryf(
          'SELECT level_id, team_id FROM scores_log LEFT JOIN teams ON scores_log.team_id = teams.id WHERE teams.visible = 1',
        );
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

  // Check if there is a previous score. - ignores team visibility
  public static async function genAllPreviousScore(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('ALL_LEVEL_CAPTURES');
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
      self::setMCRecords('ALL_LEVEL_CAPTURES', new Map($level_captures));
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
    bool $refresh = false,
  ): Awaitable<array<ScoreLog>> {
    $db = await self::genDb();
    $mc_result = self::getMCRecords('SCORES_BY_TEAM');
    if (!$mc_result || count($mc_result) === 0) {
      $scores = array();
      $result =
        await $db->queryf('SELECT * FROM scores_log ORDER BY ts DESC');
      foreach ($result->mapRows() as $row) {
        $scores[$row->get('team_id')][] = self::scorelogFromRow($row);
      }
      self::setMCRecords('SCORES_BY_TEAM', new Map($scores));
      $team_scores = array();
      $scores = new Map($scores);
      if ($scores->contains($team_id)) {
        $team_scores = $scores->get($team_id);
        invariant(
          is_array($team_scores),
          'team_scores should an array and not null',
        );
        return $team_scores;
      }
      return $team_scores;
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be a Map of type ScoreLog and not null',
      );
      $team_scores = array();
      if ($mc_result->contains($team_id)) {
        $team_scores = $mc_result->get($team_id);
        invariant(
          is_array($team_scores),
          'team_scores should an array and not null',
        );
        return $team_scores;
      }
      return $team_scores;
    }
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

  // Get all scores by level.
  public static async function genAllScoresByLevel(
    int $level_id,
  ): Awaitable<array<ScoreLog>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM scores_log WHERE level_id = %d',
      $level_id,
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
  ): Awaitable<bool> {
    $db = await self::genDb();
    //'INSERT INTO scores_log (ts, level_id, team_id, points, type) VALUES (NOW(), %d, %d, %d, %s)',
    $result =
      await $db->queryf(
        'INSERT INTO scores_log (ts, level_id, team_id, points, type) SELECT NOW(), %d, %d, %d, %s FROM DUAL WHERE NOT EXISTS (SELECT * FROM scores_log WHERE level_id = %d AND team_id = %d)',
        $level_id,
        $team_id,
        $points,
        $type,
        $level_id,
        $team_id,
      );

    $captured = $result->numRowsAffected() > 0 ? true : false;

    if ($captured === true) {
      await ActivityLog::genCaptureLog($team_id, $level_id);
      self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
      ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
      MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
      MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
      MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
      $completed_level = await MultiTeam::genCompletedLevel($level_id);
      if (count($completed_level) === 0) {
        MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
      }
      MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    }

    return $captured;
  }

  public static async function genScoreLogUpdate(
    int $level_id,
    int $team_id,
    int $points,
    string $type,
    string $timestamp,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE scores_log SET ts = %s, level_id = %d, team_id = %d, points = %d, type = %s WHERE level_id = %d AND team_id = %d',
      $timestamp,
      $level_id,
      $team_id,
      $points,
      $type,
      $level_id,
      $team_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  public static async function genUpdateScoreLogBonus(
    int $level_id,
    int $team_id,
    int $points,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE scores_log SET ts = ts, points = %d WHERE level_id = %d AND team_id = %d',
      $points,
      $level_id,
      $team_id,
    );
    self::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP'); // Invalidate Memcached MultiTeam data.
  }

  public static async function genLevelScores(
    int $level_id,
  ): Awaitable<array<ScoreLog>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM scores_log WHERE level_id = %d ORDER BY ts ASC',
      $level_id,
    );

    $scores = array();
    foreach ($result->mapRows() as $row) {
      $scores[] = self::scorelogFromRow($row);
    }

    return $scores;
  }

  public static async function genLevelScoreByTeam(
    int $team_id,
    int $level_id,
  ): Awaitable<ScoreLog> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM scores_log WHERE team_id = %d AND level_id = %d',
      $team_id,
      $level_id,
    );

    return self::scorelogFromRow($result->mapRows()[0]);
  }
}
