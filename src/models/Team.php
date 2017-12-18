<?hh // strict

class Team extends Model implements Importable, Exportable {
  private function __construct(
    private int $id,
    private int $active,
    private int $admin,
    private int $protected,
    private int $visible,
    private string $name,
    private string $password_hash,
    private int $points,
    private string $last_score,
    private string $logo,
    private string $created_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getActive(): bool {
    return $this->active === 1;
  }

  public function getAdmin(): bool {
    return $this->admin === 1;
  }

  public function getProtected(): bool {
    return $this->protected === 1;
  }

  public function getVisible(): bool {
    return $this->visible === 1;
  }

  public function getName(): string {
    return mb_convert_encoding($this->name, 'UTF-8');
  }

  public function getPasswordHash(): string {
    return $this->password_hash;
  }

  public function getPoints(): int {
    return $this->points;
  }

  public function getLastScore(): string {
    return $this->last_score;
  }

  public function getLogo(): string {
    return $this->logo;
  }

  public async function getLogoModel(): Awaitable<Logo> {
    $logo = await Logo::genByName($this->logo);
    return $logo;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  protected static function teamFromRow(Map<string, string> $row): Team {
    return new Team(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'active')),
      intval(must_have_idx($row, 'admin')),
      intval(must_have_idx($row, 'protected')),
      intval(must_have_idx($row, 'visible')),
      must_have_idx($row, 'name'),
      must_have_idx($row, 'password_hash'),
      intval(must_have_idx($row, 'points')),
      must_have_idx($row, 'last_score'),
      must_have_idx($row, 'logo'),
      must_have_idx($row, 'created_ts'),
    );
  }

  // Import teams.
  public static async function importAll(
    array<string, array<string, mixed>> $elements,
  ): Awaitable<bool> {
    foreach ($elements as $team) {
      $name = must_have_string($team, 'name');
      $exist = await self::genTeamExist($name); // TODO: Combine Awaits
      if (!$exist) {
        $team_id = await self::genCreateAll(
          (bool) must_have_idx($team, 'active'),
          $name,
          must_have_string($team, 'password_hash'),
          must_have_int($team, 'points'),
          must_have_string($team, 'logo'),
          (bool) must_have_idx($team, 'admin'),
          (bool) must_have_idx($team, 'protected'),
          (bool) must_have_idx($team, 'visible'),
        ); // TODO: Combine Awaits
      }
      await Logo::genSetUsed(must_have_string($team, 'logo'), true); // TODO: Combine Awaits
    }
    return true;
  }

  // Export teams.
  public static async function exportAll(
  ): Awaitable<array<string, array<string, mixed>>> {
    $all_teams_data = array();
    $all_teams = await self::genAllTeams();

    foreach ($all_teams as $team) {
      $team_data = self::genTeamData($team->getId());
      $one_team = array(
        'name' => $team->getName(),
        'active' => $team->getActive(),
        'admin' => $team->getAdmin(),
        'protected' => $team->getProtected(),
        'visible' => $team->getVisible(),
        'password_hash' => $team->getPasswordHash(),
        'points' => $team->getPoints(),
        'logo' => $team->getLogo(),
        'data' => $team_data,
      );
      array_push($all_teams_data, $one_team);
    }
    return array('teams' => $all_teams_data);
  }

  // Retrieve how many teams are using one logo.
  public static async function genWhoUses(
    string $logo,
  ): Awaitable<array<Team>> {
    $db = await self::genDb();
    $result = await $db->queryf('SELECT * FROM teams WHERE logo = %s', $logo);

    $teams = array();
    foreach ($result->mapRows() as $row) {
      $teams[] = self::teamFromRow($row);
    }
    return $teams;
  }

  // Generate salted hash.
  public static function generateHash(string $password): string {
    $options = array('cost' => 12);
    return strval(password_hash($password, PASSWORD_DEFAULT, $options));
  }

