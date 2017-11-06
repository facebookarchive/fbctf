<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class CapturesController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $data = array();

    $my_team_id = SessionUtils::sessionTeam();

    $captures = await ScoreLog::genAllScoresByTeam($my_team_id);

    foreach ($captures as $capture) {
      $data[] = $capture->getLevelId();
    }

    $this->jsonSend($data);
  }
}

/* HH_IGNORE_ERROR[1002] */
$capturesData = new CapturesController();
$capturesData->sendData();
