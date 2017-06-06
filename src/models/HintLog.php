<?hh // strict

class HintLog extends Model {

  protected static string $MC_KEY = 'hintlog:';

  protected static Map<string, string>
    $MC_KEYS = Map {'USED_HINTS' => 'hint_level_teams'};

  private function __construct(
    private int $id,
    private string $ts,
    private int $level_id,
    private int $team_id,
    private int $penalty,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getLevelId(): int {
    return $this->level_id;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getPenalty(): int {
    return $this->penalty;
  }

  private static function hintlogFromRow(Map<string, string> $row): HintLog {
    return new HintLog(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      intval(must_have_idx($row, 'level_id')),
      intval(must_have_idx($row, 'team_id')),
      intval(must_have_idx($row, 'penalty')),
    );
  }

  // Log hint request hint.
  public static async function genLogGetHint(
    int $level_id,
    int $team_id,
    int $penalty,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO hints_log (ts, level_id, team_id, penalty) VALUES (NOW(), %d, %d, %d)',
      $level_id,
      $team_id,
      $penalty,
    );
    self::invalidateMCRecords(); // Invalidate Memcached HintLog data.
  }

  public static async function genResetHints(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM hints_log WHERE id > 0');
  }

  // Check if there is a previous hint.
  public static async function genPreviousHint(
    int $level_id,
    int $team_id,
    bool $any_team,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCRecords('USED_HINTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $hints_used = Map {};
      $result = await $db->queryf('SELECT level_id, team_id FROM hints_log');
      foreach ($result->mapRows() as $row) {
        if ($hints_used->contains(intval($row->get('level_id')))) {
          $hints_used_teams = $hints_used->get(intval($row->get('level_id')));
          invariant(
            $hints_used_teams !== null,
            'hints_used_teams should not be null',
          );
          $hints_used_teams->add(intval($row->get('team_id')));
          $hints_used->set(intval($row->get('level_id')), $hints_used_teams);
        } else {
          $hints_used_teams = Vector {};
          $hints_used_teams->add(intval($row->get('team_id')));
          $hints_used->add(
            Pair {intval($row->get('level_id')), $hints_used_teams},
          );
        }
      }
      self::setMCRecords('USED_HINTS', new Map($hints_used));
      if ($hints_used->contains($level_id)) {
        if ($any_team) {
          $hints_used_teams = $hints_used->get($level_id);
          invariant(
            $hints_used_teams !== null,
            'hints_used_teams should not be null',
          );
          $team_id_key = $hints_used_teams->linearSearch($team_id);
          if ($team_id_key !== -1) {
            $hints_used_teams->removeKey($team_id_key);
          }
          return intval(count($hints_used_teams)) > 0;
        } else {
          $hints_used_teams = $hints_used->get($level_id);
          invariant(
            $hints_used_teams !== null,
            'hints_used_teams should not be null',
          );
          $team_id_key = $hints_used_teams->linearSearch($team_id);
          return $team_id_key !== -1;
        }
      } else {
        return false;
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'hints_used should be of type Map',
      );
      if ($mc_result->contains($level_id)) {
        if ($any_team) {
          $hints_used_teams = $mc_result->get($level_id);
          invariant(
            $hints_used_teams !== null,
            'hints_used_teams should not be null',
          );
          $team_id_key = $hints_used_teams->linearSearch($team_id);
          if ($team_id_key !== -1) {
            $hints_used_teams->removeKey($team_id_key);
          }
          return intval(count($hints_used_teams)) > 0;
        } else {
          $hints_used_teams = $mc_result->get($level_id);
          invariant(
            $hints_used_teams !== null,
            'hints_used_teams should not be null',
          );
          $team_id_key = $hints_used_teams->linearSearch($team_id);
          return $team_id_key !== -1;
        }
      } else {
        return false;
      }
    }
  }

  // Get all hints.
  public static async function genAllHints(): Awaitable<array<HintLog>> {
    $db = await self::genDb();
    $result = await $db->queryf('SELECT * FROM hints_log ORDER BY ts DESC');

    $hints = array();
    foreach ($result->mapRows() as $row) {
      $hints[] = self::hintlogFromRow($row);
    }

    return $hints;
  }

  // Get all hints by team.
  public static async function genAllHintsByTeam(
    int $team_id,
  ): Awaitable<array<HintLog>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM hints_log WHERE team_id = %d ORDER BY ts DESC',
      $team_id,
    );

    $hints = array();
    foreach ($result->mapRows() as $row) {
      $hints[] = self::hintlogFromRow($row);
    }

    return $hints;
  }

  // Get all hints by level.
  public static async function genAllHintsByLevel(
    int $level_id,
  ): Awaitable<array<HintLog>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM hints_log WHERE level_id = %d',
      $level_id,
    );

    $hints = array();
    foreach ($result->mapRows() as $row) {
      $hints[] = self::hintlogFromRow($row);
    }

    return $hints;
  }
}