  // Checks if hash need refreshing.
  public static function regenerateHash(string $password_hash): bool {
    $options = array('cost' => 12);
    return (bool) password_needs_rehash(
      $password_hash,
      PASSWORD_DEFAULT,
      $options,
    );
  }

  // Verify if login is valid.
  public static async function genVerifyCredentials(
    int $team_id,
    string $password,
  ): Awaitable<?Team> {
    $db = await self::genDb();
    $result =
      await $db->queryf(
        'SELECT * FROM teams WHERE id = %d AND (active = 1 OR admin = 1) LIMIT 1',
        $team_id,
      );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      $team = self::teamFromRow($result->mapRows()[0]);

      // Check if ldap is enabled and verify credentials if successful
      // An exception is admin user, which is verified locally
      $ldap = await Configuration::gen('ldap');
      if ($ldap->getValue() === '1' && !$team->getAdmin()) {
        // Get server information from configuration
        list($ldap_server, $ldap_port, $ldap_domain_suffix) =
          await \HH\Asio\va(
            Configuration::gen('ldap_server'),
            Configuration::gen('ldap_port'),
            Configuration::gen('ldap_domain_suffix'),
          );
        $ldapconn = ldap_connect(
          $ldap_server->getValue(),
          intval($ldap_port->getValue()),
        );
        if (!$ldapconn)
          return null;
        $team_name = trim($team->getName());
        $bind = ldap_bind(
          $ldapconn,
          $team_name.$ldap_domain_suffix->getValue(),
          $password,
        );
        if (!$bind)
          return null;
        //Successful Login via LDAP
        return $team;
      }

      if (password_verify($password, $team->getPasswordHash())) {
        if (self::regenerateHash($team->getPasswordHash())) {
          $new_hash = self::generateHash($password);
          await self::genUpdateTeamPassword($new_hash, $team->getId());
        }
        return $team;
      } else {
        return null;
      }
    } else {
      return null;
    }
  }

  // Check to see if the team is active.
  public static async function genCheckTeamStatus(
    int $team_id,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT COUNT(*) FROM teams WHERE id = %d AND active = 1 LIMIT 1',
      $team_id,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Create a team and return the created team id.
  public static async function genCreate(
    string $name,
    string $password_hash,
    string $logo,
  ): Awaitable<int> {
    $db = await self::genDb();

    // Create team
    await \HH\Asio\va(
      $db->queryf(
        'INSERT INTO teams (name, password_hash, logo, created_ts) VALUES (%s, %s, %s, NOW())',
        $name,
        $password_hash,
        $logo,
      ),
      Logo::genSetUsed($logo, true),
    );

    // Return newly created team_id
    $result =
      await $db->queryf(
        'SELECT id FROM teams WHERE name = %s AND password_hash = %s AND logo = %s LIMIT 1',
        $name,
        $password_hash,
        $logo,
      );

    Logo::invalidateMCRecords();
    // Delay rebuilding all cache for the new team, as they won't have any scoring data yet anyway.
    MultiTeam::invalidateMCRecords('ALL_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
    MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval($result->mapRows()[0]['id']);
  }

  // Create a team (all the fields) and return the created team id.
  public static async function genCreateAll(
    bool $active,
    string $name,
    string $password_hash,
    int $points,
    string $logo,
    bool $admin,
    bool $protected,
    bool $visible,
  ): Awaitable<int> {
    $db = await self::genDb();

    // Create team
    await \HH\Asio\va(
      $db->queryf(
        'INSERT INTO teams (name, password_hash, points, logo, active, admin, protected, visible, created_ts) VALUES (%s, %s, %d, %s, %d, %d, %d, %d, NOW())',
        $name,
        $password_hash,
        $points,
        $logo,
        $active ? 1 : 0,
        $admin ? 1 : 0,
        $protected ? 1 : 0,
        $visible ? 1 : 0,
      ),
      Logo::genSetUsed($logo, true),
    );

    // Return newly created team_id
    $result =
      await $db->queryf(
        'SELECT id FROM teams WHERE name = %s AND password_hash = %s AND logo = %s LIMIT 1',
        $name,
        $password_hash,
        $logo,
      );

    Logo::invalidateMCRecords();
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval($result->mapRows()[0]['id']);
  }

  // Add data to a team.
  public static async function genAddTeamData(
    string $name,
    string $email,
    int $team_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'INSERT INTO teams_data (name, email, team_id, created_ts) VALUES (%s, %s, %d, NOW())',
      $name,
      $email,
      $team_id,
    );
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
    MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');
  }

  // Get a team data.
  public static async function genTeamData(
    int $team_id,
  ): Awaitable<Vector<Map<string, string>>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM teams_data WHERE team_id = %d',
      $team_id,
    );
    return $result->mapRows();
  }

  // Update team.
  public static async function genUpdate(
    string $name,
    string $logo,
    int $points,
    int $team_id,
  ): Awaitable<void> {
    $db = await self::genDb();

    // Get and set old logo to unused
    $result =
      await $db->queryf('SELECT logo FROM teams WHERE id = %d', $team_id);
    invariant($result->numRows() === 1, 'Expected exactly one result');
    $logo_old = strval($result->mapRows()[0]['logo']);

    await \HH\Asio\va(
      $db->queryf(
        'UPDATE teams SET name = %s, logo = %s , points = %d WHERE id = %d LIMIT 1',
        $name,
        $logo,
        $points,
        $team_id,
      ),
      Logo::genSetUsed($logo_old, false),
      Logo::genSetUsed($logo, true),
    );
    Logo::invalidateMCRecords();
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
  }

  // Update team password.
  public static async function genUpdateTeamPassword(
    string $password_hash,
    int $team_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET password_hash = %s WHERE id = %d LIMIT 1',
      $password_hash,
      $team_id,
    );
    MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
    MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
    MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
    MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');
    await Session::genDeleteByTeam($team_id);
  }

  // Delete team.
  public static async function genDelete(int $team_id): Awaitable<void> {
    $db = await self::genDb();
    $result =
      await $db->queryf('SELECT logo FROM teams WHERE id = %d', $team_id);
    invariant($result->numRows() === 1, 'Expected exactly one result');
    $logo = strval($result->mapRows()[0]['logo']);

    await Logo::genSetUsed($logo, false);

    $queries = Vector {
      sprintf(
        'DELETE FROM teams WHERE id = %d AND protected = 0 LIMIT 1',
        $team_id,
      ),
      sprintf(
        'DELETE FROM registration_tokens WHERE team_id = %d',
        $team_id,
      ),
      sprintf('DELETE FROM teams_oauth WHERE team_id = %d', $team_id),
      sprintf('DELETE FROM scores_log WHERE team_id = %d', $team_id),
      sprintf('DELETE FROM hints_log WHERE team_id = %d', $team_id),
      sprintf('DELETE FROM failures_log WHERE team_id = %d', $team_id),
      sprintf(
        'DELETE FROM activity_log WHERE subject = "Team:%d"',
        $team_id,
      ),
    };
    await $db->multiQuery($queries);
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
    ScoreLog::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    HintLog::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
    await Session::genDeleteByTeam($team_id);
  }

  public static async function genSetTeamName(
    int $team_id,
    string $team_name,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $team_name = trim($team_name);

    if ($team_name === '') {
      return false;
    }

    $shortname = substr($team_name, 0, 20);

    $team_exists = await Team::genTeamExist($shortname);
    if ($team_exists === true) {
      return false;
    } else {
      $team = await self::genTeam($team_id);
      await self::genUpdate(
        $shortname,
        $team->getLogo(),
        $team->getPoints(),
        $team_id,
      );
      MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
      MultiTeam::invalidateMCRecords('ALL_ACTIVE_TEAMS');
      MultiTeam::invalidateMCRecords('ALL_VISIBLE_TEAMS');
      MultiTeam::invalidateMCRecords('TEAMS_BY_LOGO');
      ScoreLog::invalidateMCRecords(); // Invalidate Memcached ScoreLog data.
      ActivityLog::invalidateMCRecords(); // Invalidate Memcached ActivityLog data.
      return true;
    }
  }

  // Enable or disable teams by passing 1 or 0.
  public static async function genSetStatus(
    int $team_id,
    bool $status,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET active = %d WHERE id = %d LIMIT 1',
      $status ? 1 : 0,
      $team_id,
    );
    if ($status === false) {
      await Session::genDeleteByTeam($team_id);
    }
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Enable or disable all teams by passing 1 or 0.
  public static async function genSetStatusAll(bool $status): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET active = %d WHERE id > 0 AND protected = 0',
      $status ? 1 : 0,
    );
    if ($status === false) {
      await Session::genDeleteAllUnprotected();
    }
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Sets toggles team protection status.
  public static async function genSetProtected(
    int $team_id,
    bool $protect,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET protected = %d WHERE id = %d LIMIT 1',
      $protect ? 1 : 0,
      $team_id,
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Sets toggles team admin status.
  public static async function genSetAdmin(
    int $team_id,
    bool $admin,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET admin = %d WHERE id = %d AND protected = 0 LIMIT 1',
      $admin ? 1 : 0,
      $team_id,
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    await Session::genDeleteByTeam($team_id); // Delete all sessions for team in question
  }

  // Enable or disable team visibility by passing 1 or 0.
  public static async function genSetVisible(
    int $team_id,
    bool $visible,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET visible = %d WHERE id = %d LIMIT 1',
      $visible ? 1 : 0,
      $team_id,
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
  }

  // Check if a team name is already created.
  public static async function genTeamExist(
    string $team_name,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM teams WHERE name = %s',
      $team_name,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // Check if a team name is already created.
  public static async function genTeamExistById(
    int $team_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result =
      await $db->queryf('SELECT COUNT(*) FROM teams WHERE id = %d', $team_id);

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval(idx($result->mapRows()[0], 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }

  // All active teams.
  public static async function genAllActiveTeams(): Awaitable<array<Team>> {
    $db = await self::genDb();

    $result =
      await $db->queryf('SELECT * FROM teams WHERE active = 1 ORDER BY id');

    $teams = array();
    foreach ($result->mapRows() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // All visible teams.
  public static async function genAllVisibleTeams(): Awaitable<array<Team>> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM teams WHERE visible = 1 AND active = 1 ORDER BY id',
    );

    $teams = array();
    foreach ($result->mapRows() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // Leaderboard order.
  public static async function genLeaderboard(): Awaitable<array<Team>> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC',
      );

    $teams = array();
    foreach ($result->mapRows() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // All teams.
  public static async function genAllTeams(): Awaitable<array<Team>> {
    $db = await self::genDb();

    $result = await $db->queryf('SELECT * FROM teams ORDER BY points DESC');

    $teams = array();
    foreach ($result->mapRows() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // Get a single team.
  public static async function genTeam(int $team_id): Awaitable<Team> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM teams WHERE id = %d LIMIT 1',
      $team_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    $team = self::teamFromRow($result->mapRows()[0]);

    return $team;
  }

  // Get a single team, by name.
  public static async function genTeamByName(
    string $team_name,
  ): Awaitable<Team> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM teams WHERE name = %s LIMIT 1',
      $team_name,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    $team = self::teamFromRow($result->mapRows()[0]);

    return $team;
  }

  // Get points by type.
  public static async function genPointsByType(
    int $team_id,
    string $type,
  ): Awaitable<int> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT IFNULL(SUM(points), 0) AS points FROM scores_log WHERE type = %s AND team_id = %d',
        $type,
        $team_id,
      );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(idx($result->mapRows()[0], 'points'));
  }

  // Get healthy status for points.
  public static async function genPointsHealth(int $team_id): Awaitable<bool> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT IFNULL(t.points, 0) AS points, IFNULL(SUM(s.points), 0) AS sum FROM teams AS t, scores_log AS s WHERE t.id = %d AND s.team_id = %d',
        $team_id,
        $team_id,
      );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    $value = $result->mapRows()[0];

    return (intval($value['points']) === intval($value['sum']));
  }

  // Update the last_score field.
  public static async function genLastScore(int $team_id): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET last_score = NOW() WHERE id = %d LIMIT 1',
      $team_id,
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
  }

  // Set all points to zero for all teams.
  public static async function genResetAllPoints(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('UPDATE teams SET points = 0 WHERE id > 0');
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    ActivityLog::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached ActivityLog data.
  }

  // Teams total number.
  public static async function genTeamsCount(): Awaitable<int> {
    $db = await self::genDb();

    $result = await $db->queryf('SELECT COUNT(*) AS count FROM teams');

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval(idx($result->mapRows()[0], 'COUNT(*)'));
  }

  public static async function genFirstCapture(
    int $level_id,
  ): Awaitable<Team> {
    $db = await self::genDb();
    $result =
      await $db->queryf(
        'SELECT * FROM teams WHERE id = (SELECT team_id FROM scores_log WHERE level_id = %d AND team_id IN (SELECT id FROM teams WHERE visible = 1 AND active = 1) ORDER BY ts LIMIT 0,1)',
        $level_id,
      );
    return self::teamFromRow($result->mapRows()[0]);
  }

  public static async function genCompletedLevel(
    int $level_id,
  ): Awaitable<array<Team>> {
    $db = await self::genDb();

    $result =
      await $db->queryf(
        'SELECT * FROM teams WHERE id IN (SELECT team_id FROM scores_log WHERE level_id = %d ORDER BY ts) AND visible = 1 AND active = 1',
        $level_id,
      );

    $teams = array();
    foreach ($result->mapRows() as $row) {
      $teams[] = self::teamFromRow($row);
    }

    return $teams;
  }

  // Get rank position for a team
  public static async function genMyRank(int $team_id): Awaitable<int> {
    $rank = 1;
    $leaderboard = await MultiTeam::genLeaderboard();
    foreach ($leaderboard as $team) {
      if ($team_id === $team->getId()) {
        return $rank;
      }
      $rank++;
    }

    return $rank;
  }

  public static async function genTeamUpdatePoints(
    int $team_id,
    int $points,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET last_score = last_score, points = %d WHERE id = %d',
      $points,
      $team_id,
    );
    MultiTeam::invalidateMCRecords(); // Invalidate Memcached MultiTeam data.
    Control::invalidateMCRecords('ALL_ACTIVITY'); // Invalidate Memcached Control data.
  }

  public static async function genAuthTokenExists(
    string $type,
    string $token,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $team_id_result = await $db->queryf(
      'SELECT team_id FROM teams_oauth WHERE type = %s AND token = %s',
      $type,
      $token,
    );

    if ($team_id_result->numRows() === 1) {
      return true;
    } else {
      return false;
    }
  }

  public static async function genTeamOAuthTokenExists(
    string $type,
    int $team_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $team_id_result = await $db->queryf(
      'SELECT id FROM teams_oauth WHERE type = %s AND team_id = %d',
      $type,
      $team_id,
    );

    if ($team_id_result->numRows() === 1) {
      return true;
    } else {
      return false;
    }
  }

  public static async function genTeamFromOAuthToken(
    string $type,
    string $token,
  ): Awaitable<Team> {
    $db = await self::genDb();

    $team_id_result = await $db->queryf(
      'SELECT team_id FROM teams_oauth WHERE type = %s AND token = %s',
      $type,
      $token,
    );

    $team_id =
      intval(must_have_idx($team_id_result->mapRows()[0], 'team_id'));
    $team = await self::genTeam($team_id);
    return $team;
  }

  public static async function genSetOAuthToken(
    int $team_id,
    string $type,
    string $token,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $queries = Vector {
      sprintf(
        'SELECT id FROM teams_oauth WHERE type = "%s" AND token = "%s"',
        $db->escapeString($type),
        $db->escapeString($token),
      ),
      sprintf(
        'SELECT id FROM teams_oauth WHERE team_id = %d AND type = "%s"',
        $team_id,
        $db->escapeString($type),
      ),
    };
    list($oauth_exists_result, $current_id_result) =
      await $db->multiQuery($queries);

    if ($oauth_exists_result->numRows() > 0) {
      return false;
    }

    if ($current_id_result->numRows() === 1) {
      $result = await $db->queryf(
        'UPDATE teams_oauth SET token = %s WHERE id = %d',
        $token,
        intval(must_have_idx($current_id_result->mapRows()[0], 'id')),
      );
      if ($result) {
        return true;
      }
    } else {
      $result = await $db->queryf(
        'INSERT INTO teams_oauth (type, team_id, token) VALUES (%s, %d, %s)',
        $type,
        $team_id,
        $token,
      );
      if ($result) {
        return true;
      }
    }
    return false;

  }

  public static async function genGetLiveSyncKey(
    int $team_id,
    string $type,
  ): Awaitable<string> {
    $db = await self::genDb();
    if ($type === 'general') {
      $team = await self::genTeam($team_id);
      $username = $team->getName();
      $key = '';
    } else {
      $result = await $db->queryf(
        'SELECT * FROM livesync WHERE team_id = %d AND type = %s',
        $team_id,
        $type,
      );
      invariant($result->numRows() === 1, 'Expected exactly one result');

      $username = strval(must_have_idx($result->mapRows()[0], 'username'));
      $key_from_db = strval(must_have_idx($result->mapRows()[0], 'sync_key'));

      switch ($type) {
        case 'fbctf':
          $key = self::generateHash($key_from_db);
          break;
        case 'facebook_oauth':
          $key = $key_from_db;
          $username = '';
          break;
        case 'google_oauth':
          $key = $key_from_db;
          $username = '';
          break;
          // FALLTHROUGH
        default:
          $key = $key_from_db;
          break;
      }
    }

    return strval($type.":".$username.":".$key);
  }

  public static async function genLiveSyncExists(
    int $team_id,
    string $type,
  ): Awaitable<bool> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT id FROM livesync WHERE team_id = %d AND type = %s',
      $team_id,
      $type,
    );
    if ($result->numRows() === 1) {
      return true;
    }
    return false;
  }

  public static async function genSetLiveSyncPassword(
    int $team_id,
    string $type,
    string $username,
    string $password,
  ): Awaitable<bool> {
    $db = await self::genDb();

    if (($username === '') || ($password === '')) {
      return false;
    }

    switch ($type) {
      case 'fbctf':
        $key = hash("sha256", $password);
        $team = await self::genTeam($team_id);
        if (password_verify($password, $team->getPasswordHash())) {
          return false;
        }
        break;
        // FALLTHROUGH
      default:
        $key = $password;
        break;
    }

    $username_result =
      await $db->queryf(
        'SELECT id FROM livesync WHERE username = %s AND type = %s AND team_id != %d',
        $username,
        $type,
        $team_id,
      );
    if ($username_result->numRows() > 0) {
      return false;
    }

    $current_id_result = await $db->queryf(
      'SELECT id FROM livesync WHERE team_id = %d AND type = %s',
      $team_id,
      $type,
    );
    if ($current_id_result->numRows() === 1) {
      $result = await $db->queryf(
        'UPDATE livesync SET username = %s, sync_key = %s WHERE id = %d',
        $username,
        $key,
        intval(must_have_idx($current_id_result->mapRows()[0], 'id')),
      );
      if ($result) {
        return true;
      }
    } else {
      $result =
        await $db->queryf(
          'INSERT INTO livesync (type, team_id, username, sync_key) VALUES (%s, %d, %s, %s)',
          $type,
          $team_id,
          $username,
          $key,
        );
      if ($result) {
        return true;
      }
    }
    return false;
  }

  public static async function genLiveSyncKeyExists(
    string $key,
  ): Awaitable<bool> {
    $db = await self::genDb();

    if (strpos($key, ':') === false) {
      return false;
    }
    list($type, $username, $key) = explode(':', $key);

    switch ($type) {
      case 'fbctf':
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE username = %s AND type = %s',
          $username,
          $type,
        );
        break;
      case 'facebook_oauth':
        return await Integration::genFacebookThirdPartyExists($key);
        break;
      case 'google_oauth':
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE sync_key = %s AND type = %s',
          $key,
          $type,
        );
        break;
        // FALLTHROUGH
      default:
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE sync_key = %s',
          $key,
        );
        break;
    }

    if ($result->numRows() > 0) {
      $team_id = 0;
      foreach ($result->mapRows() as $row) {
        $type = strval(must_have_idx($row, 'type'));
        $username = strval(must_have_idx($row, 'username'));
        $key_from_db = strval(must_have_idx($row, 'sync_key'));

        switch ($type) {
          case 'fbctf':
            if (password_verify($key_from_db, $key)) {
              return true;
            }
            break;
            // FALLTHROUGH
          default:
            if (strval($key) === strval($key_from_db)) {
              return true;
            }
            break;
        }
      }
    }
    return false;
  }

  public static async function genTeamFromLiveSyncKey(
    string $key,
  ): Awaitable<Team> {
    $db = await self::genDb();
    $email = '';

    invariant(strpos($key, ':'), "Invalid live sync key");
    list($type, $username, $key) = explode(':', $key);

    switch ($type) {
      case 'fbctf':
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE username = %s AND type = %s',
          $username,
          $type,
        );
        invariant($result->numRows() > 0, 'Expected at least one result');
        break;
      case 'facebook_oauth':
        $email = await Integration::genFacebookThirdPartyEmail($key);
        invariant(
          $email !== '',
          'Expected an email from genFacebookThirdPartyEmail, non returned.',
        );
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE username = %s AND type = %s',
          $email,
          $type,
        );
        break;
      case 'google_oauth':
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE sync_key = %s AND type = %s',
          $key,
          $type,
        );
        break;
        // FALLTHROUGH
      default:
        $result = await $db->queryf(
          'SELECT * FROM livesync WHERE sync_key = %s',
          $key,
        );
        invariant($result->numRows() > 0, 'Expected at least one result');
        break;
    }

    $team_id = 0;
    foreach ($result->mapRows() as $row) {
      $type = strval(must_have_idx($row, 'type'));
      $username = strval(must_have_idx($row, 'username'));
      $key_from_db = strval(must_have_idx($row, 'sync_key'));

      switch ($type) {
        case 'fbctf':
          if (password_verify($key_from_db, $key)) {
            $team_id = intval(must_have_idx($row, 'team_id'));
            $team = await self::genTeam($team_id);
            return $team;
          }
          break;
        case 'facebook_oauth':
          if (strval($email) === strval($username)) {
            $team_id = intval(must_have_idx($row, 'team_id'));
            $team = await self::genTeam($team_id);
            return $team;
          }
          break;
          // FALLTHROUGH
        default:
          if (strval($key) === strval($key_from_db)) {
            $team_id = intval(must_have_idx($row, 'team_id'));
            $team = await self::genTeam($team_id);
            return $team;
          }
          break;
      }
    }
    invariant($team_id !== 0, 'team_id not found');
    $team = await self::genTeam($team_id);
    return $team;
  }

}
