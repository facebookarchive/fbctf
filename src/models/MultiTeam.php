<?hh // strict

class MultiTeam extends Team {

  protected static string $MC_KEY = 'multiteam:';

  protected static Map<string, string>
    $MC_KEYS = Map {
      'ALL_TEAMS' => 'all_teams',
      'LEADERBOARD' => 'leaderboard_teams',
      'LEADERBOARD_LIMIT' => 'leaderboard_limit',
      'POINTS_BY_TYPE' => 'points_by_type',
      'ALL_ACTIVE_TEAMS' => 'active_teams',
      'ALL_VISIBLE_TEAMS' => 'visible_teams',
      'TEAMS_BY_LOGO' => 'logo_teams',
      'TEAMS_BY_LEVEL' => 'level_teams',
      'TEAMS_NAMES_BY_LEVEL' => 'level_teams_names',
      'TEAMS_FIRST_CAP' => 'capture_teams',
    };

  private static async function genTeamArrayFromDB(
    string $query,
    int $limit = 0,
  ): Awaitable<Vector<Map<string, string>>> {
    $db = await self::genDb();
    if ($limit !== 0) {
      $query .= ' LIMIT '.intval($limit);
    }

    $result = await $db->query($query);

    return $result->mapRows();
  }

  public static async function genAllAdmins(): Awaitable<array<Team>> {
    $admins = await MultiTeam::genTeamArrayFromDB(
      'SELECT * FROM teams WHERE admin = 1',
    );
    $admin_teams = array();
    foreach ($admins->items() as $admin) {
      $admin_teams[] = Team::teamFromRow($admin);
    }
    return $admin_teams;
  }

