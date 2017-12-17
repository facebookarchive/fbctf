<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class CommandsController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    // Object to hold all the data.
    $commands_line_data = (object) array();

    // Preparing the results_library object.
    $results_library = (object) array();
    $results_library_key = "results_library";

    list(
      $all_levels,
      $all_enabled_countries,
      $all_visible_teams,
      $all_categories,
    ) = await \HH\Asio\va(
      Level::genAllLevels(),
      Country::genAllEnabledCountries(),
      MultiTeam::genAllVisibleTeams(),
      Category::genAllCategories(),
    );

    $levels_map = Map {};
    foreach ($all_levels as $level) {
      $levels_map[$level->getEntityId()] = $level;
    }

    // List of active countries.
    $countries_results = array();
    $countries_key = "country_list";
    foreach ($all_enabled_countries as $country) {
      $level = $levels_map->get($country->getId());
      $is_active_level = $level !== null && $level->getActive();
      if ($country->getUsed() && $is_active_level) {
        array_push($countries_results, $country->getName());
      }
    }

    // List of modules
    $modules_results = array(
      "All",
      "Leaderboard",
      "Announcements",
      "Activity",
      "Teams",
      "Filter",
      "Game Clock",
    );
    $modules_key = "modules";

    // List of active teams.
    $teams_results = array();
    $teams_key = "teams";
    foreach ($all_visible_teams as $team) {
      array_push($teams_results, $team->getName());
    }

    // List of level categories.
    $categories_results = array();
    $categories_key = "categories";
    foreach ($all_categories as $category) {
      array_push($categories_results, $category->getCategory());
    }
    array_push($categories_results, "All");

    /* HH_FIXME[1002] */
    /* HH_FIXME[2011] */
    $results_library->{$countries_key} = $countries_results;
    $results_library->{$modules_key} = $modules_results;
    $results_library->{$teams_key} = $teams_results;
    $results_library->{$categories_key} = $categories_results;

    // Preparing the commands object
    $commands = (object) array();
    $commands_key = "commands";

    // Teams information command: teams
    $command_teams = (object) array();
    $command_teams_function = (object) array();
    $command_teams_function->{"name"} = "show-team";
    $command_teams_key = "teams";
    $command_teams->{"results"} = $teams_key;
    $command_teams->{"function"} = $command_teams_function;
    $commands->{$command_teams_key} = $command_teams;

    // Attack country command: atk
    $command_atk = (object) array();
    $command_atk_function = (object) array();
    $command_atk_function->{"name"} = "capture-country";
    $command_atk_key = "atk";
    $command_atk->{"results"} = $countries_key;
    $command_atk->{"function"} = $command_atk_function;
    $commands->{$command_atk_key} = $command_atk;

    // Filter by category command: cat
    $command_cat = (object) array();
    $command_cat_function = (object) array();
    $command_cat_function->{"name"} = "change-radio";
    $command_cat_function->{"param"} = "fb--module--filter--category";
    $command_cat_key = "cat";
    $command_cat->{"results"} = $categories_key;
    $command_cat->{"function"} = $command_cat_function;
    $commands->{$command_cat_key} = $command_cat;

    // Open module command: open
    $command_open = (object) array();
    $command_open_function = (object) array();
    $command_open_function->{"name"} = "open-module";
    $command_open_key = "open";
    $command_open->{"results"} = $modules_key;
    $command_open->{"function"} = $command_open_function;
    $commands->{$command_open_key} = $command_open;

    // Close module command: close
    $command_close = (object) array();
    $command_close_function = (object) array();
    $command_close_function->{"name"} = "close-module";
    $command_close_key = "close";
    $command_close->{"results"} = $modules_key;
    $command_close->{"function"} = $command_close_function;
    $commands->{$command_close_key} = $command_close;

    // Put it all together and print JSON.
    $commands_line_data->{$results_library_key} = $results_library;
    $commands_line_data->{$commands_key} = $commands;

    $this->jsonSend($commands_line_data);
  }
}

$cmd = new CommandsController();
$cmd->sendData();
