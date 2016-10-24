<?hh // strict

class Session extends Model {
  private function __construct(
    private int $id,
    private string $cookie,
    private string $data,
    private int $team_id,
    private string $created_ts,
    private string $last_access_ts,
    private string $last_page_access,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getCookie(): string {
    return $this->cookie;
  }

  public function getData(): string {
    return $this->data;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  public function getLastAccessTs(): string {
    return $this->last_access_ts;
  }

  public function getLastPageAccess(): string {
    return $this->last_page_access;
  }

  private static function decodeTeamId(string $data): int {
    // This is a bit janky
    $delim = explode('team_id|', $data)[1];
    $serialized = explode('name|', $delim)[0];
    $unserialized = strval(unserialize($serialized));

    return intval($unserialized);
  }

  public static async function genSetTeamId(
    string $cookie,
    string $data,
  ): Awaitable<void> {
    if (Router::isRequestAjax() || !Router::isRequestRouter()) {
      return;
    }
    $team_id = self::decodeTeamId($data);
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE sessions SET team_id = %d WHERE cookie = %s LIMIT 1',
      $team_id,
      $cookie,
    );
  }

  private static function sessionFromRow(Map<string, string> $row): Session {
    return new Session(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'cookie'),
      must_have_idx($row, 'data'),
      intval(must_have_idx($row, 'team_id')),
      must_have_idx($row, 'created_ts'),
      must_have_idx($row, 'last_access_ts'),
      must_have_idx($row, 'last_page_access'),
    );
  }

  // Create new session.
  public static async function genCreate(
    string $cookie,
    string $data,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO sessions (cookie, data, created_ts, last_access_ts, team_id, last_page_access) VALUES (%s, %s, NOW(), NOW(), 1, %s)',
      $cookie,
      $data,
      Router::getRequestedPage(),
    );
  }

  // Retrieve the session by cookie.
  public static async function gen(string $cookie): Awaitable<Session> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM sessions WHERE cookie = %s LIMIT 1',
      $cookie,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');

    return self::sessionFromRow($result->mapRows()[0]);
  }

  // Checks if session exists by cookie.
  public static async function genSessionExist(
    string $cookie,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT COUNT(*) FROM sessions WHERE cookie = %s',
      $cookie,
    );
    invariant($result->numRows() === 1, 'Expected exactly one result');

    return intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0;
  }

  public static async function genSessionDataIfExist(string $cookie): Awaitable<string> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM sessions WHERE cookie = %s LIMIT 1',
      $cookie,
    );

    if ($result->numRows() === 1) {
      $session = self::sessionFromRow($result->mapRows()[0]);
      return $session->getData();
    } else {
      return '';
    }
  }

  // Update the session for a given cookie.
  public static async function genUpdate(
    string $cookie,
    string $data,
  ): Awaitable<void> {
    if (Router::isRequestAjax() || !Router::isRequestRouter()) {
      return;
    }
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE sessions SET last_access_ts = NOW(), data = %s, last_page_access = %s WHERE cookie = %s LIMIT 1',
      $data,
      Router::getRequestedPage(),
      $cookie,
    );
  }

  // Delete the session for a given cookie.
  public static async function genDelete(string $cookie): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'DELETE FROM sessions WHERE cookie = %s LIMIT 1',
      $cookie,
    );
  }

  // Delete the session for a given a team id.
  public static async function genDeleteByTeam(int $team_id): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'DELETE FROM sessions WHERE team_id = %d LIMIT 1',
      $team_id,
    );
  }

  // Does cleanup of cookies.
  public static async function genCleanup(int $maxlifetime): Awaitable<void> {
    if (Router::isRequestAjax() || !Router::isRequestRouter()) {
      return;
    }
    $db = await self::genDb();
    // Clean up expired sessions
    await $db->queryf(
      'DELETE FROM sessions WHERE UNIX_TIMESTAMP(last_access_ts) < %d',
      time() - $maxlifetime,
    );
    // Clean up empty sessions
    await $db->queryf(
      'DELETE FROM sessions WHERE IFNULL(data, %s) = %s',
      '',
      '',
    );
  }

  // All the sessions
  public static async function genAllSessions(): Awaitable<array<Session>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM sessions ORDER BY last_access_ts DESC',
    );

    $sessions = array();
    foreach ($result->mapRows() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }
}
