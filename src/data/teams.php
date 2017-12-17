<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class TeamDataController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $rank = 1;
    list($leaderboard, $gameboard, $leaderboard_limit) = await \HH\Asio\va(
      MultiTeam::genLeaderboard(),
      Configuration::gen('gameboard'),
      Configuration::gen('leaderboard_limit'),
    );

    $teams_data = (object) array();

    // If refresing is disabled, exit
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($teams_data);
      exit(1);
    }

    $leaderboard_size = count($leaderboard);
    $leaderboard_limit_value = intval($leaderboard_limit->getValue());
    if (($leaderboard_size <= $leaderboard_limit_value) ||
        ($leaderboard_limit_value === 0)) {
      $leaderboard_count = $leaderboard_size;
    } else {
      $leaderboard_count = $leaderboard_limit_value;
    }
    for ($i = 0; $i < $leaderboard_count; $i++) {
      $team = $leaderboard[$i];
      list($base, $quiz, $flag) = await \HH\Asio\va(
        MultiTeam::genPointsByType($team->getId(), 'base'),
        MultiTeam::genPointsByType($team->getId(), 'quiz'),
        MultiTeam::genPointsByType($team->getId(), 'flag'),
      );

      $logo_model = await $team->getLogoModel(); // TODO: Combine Awaits

      $team_data = (object) array(
        'logo' => array(
          'path' => $logo_model->getLogo(),
          'name' => $logo_model->getName(),
          'custom' => $logo_model->getCustom(),
        ),
        'team_members' => array(),
        'rank' => $rank,
        'points' => array(
          'base' => $base,
          'quiz' => $quiz,
          'flag' => $flag,
          'total' => $team->getPoints(),
        ),
      );
      if ($team->getName()) {
        /* HH_FIXME[1002] */
        /* HH_FIXME[2011] */
        $teams_data->{$team->getName()} = $team_data;
      }
      $rank++;
    }

    $this->jsonSend($teams_data);
  }
}

$teamsData = new TeamDataController();
$teamsData->sendData();
