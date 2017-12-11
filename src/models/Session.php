<?hh // strict

class Session extends Model {

  protected static string $MC_KEY = 'sessions:';

  protected static Map<string, string>
    $MC_KEYS = Map {'SESSIONS' => 'active_sessions:'};

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
    await self::genSetTeamIdCacheSession($cookie, $data);
    if (Router::isRequestModal() ||
        Router::isRequestAjax() ||
        !Router::isRequestRouter()) {
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

  public static async function genSetTeamIdCacheSession(
    string $cookie,
    string $data,
  ): Awaitable<void> {
    $sessions = Map {};
    $session_data = Map {};
    $mc_result = self::getMCSession($cookie);
    if ($mc_result) {
      invariant(
        $mc_result instanceof Session,
        'mc_result should be of type Session',
      );
      $mc_result->team_id = self::decodeTeamId($data);
      self::setMCSession($cookie, $mc_result);
    } else {
      await self::genCreateCacheSession($cookie);
    }
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
      'INSERT INTO sessions (cookie, data, created_ts, last_access_ts, team_id, last_page_access) VALUES (%s, %s, NOW(), NOW(), 0, %s)',
      $cookie,
      $data,
      Router::getRequestedPage(),
    );
    if ($data !== '') {
      await self::genCreateCacheSession($cookie);
    }
  }

  // Create new session.
  public static async function genCreateCacheSession(
    string $cookie,
  ): Awaitable<void> {
    $session_data = await self::gen($cookie, true);
    self::setMCSession($cookie, $session_data);
  }

