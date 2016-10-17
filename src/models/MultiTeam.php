<?hh // strict

class MultiTeam extends Team {

  private static Map<int, Team> $all_teams = Map {};
  private static array<Team> $team_leaderboard = [];
  private static Map<int, Map<string, int>> $team_points_by_type = Map {};
  private static array<Team> $all_active_teams = [];
  private static array<Team> $all_visible_teams = [];
  private static Map<string, array<Team>> $teams_by_logo = Map {};
  private static Map<int, array<Team>> $teams_by_completed_level = Map {};
  private static Map<int, Team> $first_team_captured_by_level = Map {};
  
  private static function setAllTeams(Map<int, Team> $all_teams): void {
  	self::$all_teams = $all_teams;
  }
  
  private static function setLeaderboard(array<Team> $team_leaderboard): void {
    self::$team_leaderboard = $team_leaderboard;
  }

  private static function setPointsByType(Map<int, Map<string, int>> $team_points_by_type): void {
    self::$team_points_by_type = $team_points_by_type;
  }

  private static function setAllActiveTeams(array<Team> $all_active_teams): void {
  	self::$all_active_teams = $all_active_teams;
  }
  
  private static function setAllVisibleTeams(array<Team> $all_visible_teams): void {
    self::$all_visible_teams = $all_visible_teams;
  }
  
  private static function setAllTeamsByLogo(Map<string, array<Team>> $teams_by_logo): void {
  	self::$teams_by_logo = $teams_by_logo;
  }
  
  private static function setTeamsByCompletedLevel(Map<int, array<Team>> $teams_by_completed_level): void {
    self::$teams_by_completed_level = $teams_by_completed_level;
  }
  
  private static function setFirstTeamCapturedByLevel(Map<int, Team> $first_team_captured_by_level): void {
  	self::$first_team_captured_by_level = $first_team_captured_by_level;
  }
  
  private static async function genTeamArrayFromDB(
    string $query,
  ): Awaitable<Vector<Map<string, string>>> {
  	$db = await self::genDb();
  	$result = await $db->query($query);
  	
  	return $result->mapRows();
  }
  
  // All teams.
  public static async function genAllTeamsCache(
    bool $refresh = false,
    ): Awaitable<Map<int, Team>> {
      if ((count(self::$all_teams) === 0) || ($refresh)) {
        $all_teams = Map {};
        $teams = await self::genTeamArrayFromDB('SELECT * FROM teams');
        foreach ($teams->items() as $team) {
          $all_teams->add(Pair {intval($team->get("id")), Team::teamFromRow($team)});
        }
        self::setAllTeams($all_teams);
       }
    return self::$all_teams;
  }
  
  public static async function genTeam(
    int $team_id,
    bool $refresh = false,
  ): Awaitable<Team> {
  	await self::genAllTeamsCache($refresh);
    /* HH_IGNORE_ERROR[4110] */
    return self::$all_teams->get($team_id);
  }
  
  // Leaderboard order.
  public static async function genLeaderboard(
    bool $refresh = false,
  ): Awaitable<array<Team>> {
  	
  	if ((count(self::$team_leaderboard) === 0) || ($refresh)) {
  	  $team_leaderboard = array();
      $teams = await self::genTeamArrayFromDB('SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC');
  	  foreach ($teams->items() as $team) {
        $team_leaderboard[] = Team::teamFromRow($team);
      }
      self::setLeaderboard($team_leaderboard);
  	}
    return self::$team_leaderboard;
  }
  
  // Get points by type.
  public static async function genPointsByType(
    int $team_id,
    string $type,
    bool $refresh = false,
  ): Awaitable<int> {
  	
    if ((count(self::$team_points_by_type) === 0) || ($refresh)) {
      $points_by_type = Map {};
      $teams = await self::genTeamArrayFromDB('SELECT teams.id, scores_log.type, IFNULL(SUM(scores_log.points), 0) AS points FROM teams LEFT JOIN scores_log ON teams.id = scores_log.team_id GROUP BY teams.id, scores_log.type');
      foreach ($teams->items() as $team) {
      	if ($team->get("type") !== null) {
          if ($points_by_type->contains(intval($team->get("id")))) {
            $type_pair = $points_by_type->get(intval($team->get("id")));
            /* HH_IGNORE_ERROR[4064] */
            $type_pair->add(Pair {$team->get("type"), intval($team->get("points"))});
            $points_by_type->set(intval($team->get("id")), $type_pair);
          } else {
            $type_pair = Map {};
            $type_pair->add(Pair {$team->get("type"), intval($team->get("points"))});
            $points_by_type->add(Pair {intval($team->get("id")), $type_pair});
          }
        }
      }
      /* HH_IGNORE_ERROR[4110] */
      self::setPointsByType(new Map($points_by_type));
    }
    /* HH_IGNORE_ERROR[4064] */
    if ((array_key_exists(intval($team_id), self::$team_points_by_type)) && (self::$team_points_by_type->contains($team_id)) && (self::$team_points_by_type->get($team_id)->contains($type))) return intval(self::$team_points_by_type->get($team_id)->get($type));
    else return intval(0);
  }
  
