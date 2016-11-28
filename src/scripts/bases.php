<?hh

require_once('/var/www/fbctf/src/Db.php');
require_once('/var/www/fbctf/src/Utils.php');
require_once('/var/www/fbctf/src/models/Model.php');
require_once('/var/www/fbctf/src/models/Importable.php');
require_once('/var/www/fbctf/src/models/Exportable.php');
require_once('/var/www/fbctf/src/models/Level.php');
require_once('/var/www/fbctf/src/models/Configuration.php');

$conf_game = \HH\Asio\join(Configuration::gen('game'));
while ($conf_game->getValue() === '1') {
  // Get all active base levels
  $bases_endpoints = array();
  foreach (\HH\Asio\join(Level::genAllActiveBases()) as $base) {
    $endpoint = array(
      'id' => $base->getId(),
      'url' => \HH\Asio\join(Level::genBaseIP($base->getId())),
    );
    array_push($bases_endpoints, $endpoint);
  }

  // Retrieve current owners
  foreach (Level::getBasesResponses($bases_endpoints) as $response) {
    if ($response['response']) {
      $code = 0;
      $json_r = json_decode($response['response'])[0];
      $teamname = $json_r->team;
      // Give points to the team if exists
      if (\HH\Asio\join(Team::genTeamExist($teamname))) {
        $team = \HH\Asio\join(Team::genTeamByName($teamname));
        \HH\Asio\join(Level::genScoreBase($response['id'], $team->getId()));
        //echo "Points\n";
      }
      //echo "Base(".strval($response['id']).") taken by ".$teamname."\n";
    } else {
      $code = -1;
      //echo "Base(".strval($response['id']).") is DOWN\n";
    }
    \HH\Asio\join(
      Level::genLogBaseEntry(
        $response['id'],
        $code,
        strval($response['response']),
      ),
    );
  }
  // Wait until next iteration
  $bases_cycle = \HH\Asio\join(Configuration::gen('bases_cycle'));
  sleep(intval($bases_cycle->getValue()));
}
