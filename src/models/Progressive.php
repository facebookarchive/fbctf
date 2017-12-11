<?hh // strict

class Progressive extends Model {

  protected static string $MC_KEY = 'progressive:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'ITERATION_COUNT' => 'iterations_count',
      'PROGRESSIVE_POINTS' => 'points_by_teamname',
    };

  private function __construct(
    private int $id,
    private string $ts,
    private string $team_name,
    private int $points,
    private int $iteration,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getTs(): string {
    return $this->ts;
  }

  public function getTeamName(): string {
    return $this->team_name;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getIteration(): int {
    return $this->iteration;
  }

  public static async function genGameStatus(): Awaitable<bool> {
    $config = await Configuration::gen('game');
    return $config->getValue() === '1';
  }

  public static async function genCycle(): Awaitable<int> {
    $config = await Configuration::gen('progressive_cycle');
    return intval($config->getValue());
  }

  private static function progressiveFromRow(
    Map<string, string> $row,
  ): Progressive {
    return new Progressive(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'ts'),
      must_have_idx($row, 'team_name'),
      intval(must_have_idx($row, 'points')),
      intval(must_have_idx($row, 'iteration')),
    );
  }

  // Progressive points.
  public static async function genProgressiveScoreboard(
    string $team_name,
    bool $refresh = false,
  ): Awaitable<array<Progressive>> {
    $mc_result = self::getMCRecords('PROGRESSIVE_POINTS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $progressive = array();
      $result =
        await $db->queryf(
          'SELECT MAX(id) as id, MAX(ts) as ts, team_name, MAX(points) as points, iteration FROM progressive_log GROUP BY team_name, iteration, id ORDER BY points ASC',
        );
      foreach ($result->mapRows() as $row) {
        $progressive[$row->get('team_name')][] =
          self::progressiveFromRow($row);
      }
      self::setMCRecords('PROGRESSIVE_POINTS', new Map($progressive));
      $progressive = new Map($progressive);
      if ($progressive->contains($team_name)) {
        $team_progressive = $progressive->get($team_name);
        invariant(
          is_array($team_progressive),
          'team_progressive should not an array of Progressive',
        );
        return $team_progressive;
      } else {
        return array();
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ($mc_result->contains($team_name)) {
        $team_progressive = $mc_result->get($team_name);
        invariant(
          is_array($team_progressive),
          'team_progressive should not an array of Progressive',
        );
        return $team_progressive;
      } else {
        return array();
      }
    }
  }

  // Count how many iterations of the progressive scoreboard we have.
  public static async function genCount(
    bool $refresh = false,
  ): Awaitable<int> {
    $mc_result = self::getMCRecords('ITERATION_COUNT');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $result = await $db->queryf(
        'SELECT COUNT(DISTINCT(iteration)) AS C FROM progressive_log',
      );
      invariant($result->numRows() === 1, 'Expected exactly one result');
      self::setMCRecords(
        'ITERATION_COUNT',
        intval($result->mapRows()[0]['C']),
      );
      return intval($result->mapRows()[0]['C']);
    } else {
      return intval($mc_result);
    }
  }

  // Acquire the data for one iteration of the progressive scoreboard.
  public static async function genTake(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO progressive_log (ts, team_name, points, iteration) (SELECT NOW(), name, points, (SELECT IFNULL(MAX(iteration)+1, 1) FROM progressive_log) FROM teams)',
    );
    self::invalidateMCRecords(); // Invalidate Memcached Progressive data.
  }

  // Reset the progressive scoreboard.
  public static async function genReset(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('DELETE FROM progressive_log WHERE id > 0');
    self::invalidateMCRecords(); // Invalidate Memcached Progressive data.
  }

  // Kick off the progressive scoreboard in the background.
  public static async function genRun(): Awaitable<void> {
    $document_root = must_have_string(Utils::getSERVER(), 'DOCUMENT_ROOT');
    $cmd =
      'hhvm -vRepo.Central.Path=/var/run/hhvm/.hhvm.hhbc_progressive '.
      $document_root.
      '/scripts/progressive.php > /dev/null 2>&1 & echo $!';
    $pid = shell_exec($cmd);
    await Control::genStartScriptLog(intval($pid), 'progressive', $cmd);
  }

  // Stop the progressive scoreboard process in the background
  public static async function genStop(): Awaitable<void> {
    // Kill running process
    $pid = await Control::genScriptPid('progressive');
    if ($pid > 0) {
      exec('kill -9 '.escapeshellarg(strval($pid)));
    }
    // Mark process as stopped
    await Control::genStopScriptLog($pid);
  }
}