  // Retrieve the session by cookie.
  public static async function gen(
    string $cookie,
    bool $refresh = false,
  ): Awaitable<Session> {
    $mc_result = self::getMCSession($cookie);
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $result = await $db->queryf(
        'SELECT * FROM sessions WHERE cookie = %s LIMIT 1',
        $cookie,
      );

      invariant($result->numRows() === 1, 'Expected exactly one result');
      $session_data = self::sessionFromRow($result->mapRows()[0]);
      self::setMCSession($cookie, $session_data);
      return $session_data;
    } else {
      invariant(
        $mc_result instanceof Session,
        'cache return should be of type Session',
      );
      return $mc_result;
    }
  }

  // Checks if session exists by cookie.
  public static async function genSessionExist(
    string $cookie,
    bool $refresh = false,
  ): Awaitable<bool> {
    $mc_result = self::getMCSession($cookie);
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $result = await $db->queryf(
        'SELECT COUNT(*) FROM sessions WHERE cookie = %s',
        $cookie,
      );
      invariant($result->numRows() === 1, 'Expected exactly one result');
      if (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0) {
        await self::genCreateCacheSession($cookie);
        return true;
      }
    }
    return self::getMCSession($cookie) !== false;
  }

  public static async function genSessionDataIfExist(
    string $cookie,
    bool $refresh = false,
  ): Awaitable<string> {
    $mc_result = self::getMCSession($cookie);
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $result = await $db->queryf(
        'SELECT * FROM sessions WHERE cookie = %s LIMIT 1',
        $cookie,
      );

      if ($result->numRows() === 1) {
        $session = await self::gen($cookie);
        return $session->getData();
      } else {
        return '';
      }
    }
    $session = self::getMCSession($cookie);
    if ($session) {
      invariant(
        $session instanceof Session,
        'session should be of type Session',
      );
      return $session->getData();
    } else {
      return '';
    }
  }

  // Update the session for a given cookie.
  public static async function genUpdate(
    string $cookie,
    string $data,
    bool $refresh = false,
  ): Awaitable<void> {
    await self::genUpdateCacheSession($cookie, $data);
    if (!$refresh &&
        (Router::isRequestModal() ||
         Router::isRequestAjax() ||
         !Router::isRequestRouter())) {
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

  // Update the cache version of a session for a given cookie.
  public static async function genUpdateCacheSession(
    string $cookie,
    string $data,
  ): Awaitable<void> {
    if ($data === '') {
      return;
    }
    $mc_result = self::getMCSession($cookie);
    if ($mc_result) {
      invariant(
        $mc_result instanceof Session,
        'session should be of type Session',
      );
      $mc_result->last_access_ts = date("Y-m-d H:i:s");
      $mc_result->data = $data;
      $last_page_access = Router::getRequestedPage();
      if ($last_page_access !== 'index') {
        $mc_result->last_page_access = $last_page_access;
      }
      self::setMCSession($cookie, $mc_result);
    } else {
      await self::genCreateCacheSession($cookie);
    }
  }

  // Delete the session for a given cookie.
  public static async function genDelete(string $cookie): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'DELETE FROM sessions WHERE cookie = %s LIMIT 1',
      $cookie,
    );
    self::invalidateMCSessions($cookie);
  }

  // Delete the session for a given a team id.
  public static async function genDeleteByTeam(int $team_id): Awaitable<void> {
    $db = await self::genDb();
    $team_sessions = await self::genSessionsByTeam($team_id);
    await $db->queryf('DELETE FROM sessions WHERE team_id = %d', $team_id);
    foreach ($team_sessions as $session) {
      self::invalidateMCSessions($session->getCookie());
    }
  }

  public static async function genSessionsByTeam(
    int $team_id,
  ): Awaitable<array<Session>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM sessions WHERE team_id = %d',
      $team_id,
    );

    $sessions = array();
    foreach ($result->mapRows() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  // Delete all sessions for unprotected teams
  public static async function genDeleteAllUnprotected(): Awaitable<void> {
    $db = await self::genDb();
    $team_sessions = await self::genUnprotectedSessions();
    await $db->queryf(
      'DELETE FROM sessions USING sessions, teams WHERE sessions.team_id = teams.id and teams.protected = 0',
    );
    foreach ($team_sessions as $session) {
      self::invalidateMCSessions($session->getCookie());
    }
  }

  // Does cleanup of cookies.
  public static async function genCleanup(int $maxlifetime): Awaitable<void> {
    if (Router::isRequestModal() ||
        Router::isRequestAjax() ||
        !Router::isRequestRouter()) {
      return;
    }
    $db = await self::genDb();
    list($expired_sessions, $empty_sessions) = await \HH\Asio\va(
      self::genExpiredSessionsForCleanup($maxlifetime),
      self::genEmptySessionsForCleanup(),
    );

    foreach ($expired_sessions as $session) {
      $cached_session = self::getMCSession($session->getCookie());
      if ($cached_session === false) {
        continue;
      }
      invariant(
        $cached_session instanceof Session,
        'cached_session should be of type Session',
      );
      $cached_timestamp = strtotime($cached_session->last_access_ts);
      if (strtotime($cached_session->last_access_ts) <
          (time() - $maxlifetime)) {
        self::invalidateMCSessions($session->getCookie());
      } else {
        await self::genUpdate(
          $session->getCookie(),
          $session->getData(),
          true,
        );
      }
    }
    foreach ($empty_sessions as $session) {
      $cached_session = self::getMCSession($session->getCookie());
      if ($cached_session === false) {
        continue;
      }
      invariant(
        $cached_session instanceof Session,
        'cached_session should be of type Session',
      );
      if ($cached_session->getData() === '') {
        self::invalidateMCSessions($session->getCookie());
      } else if ($cached_session->getData() !== '') {
        await self::genUpdate(
          $session->getCookie(),
          $session->getData(),
          true,
        );
      }
    }
    // Clean up expired and empty sessions
    $queries = Vector {
      sprintf(
        'DELETE FROM sessions WHERE UNIX_TIMESTAMP(last_access_ts) < %d',
        time() - $maxlifetime,
      ),
      'DELETE FROM sessions WHERE data IS NULL',
    };
    await $db->multiQuery($queries);
  }

  public static async function genUnprotectedSessions(
  ): Awaitable<array<Session>> {
    $db = await self::genDb();
    $sessions = array();
    $result =
      await $db->queryf(
        'SELECT * FROM sessions, teams WHERE sessions.team_id = teams.id AND teams.protected = 0',
      );

    foreach ($result->mapRows() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  public static async function genExpiredSessionsForCleanup(
    int $maxlifetime,
  ): Awaitable<array<Session>> {
    $db = await self::genDb();
    $sessions = array();
    $result = await $db->queryf(
      'SELECT * FROM sessions WHERE UNIX_TIMESTAMP(last_access_ts) < %d',
      time() - $maxlifetime,
    );

    foreach ($result->mapRows() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  public static async function genEmptySessionsForCleanup(
  ): Awaitable<array<Session>> {
    $db = await self::genDb();
    $sessions = array();
    $result = await $db->queryf(
      'SELECT * FROM sessions WHERE IFNULL(data, %s) = %s',
      '',
      '',
    );

    foreach ($result->mapRows() as $row) {
      $sessions[] = self::sessionFromRow($row);
    }

    return $sessions;
  }

  // All the sessions
  public static async function genAllSessions(
    bool $refresh = false,
  ): Awaitable<array<Session>> {
    if ($refresh) {
      $db = await self::genDb();
      $sessions = array();
      $result = await $db->queryf(
        'SELECT * FROM sessions ORDER BY last_access_ts DESC',
      );

      $sessions = array();
      foreach ($result->mapRows() as $row) {
        $sessions[] = self::sessionFromRow($row);
      }
      return $sessions;
    } else {
      $mc = self::getMc();
      $sessions = array();
      $cached_sessions = array();
      /* HH_IGNORE_ERROR[4053]: HHVM doesn't beleive there is a getAllKeys() method, there is... */
      //$mc_keys = $mc->getAllKeys();
      /* Memcached::getAllKeys() is not working in HHVM 3.21 - For now we will flush Memcache in place of the call.
       *  Flushing the cache will be slower and will negatively impact performance.
       *  As soon as getAllKeys() regains support in HHVM, or the functionality it replaced, this should be updated.
       */
      $mc_keys = array();
      self::flushMCCluster();
      $all_sessions = preg_grep(
        '/'.self::$MC_KEY.self::$MC_KEYS->get('SESSIONS').'/',
        $mc_keys,
      );
      foreach ($all_sessions as $session_key) {
        $session_key =
          substr(strstr(substr(strstr($session_key, ':'), 1), ':'), 1);
        $session = self::getMCSession($session_key);
        if ($session !== false &&
            $session instanceof Session &&
            $session->getTeamId() !== 0) {
          $cached_sessions[] = $session_key;
          $sessions[] = $session;
        }
      }
      $db = await self::genDb();
      $result = await $db->queryf('SELECT * FROM sessions');

      foreach ($result->mapRows() as $row) {
        if (!in_array($row->get('cookie'), $cached_sessions)) {
          $sessions[] = self::sessionFromRow($row);
        }
      }
      invariant(
        is_array($sessions),
        '$sessions should be an array of Session',
      );
      return $sessions;
    }
  }

  private static function setMCSession(string $key, mixed $records): void {
    $key = str_replace(' ', '', $key);
    self::writeMCCluster(
      self::$MC_KEY.self::$MC_KEYS->get('SESSIONS').$key,
      $records,
    );
  }

  private static function getMCSession(string $key): mixed {
    $mc = self::getMc();
    $key = str_replace(' ', '', $key);
    $mc_result =
      $mc->get(static::$MC_KEY.static::$MC_KEYS->get('SESSIONS').$key);
    return $mc_result;
  }

  public static function invalidateMCSessions(?string $key = null): void {
    $mc = self::getMc();
    $key = str_replace(' ', '', $key);
    if ($key === null) {
      /* HH_IGNORE_ERROR[4053]: HHVM doesn't beleive there is a getAllKeys() method, there is... */
      //$mc_keys = $mc->getAllKeys();
      /* Memcached::getAllKeys() is not working in HHVM 3.21 - For now we will flush Memcache in place of the call.
       *  Flushing the cache will be slower and will negatively impact performance.
       *  As soon as getAllKeys() regains support in HHVM, or the functionality it replaced, this should be updated.
       */
      $mc_keys = array();
      self::flushMCCluster();
      $all_sessions = preg_grep(
        '/'.self::$MC_KEY.self::$MC_KEYS->get('SESSIONS').'/',
        $mc_keys,
      );
      foreach ($all_sessions as $session_key) {
        self::invalidateMCCluster($session_key);
      }
    } else {
      self::invalidateMCCluster(
        self::$MC_KEY.self::$MC_KEYS->get('SESSIONS').$key,
      );
    }
  }
}
