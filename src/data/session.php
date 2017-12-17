<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class SessionController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $data = array('true');
    $this->jsonSend($data);
  }
}

/* HH_IGNORE_ERROR[1002] */
$sessionControler = new SessionController();
$sessionControler->sendData();
