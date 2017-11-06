<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ConfigurationController extends DataController {

  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $conf_data = (object) array();

    $control = new Control();

    $awaitables = Map {
      'gameboard' => Configuration::gen('gameboard'),
      'gameboard_cycle' => Configuration::gen('gameboard_cycle'),
      'conf_cycle' => Configuration::gen('conf_cycle'),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $gameboard = $awaitables_results['gameboard'];
    // Refresh rate for teams/leaderboard in milliseconds
    // Refresh rate for map/announcements in milliseconds
    $gameboard_cycle = $awaitables_results['gameboard_cycle'];
    // Refresh rate for configuration values in milliseconds
    // Refresh rate for commands in milliseconds
    $conf_cycle = $awaitables_results['conf_cycle'];

    /* HH_FIXME[1002] */
    /* HH_FIXME[2011] */
    $conf_data->{'currentTeam'} = SessionUtils::sessionTeamName();
    $conf_data->{'gameboard'} = $gameboard->getValue();
    $conf_data->{'refreshTeams'} = ($gameboard_cycle->getValue()) * 1000;
    $conf_data->{'refreshMap'} = ($gameboard_cycle->getValue()) * 1000;
    $conf_data->{'refreshConf'} = ($conf_cycle->getValue()) * 1000;
    $conf_data->{'refreshCmd'} = ($conf_cycle->getValue()) * 1000;
    $conf_data->{'progressiveCount'} = await Progressive::genCount();

    $this->jsonSend($conf_data);
  }
}

$confController = new ConfigurationController();
$confController->sendData();
