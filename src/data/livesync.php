<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class LiveSyncDataController extends DataController {

  public async function genGenerateData(): Awaitable<void> {
    $data = array();

    $teams_array = array();
    $all_teams = await Team::genAllTeams();
    foreach ($all_teams as $team) {
      $team_livesync_exists =
        await Team::genLiveSyncExists($team->getId(), "fbctf");
      if ($team_livesync_exists === true) {
        $team_livesync_key =
          await Team::genGetLiveSyncKey($team->getId(), "fbctf");
        $teams_array[$team->getId()] = strval($team_livesync_key);
      }
    }

    $scores_array = array();
    $scored_teams = array();
    $all_scores = await ScoreLog::genAllScores();
    foreach ($all_scores as $score) {
      if (in_array($score->getTeamId(), array_keys($teams_array)) === false) {
        continue;
      }
      $scores_array[$score->getLevelId()][$teams_array[$score->getTeamId()]]['timestamp'] =
        $score->getTs();
      $scores_array[$score->getLevelId()][$teams_array[$score->getTeamId()]]['capture'] =
        true;
      $scores_array[$score->getLevelId()][$teams_array[$score->getTeamId()]]['hint'] =
        false;
      $scored_teams[$score->getLevelId()][] = $score->getTeamId();
    }
    $all_hints = await HintLog::genAllHints();
    foreach ($all_hints as $hint) {
      if ($hint->getPenalty()) {
        if (in_array($hint->getTeamId(), array_keys($teams_array)) ===
            false) {
          continue;
        }
        $scores_array[$hint->getLevelId()][$teams_array[$hint->getTeamId()]]['hint'] =
          true;
        if (in_array(
              $hint->getTeamId(),
              $scored_teams[$hint->getLevelId()],
            ) ===
            false) {
          $scores_array[$hint->getLevelId()][$teams_array[$hint->getTeamId()]]['capture'] =
            false;
          $scores_array[$hint->getLevelId()][$teams_array[$hint->getTeamId()]]['timestamp'] =
            $hint->getTs();
        }
      }
    }

    $levels_array = array();
    $all_levels = await Level::genAllLevels();
    foreach ($all_levels as $level) {
      $entity = await Country::gen($level->getEntityId());
      $category = await Category::genSingleCategory($level->getCategoryId());
      if (array_key_exists($level->getId(), $scores_array)) {
        $score_level_array = $scores_array[$level->getId()];
      } else {
        $score_level_array = array();
      }
      $one_level = array(
        'active' => $level->getActive(),
        'type' => $level->getType(),
        'title' => $level->getTitle(),
        'description' => $level->getDescription(),
        'entity_iso_code' => $entity->getIsoCode(),
        'category' => $category->getCategory(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonusFix(),
        'bonus_dec' => $level->getBonusDec(),
        'penalty' => $level->getPenalty(),
        'teams' => $score_level_array,
      );
      $levels_array[] = $one_level;
    }

    $data = $levels_array;
    $this->jsonSend($data);
  }

}

/* HH_IGNORE_ERROR[1002] */
$syncData = new LiveSyncDataController();
\HH\Asio\join($syncData->genGenerateData());
