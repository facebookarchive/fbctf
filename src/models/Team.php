<?hh // strict

class Team extends Model {
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
    return $this->name;
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

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  private static function teamFromRow(Map<string, string> $row): Team {
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
    await $db->queryf(
      'INSERT INTO teams (name, password_hash, logo, created_ts) VALUES (%s, %s, %s, NOW())',
      $name,
      $password_hash,
      $logo,
    );

    // Return newly created team_id
    $result =
      await $db->queryf(
        'SELECT id FROM teams WHERE name = %s AND password_hash = %s AND logo = %s LIMIT 1',
        $name,
        $password_hash,
        $logo,
      );

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
    await $db->queryf(
      'INSERT INTO teams (name, password_hash, points, logo, active, admin, protected, visible, created_ts) VALUES (%s, %s, %d, %s, %d, %d, %d, %d, NOW())',
      $name,
      $password_hash,
      $points,
      $logo,
      $active ? 1 : 0,
      $admin ? 1 : 0,
      $protected ? 1 : 0,
      $visible ? 1 : 0,
    );

    // Return newly created team_id
    $result = await $db->queryf(
      'SELECT id FROM teams WHERE name = %s AND password_hash = %s AND logo = %s LIMIT 1',
      $name,
      $password_hash,
      $logo,
    );

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
    await $db->queryf(
      'UPDATE teams SET name = %s, logo = %s , points = %d WHERE id = %d LIMIT 1',
      $name,
      $logo,
      $points,
      $team_id,
    );
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
  }

  // Delete team.
  public static async function genDelete(int $team_id): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'DELETE FROM teams WHERE id = %d AND protected = 0 LIMIT 1',
      $team_id,
    );
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
  }

  // Enable or disable all teams by passing 1 or 0.
  public static async function genSetStatusAll(bool $status): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE teams SET active = %d WHERE id > 0 AND protected = 0',
      $status ? 1 : 0,
    );
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
  }

  // Set all points to zero for all teams.
  public static async function genResetAllPoints(): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf('UPDATE teams SET points = 0 WHERE id > 0');
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
        'SELECT * FROM teams WHERE id = (SELECT team_id FROM scores_log WHERE level_id = %d ORDER BY ts LIMIT 0,1) AND visible = 1 AND active = 1',
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
    $leaderboard = await self::genLeaderboard();
    foreach ($leaderboard as $team) {
      if ($team_id === $team->getId()) {
        return $rank;
      }
      $rank++;
    }

    return $rank;
  }
}
