<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class TeamDataController extends DataController {
  public async function genGenerateData(): Awaitable<void> {
    $rank = 1;
    $leaderboard = await MultiTeam::genLeaderboard();
    $teams_data = (object) array();

    // If refresing is disabled, exit
    $gameboard = await Configuration::gen('gameboard');
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($teams_data);
      exit(1);
    }

    foreach ($leaderboard as $team) {
      $base = await MultiTeam::genPointsByType($team->getId(), 'base');
      $quiz = await MultiTeam::genPointsByType($team->getId(), 'quiz');
      $flag = await MultiTeam::genPointsByType($team->getId(), 'flag');

      $team_data = (object) array(
        'badge' => $team->getLogo(),
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
\HH\Asio\join($teamsData->genGenerateData());