  // All teams.
  public static async function genAllTeamsCache(
    bool $refresh = false,
  ): Awaitable<Map<int, Team>> {
    $mc_result = self::getMCRecords('ALL_TEAMS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_teams = Map {};
      $teams = await self::genTeamArrayFromDB('SELECT * FROM teams');
      foreach ($teams->items() as $team) {
        $all_teams->add(
          Pair {intval($team->get('id')), Team::teamFromRow($team)},
        );
      }
      self::setMCRecords('ALL_TEAMS', $all_teams);
      return $all_teams;
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should of type Map and not null',
      );
      return $mc_result;
    }
  }

  public static async function genTeam(
    int $team_id,
    bool $refresh = false,
  ): Awaitable<Team> {
    $all_teams = await self::genAllTeamsCache($refresh);
    $team = $all_teams->get($team_id);
    invariant($team instanceof Team, 'team should of type Team and not null');
    return $team;
  }

  public static async function genLeaderboardLimit(): Awaitable<int> {
    $limit = false;
    $limit = self::getMCRecords('LEADERBOARD_LIMIT');
    if (!$limit) {
      $limit = await self::setLeaderboardLimit();
    }
    return intval($limit);
  }

  public static async function setLeaderboardLimit(): Awaitable<int> {
    $leaderboard_limit = await Configuration::gen('leaderboard_limit');
    self::setMCRecords('LEADERBOARD_LIMIT', $leaderboard_limit->getValue());
    return intval($leaderboard_limit->getValue());
  }

  // Leaderboard order.
  public static async function genLeaderboard(
    bool $limit = true,
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    list($leaderboard_limit, $leaderboard_limit_cache, $visible_teams) =
      await \HH\Asio\va(
        Configuration::gen('leaderboard_limit'),
        self::genLeaderboardLimit(),
        self::genAllVisibleTeams(),
      );

    $teams_count = count($visible_teams);
    $mc_result = self::getMCRecords('LEADERBOARD');
    if (!$mc_result ||
        count($mc_result) === 0 ||
        $leaderboard_limit_cache !== intval($leaderboard_limit->getValue()) ||
        ($limit === false && (count($mc_result) !== $teams_count)) ||
        $refresh) {
      if ($limit === true) {
        $teams =
          await self::genTeamArrayFromDB(
            'SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC',
            intval($leaderboard_limit->getValue()),
          );
      } else {
        $teams =
          await self::genTeamArrayFromDB(
            'SELECT * FROM teams WHERE active = 1 AND visible = 1 ORDER BY points DESC, last_score ASC',
          );
      }
      $team_leaderboard = array();
      foreach ($teams->items() as $team) {
        $team_leaderboard[] = Team::teamFromRow($team);
      }
      self::setMCRecords('LEADERBOARD', $team_leaderboard);
      return $team_leaderboard;
    } else {
      invariant(
        is_array($mc_result),
        'cache return should be an array of Team and not null',
      );
      return $mc_result;
    }
  }

  // Get points by type.
  public static async function genPointsByType(
    int $team_id,
    string $type,
    bool $refresh = false,
  ): Awaitable<int> {
    $mc_result = self::getMCRecords('POINTS_BY_TYPE');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $points_by_type = Map {};
      $teams =
        await self::genTeamArrayFromDB(
          'SELECT teams.id, scores_log.type, IFNULL(SUM(scores_log.points), 0) AS points FROM teams LEFT JOIN scores_log ON teams.id = scores_log.team_id GROUP BY teams.id, scores_log.type',
        );
      foreach ($teams->items() as $team) {
        if ($team->get('type') !== null) {
          if ($points_by_type->contains(intval($team->get('id')))) {
            $type_pair = $points_by_type->get(intval($team->get('id')));
            invariant(
              $type_pair instanceof Map,
              'type_pair should of type Map and not null',
            );
            $type_pair->add(
              Pair {$team->get('type'), intval($team->get('points'))},
            );
            $points_by_type->set(intval($team->get('id')), $type_pair);
          } else {
            $type_pair = Map {};
            $type_pair->add(
              Pair {$team->get('type'), intval($team->get('points'))},
            );
            $points_by_type->add(Pair {intval($team->get('id')), $type_pair});
          }
        } else {
          $type_pair = Map {};
          $type_pair->add(Pair {'quiz', 0});
          $type_pair->add(Pair {'flag', 0});
          $type_pair->add(Pair {'base', 0});
          $points_by_type->add(Pair {intval($team->get('id')), $type_pair});
        }
      }
      self::setMCRecords('POINTS_BY_TYPE', new Map($points_by_type));
      if ($points_by_type->contains($team_id)) {
        $team_points_by_type = $points_by_type->get($team_id);
        invariant(
          $team_points_by_type instanceof Map,
          'team_points_by_type should of type Map and not null',
        );
        if ($team_points_by_type->contains($type)) {
          return intval($team_points_by_type->get($type));
        } else {
          return intval(0);
        }
      } else {
        return intval(0);
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should of type Map and not null',
      );
      if ($mc_result->contains($team_id)) {
        $team_points_by_type = $mc_result->get($team_id);
        invariant(
          $team_points_by_type instanceof Map,
          'team_points_by_type should of type Map and not null',
        );
        if ($team_points_by_type->contains($type)) {
          return intval($team_points_by_type->get($type));
        } else {
          return intval(0);
        }
      } else {
        return intval(0);
      }
    }
  }

  // All active teams.
  public static async function genAllActiveTeams(
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    $mc_result = self::getMCRecords('ALL_ACTIVE_TEAMS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_active_teams = array();
      $teams = await self::genTeamArrayFromDB(
        'SELECT * FROM teams WHERE active = 1 ORDER BY id',
      );
      foreach ($teams->items() as $team) {
        $all_active_teams[] = Team::teamFromRow($team);
      }
      self::setMCRecords('ALL_ACTIVE_TEAMS', $all_active_teams);
      return $all_active_teams;
    } else {
      invariant(
        is_array($mc_result),
        'cache return should be an array of Team and not null',
      );
      return $mc_result;
    }
  }

  // All visible teams.
  public static async function genAllVisibleTeams(
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    $mc_result = self::getMCRecords('ALL_VISIBLE_TEAMS');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $all_visible_teams = array();
      $teams = await self::genTeamArrayFromDB(
        'SELECT * FROM teams WHERE visible = 1 AND active = 1 ORDER BY id',
      );
      foreach ($teams->items() as $team) {
        $all_visible_teams[] = Team::teamFromRow($team);
      }
      self::setMCRecords('ALL_VISIBLE_TEAMS', $all_visible_teams);
      return $all_visible_teams;
    } else {
      invariant(
        is_array($mc_result),
        'cache return should be an array of Team and not null',
      );
      return $mc_result;
    }
  }

  // Retrieve how many teams are using one logo.
  public static async function genWhoUses(
    string $logo,
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    $mc_result = self::getMCRecords('TEAMS_BY_LOGO');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = await self::genDb();
      $all_teams = await self::genAllTeamsCache();
      $teams_by_logo = array();
      foreach ($all_teams as $team) {
        $teams_by_logo[$team->getLogo()][] = $team;
      }
      self::setMCRecords('TEAMS_BY_LOGO', new Map($teams_by_logo));
      $teams_by_logo = new Map($teams_by_logo);
      if ((count($teams_by_logo) !== 0) &&
          ($teams_by_logo->contains($logo))) {
        $teams = $teams_by_logo->get($logo);
        invariant(
          is_array($teams),
          'teams should be an array of Team and not null',
        );
        return $teams;
      } else {
        return array();
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should be of type Map',
      );
      if ((count($mc_result) !== 0) && ($mc_result->contains($logo))) {
        $teams = $mc_result->get($logo);
        invariant(
          is_array($teams),
          'cache return should be an array of Team and not null',
        );
        return $teams;
      } else {
        return array();
      }
    }
  }

  public static async function genCompletedLevel(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<array<Team>> {
    $mc_result = self::getMCRecords('TEAMS_BY_LEVEL');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $teams_by_completed_level = array();
      $scores =
        await self::genTeamArrayFromDB(
          'SELECT level_id, team_id FROM scores_log WHERE level_id IS NOT NULL ORDER BY ts',
        );
      $team_scores_awaitables = Map {};
      foreach ($scores->items() as $score) {
        $team_scores_awaitables->add(
          Pair {
            $score->get('level_id'),
            self::genTeam(intval($score->get('team_id'))),
          },
        );
      }
      $team_scores = await \HH\Asio\m($team_scores_awaitables);

      foreach ($team_scores as $level_id_key => $team) {
        if ($team->getActive() === true && $team->getVisible() === true) {
          $teams_by_completed_level[intval($level_id_key)][] = $team;
        }
      }
      self::setMCRecords(
        'TEAMS_BY_LEVEL',
        new Map($teams_by_completed_level),
      );
      $teams_by_completed_level = new Map($teams_by_completed_level);
      if ($teams_by_completed_level->contains($level_id)) {
        $teams = $teams_by_completed_level->get($level_id);
        invariant(
          is_array($teams),
          'teams should be an array of Team and not null',
        );
        return $teams;
      } else {
        return array();
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should of type Map and not null',
      );
      if ($mc_result->contains($level_id)) {
        $teams = $mc_result->get($level_id);
        invariant(
          is_array($teams),
          'cache return should be an array of Team and not null',
        );
        return $teams;
      } else {
        return array();
      }
    }
  }

  public static async function genAllCompletedLevels(
    bool $refresh = false,
  ): Awaitable<Map<int, Team>> {
    $mc_result = self::getMCRecords('TEAMS_BY_LEVEL');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $teams_by_completed_level = array();
      $scores =
        await self::genTeamArrayFromDB(
          'SELECT level_id, team_id FROM scores_log WHERE level_id IS NOT NULL ORDER BY ts',
        );
      $team_scores_awaitables = Map {};
      foreach ($scores->items() as $score) {
        if ($team_scores_awaitables->contains(
              intval($score->get('level_id')),
            )) {
          $teams_vector =
            $team_scores_awaitables->get(intval($score->get('level_id')));
          invariant(
            $teams_vector instanceof Vector,
            'teams_map should of type Vector and not null',
          );
          $teams_vector->add(self::genTeam(intval($score->get('team_id'))));
          $team_scores_awaitables->set(
            intval($score->get('level_id')),
            $teams_vector,
          );
        } else {
          $teams_vector = Vector {};
          $teams_vector->add(self::genTeam(intval($score->get('team_id'))));
          $team_scores_awaitables->add(
            Pair {intval($score->get('level_id')), $teams_vector},
          );
        }
      }

      $team_scores = await \HH\Asio\mmk(
        $team_scores_awaitables,
        async ($level_id_map_key, $teams_map) ==> {
          $teams = await \HH\Asio\v($teams_map);
          return $teams;
        },
      );

      foreach ($team_scores as $level_id_key => $teams_vector) {
        foreach ($teams_vector as $team) {
          if ($team->getActive() === true && $team->getVisible() === true) {
            $teams_by_completed_level[intval($level_id_key)][] = $team;
          }
        }
      }
      self::setMCRecords(
        'TEAMS_BY_LEVEL',
        new Map($teams_by_completed_level),
      );
      $teams_by_completed_level = new Map($teams_by_completed_level);
      invariant(
        $teams_by_completed_level instanceof Map,
        'teams_by_completed_level should be a Map of Team',
      );
      return $teams_by_completed_level;
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should of type Map and not null',
      );
      return $mc_result;
    }
  }

  public static async function genCompletedLevelTeamNames(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<array<string>> {
    $mc_result = self::getMCRecords('TEAMS_NAMES_BY_LEVEL');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $team_names = array();
      $teams = await self::genAllCompletedLevels();
      invariant($teams instanceof Map, 'teams should be a Map of Team');
      foreach ($teams as $level => $completed_arr) {
        invariant(
          is_array($completed_arr),
          'completed_arr should be an array of Team',
        );
        foreach ($completed_arr as $team_obj) {
          invariant(
            $team_obj instanceof Team,
            'team_obj should be of type Team',
          );
          $team_names[$level][] = $team_obj->getName();
        }
      }
      self::setMCRecords('TEAMS_NAMES_BY_LEVEL', new Map($team_names));
      $team_names = new Map($team_names);
      if ($team_names->contains($level_id)) {
        $team_name = $team_names->get($level_id);
        invariant(
          is_array($team_name),
          'team_name should be an array of string and not null',
        );
        return $team_name;
      } else {
        return array();
      }
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should of type string and not null',
      );
      if ($mc_result->contains($level_id)) {
        $team_name = $mc_result->get($level_id);
        invariant(
          is_array($team_name),
          'cache return should be an array of string and not null',
        );
        return $team_name;
      } else {
        return array();
      }
    }
  }

  public static async function genFirstCapture(
    int $level_id,
    bool $refresh = false,
  ): Awaitable<Team> {
    $mc_result = self::getMCRecords('TEAMS_FIRST_CAP');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $first_team_captured_by_level = array();
      $captures =
        await self::genTeamArrayFromDB(
          'SELECT sl.level_id, sl.team_id FROM (SELECT level_id, MIN(ts) ts FROM scores_log LEFT JOIN teams ON team_id = teams.id WHERE teams.visible = 1 AND teams.active = 1 GROUP BY level_id) sl2 JOIN scores_log sl ON sl.level_id = sl2.level_id AND sl.ts = sl2.ts;',
        );
      $team_scores_awaitables = Map {};
      foreach ($captures->items() as $capture) {
        $team_scores_awaitables->add(
          Pair {
            $capture->get('level_id'),
            self::genTeam(intval($capture->get('team_id'))),
          },
        );
      }
      $team_scores = await \HH\Asio\m($team_scores_awaitables);

      foreach ($team_scores as $level_id_key => $team) {
        $first_team_captured_by_level[intval($level_id_key)] = $team;
      }
      self::setMCRecords(
        'TEAMS_FIRST_CAP',
        new Map($first_team_captured_by_level),
      );
      $first_team_captured_by_level = new Map($first_team_captured_by_level);
      $team = $first_team_captured_by_level->get($level_id);
      invariant(
        $team instanceof Team,
        'team should of type Team and not null',
      );
      return $team;
    } else {
      invariant(
        $mc_result instanceof Map,
        'cache return should of type Map and not null',
      );
      $team = $mc_result->get($level_id);
      invariant(
        $team instanceof Team,
        'team return should of type Map and not null',
      );
      return $team;
    }
  }

  public static async function genMyTeamRank(
    int $team_id,
  ): Awaitable<(Team, int)> {
    $team = false;
    $rank = 1;
    $leaderboard = await MultiTeam::genLeaderboard();
    foreach ($leaderboard as $team) {
      if ($team_id === $team->getId()) {
        return tuple($team, $rank);
      }
      $rank++;
    }

    invariant(
      $team instanceof Team,
      'team return should of type Team and not null',
    );

    return tuple($team, $rank);
  }

}