  // All active teams.
  public static async function genAllActiveTeams(
    bool $refresh = false,
  ): Awaitable<array<Team>> {
  	
    if ((count(self::$all_active_teams) === 0) || ($refresh)) {
      $all_active_teams = array();
      $teams = await self::genTeamArrayFromDB('SELECT * FROM teams WHERE active = 1 ORDER BY id');
      foreach ($teams->items() as $team) {
      	$all_active_teams[] = Team::teamFromRow($team);
      }
      self::setAllActiveTeams($all_active_teams);
    }
    return self::$all_active_teams;
  }
  
  // All visible teams.
  public static async function genAllVisibleTeams(
    bool $refresh = false,
  ): Awaitable<array<Team>> {

    if ((count(self::$all_visible_teams) === 0) || ($refresh)) {
      $all_visible_teams = array();
      $teams = await self::genTeamArrayFromDB('SELECT * FROM teams WHERE visible = 1 AND active = 1 ORDER BY id');
      foreach ($teams->items() as $team) {
        $all_visible_teams[] = Team::teamFromRow($team);
      }
      self::setAllVisibleTeams($all_visible_teams);
    }
    return self::$all_visible_teams;
  }
  
  // Retrieve how many teams are using one logo.
  public static async function genWhoUses(
    string $logo,
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    if ((count(self::$teams_by_logo) === 0) || ($refresh)) {
      $db = await self::genDb();
      $all_teams = await self::genAllTeamsCache();    
      
      $teams_by_logo = array();
      foreach ($all_teams as $team) {
        $teams_by_logo[$team->getLogo()][] = $team;
      }
      self::setAllTeamsByLogo(new Map($teams_by_logo));
    }
    /* HH_IGNORE_ERROR[4110] */
    if ((count(self::$teams_by_logo) !== 0) && (array_key_exists($logo, self::$teams_by_logo))) return self::$teams_by_logo->get("$logo");
    else return array();
  }
  
  public static async function genCompletedLevel(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    if ((count(self::$teams_by_completed_level) === 0) || ($refresh)) {
    	$teams_by_completed_level = array();
      $teams = await self::genTeamArrayFromDB('SELECT scores_log.level_id, teams.* FROM teams LEFT JOIN scores_log ON teams.id = scores_log.team_id WHERE teams.visible = 1 AND teams.active = 1 AND level_id IS NOT NULL ORDER BY scores_log.ts');
      foreach ($teams->items() as $team) {
      	$teams_by_completed_level[intval($team->get("level_id"))][] = Team::teamFromRow($team);
      }
      self::setTeamsByCompletedLevel(new Map($teams_by_completed_level));
  	}
  	/* HH_IGNORE_ERROR[4110] */
  	if (self::$teams_by_completed_level->contains($level_id)) return self::$teams_by_completed_level->get($level_id);
  	else return array();
  }
  
  public static async function genFirstCapture(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<Team> {
    if ((count(self::$first_team_captured_by_level) === 0) || ($refresh)) {
    	$first_team_captured_by_level = array();
      $teams = await self::genTeamArrayFromDB('SELECT * FROM teams LEFT JOIN scores_log ON teams.id = scores_log.team_id WHERE scores_log.ts IN (SELECT MIN(scores_log.ts) FROM scores_log GROUP BY scores_log.level_id)');
      foreach ($teams->items() as $team) {
      	$first_team_captured_by_level[intval($team->get("level_id"))] = Team::teamFromRow($team);
      }
      self::setFirstTeamCapturedByLevel(new Map($first_team_captured_by_level));
    }
    /* HH_IGNORE_ERROR[4110] */
    return self::$first_team_captured_by_level->get($level_id);
  }
}