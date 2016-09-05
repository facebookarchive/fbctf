<?hh // strict

abstract class ExporterController {
  abstract public function genData(string $type): Awaitable<mixed>;

  public async function genTeams(): Awaitable<mixed> {
    $all_teams_data = array();
    $all_teams = \HH\Asio\join(Team::genAllTeams());

    foreach ($all_teams as $team) {
      $team_data = \HH\Asio\join(Team::genTeamData($team->getId()));
      $one_team = array(
        'name' => $team->getName(),
        'active' => $team->getActive(),
        'admin' => $team->getAdmin(),
        'protected' => $team->getProtected(),
        'visible' => $team->getVisible(),
        'password_hash' => $team->getPasswordHash(),
        'points' => $team->getPoints(),
        'logo' => $team->getLogo(),
        'data' => $team_data
      );
      array_push($all_teams_data, $one_team);
    }
    return $all_teams_data;
  }

  public async function genLevels(): Awaitable<array<mixed>> {
    $all_levels_data = array();
    $all_levels = \HH\Asio\join(Level::genAllLevels());

    foreach ($all_levels as $level) {
      $entity = \HH\Asio\join(Country::gen($level->getEntityId()));
      $category = \HH\Asio\join(Category::genSingleCategory($level->getCategoryId()));
      $one_level = array(
        'type' => $level->getType(),
        'title' => $level->getTitle(),
        'active' => $level->getActive(),
        'description' => $level->getDescription(),
        'entity_name' => $entity->getName(),
        'category' => $category->getCategory(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonus(),
        'bonus_dec' => $level->getBonusDec(),
        'bonus_fix' => $level->getBonusFix(),
        'flag' => $level->getFlag(),
        'hint' => $level->getHint(),
        'penalty' => $level->getPenalty()
      );
      array_push($all_levels_data, $one_level);
    }
    return $all_levels_data;
  }

  public async function genCategories(): Awaitable<array<mixed>> {
    $all_categories_data = array();
    $all_categories = \HH\Asio\join(Category::genAllCategories());

    foreach ($all_categories as $category) {
      $one_category = array(
        'category' => $category->getCategory(),
        'protected' => $category->getProtected()
      );
      array_push($all_categories_data, $one_category);
    }
    return $all_categories_data;
  }

  public async function genLogos(): Awaitable<array<mixed>> {
    $all_logos_data = array();
    $all_logos = \HH\Asio\join(Logo::genAllLogos());

    foreach ($all_logos as $logo) {
      $one_logo = array(
        'name' => $logo->getName(),
        'logo' => $logo->getLogo(),
        'used' => $logo->getUsed(),
        'enabled' => $logo->getEnabled(),
        'protected' => $logo->getProtected()
      );
      array_push($all_logos_data, $one_logo);
    }
    return $all_logos_data;
  }

  public async function genAll(): Awaitable<array<mixed>> {
    $categories = $this->genCategories();
    $levels = $this->genLevels();
    $teams = $this->genTeams();
    $logos = $this->genLogos();
    return array(
      'categories' => $categories,
      'levels' => $levels,
      'teams' => $teams,
      'logos' => $logos
    );
  }
}