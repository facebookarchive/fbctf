<?hh // strict

abstract class ImporterController {
  abstract public function importData(string $input_filename, string $type): array<string, array<string, mixed>>;

  public async function processTeams(array<string, array<string, mixed>> $teams): Awaitable<bool> {
    foreach ($teams as $team) {
      $name = must_have_string($team, 'name');
      $exist = await Team::genTeamExist($name);
      if (!$exist) {
        $team_id = await Team::genCreateAll(
          (bool)must_have_idx($team, 'active'),
          $name,
          must_have_string($team, 'password_hash'),
          must_have_int($team, 'points'),
          must_have_string($team, 'logo'),
          (bool)must_have_idx($team, 'admin'),
          (bool)must_have_idx($team, 'protected'),
          (bool)must_have_idx($team, 'visible'),
        );
      }
    }
    return true;
  }

  public async function importTeams(string $input_filename): Awaitable<bool> {
    $teams = $this->importData($input_filename, 'teams');
    return await $this->processTeams($teams);
  }

  public async function processLevels(array<string, array<string, mixed>> $levels): Awaitable<bool> {
    foreach ($levels as $level) {
      $title = must_have_string($level, 'title');
      $type = must_have_string($level, 'type');
      $entity_name = must_have_string($level, 'entity_name');
      $c = must_have_string($level, 'category');
      $exist = await Level::genAlreadyExist($type, $title, $entity_name);
      $entity_exist = await Country::genCheckExists($entity_name);
      $category_exist = await Category::genCheckExists($c);
      if (!$exist && $entity_exist && $category_exist) {
        $entity = await Country::genCountry($entity_name);
        $category = await Category::genSingleCategoryByName($c);
        await Level::genCreate(
          $type,
          $title,
          must_have_string($level, 'description'),
          $entity->getId(),
          $category->getId(),
          must_have_int($level, 'points'),
          must_have_int($level, 'bonus'),
          must_have_int($level, 'bonus_dec'),
          must_have_int($level, 'bonus_fix'),
          must_have_string($level, 'flag'),
          must_have_string($level, 'hint'),
          must_have_int($level, 'penalty'),
        );
      }
    }
    return true;
  }

  public async function importLevels(string $input_filename): Awaitable<bool> {
    $levels = $this->importData($input_filename, 'levels');
    return await $this->processLevels($levels);
  }

  public async function processCategories(array<string, array<string, mixed>> $categories): Awaitable<bool> {
    foreach ($categories as $category) {
      $c = must_have_string($category, 'category');
      $exist = await Category::genCheckExists($c);
      if (!$exist) {
        await Category::genCreate(
          $c,
          (bool)must_have_idx($category, 'protected')
        );
      }
    }
    return true;
  }

  public async function importCategories(string $input_filename): Awaitable<bool> {
    $categories = $this->importData($input_filename, 'categories');
    return await $this->processCategories($categories);
  }

  public async function processLogos(array<string, array<string, mixed>> $logos): Awaitable<bool> {
    foreach ($logos as $logo) {
      $name = must_have_string($logo, 'name');
      $exist = await Logo::genCheckExists($name);
      if (!$exist) {
        await Logo::genCreate(
          (bool)must_have_idx($logo, 'used'),
          (bool)must_have_idx($logo, 'enabled'),
          (bool)must_have_idx($logo, 'protected'),
          $name,
          must_have_string($logo, 'logo'),
        );
      }
    }
    return true;
  }

  public async function importLogos(string $input_filename): Awaitable<bool> {
    $logos = $this->importData($input_filename, 'logos');
    return await $this->processLogos($logos);
  }

  public async function importGame(string $input_filename): Awaitable<bool> {
    $all_data = $this->importData($input_filename, 'game');

    $logos = must_have_idx($all_data, 'logos');
    $logos_result = await $this->processLogos($logos);
    if (!$logos_result) {
      return false;
    }
    $teams = must_have_idx($all_data, 'teams');
    $teams_result = await $this->processTeams($teams);
    if (!$teams_result) {
      return false;
    }
    $categories = must_have_idx($all_data, 'categories');
    $categories_result = await $this->processCategories($categories);
    if (!$categories_result) {
      return false;
    }
    $levels = must_have_idx($all_data, 'levels');
    $levels_result = await $this->processLevels($levels);
    if (!$levels_result) {
      return false;
    }
    
    return true;
  }
}