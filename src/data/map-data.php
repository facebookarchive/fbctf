<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class MapDataController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $map_data = (object) array();

    $my_team_id = SessionUtils::sessionTeam();
    $my_name = SessionUtils::sessionTeamName();

    list($all_levels, $enabled_countries) = await \HH\Asio\va(
      Level::genAllLevelsCountryMap(),
      Country::genAllEnabledCountriesForMap(),
    );

    foreach ($enabled_countries as $country) {
      $country_level = $all_levels->get($country->getId());
      $is_active_level =
        $country_level !== null && $country_level->getActive();
      $active = ($country->getUsed() && $is_active_level) ? 'active' : '';
      if ($country_level) {
        list($my_previous_score, $other_previous_score) = await \HH\Asio\va(
          ScoreLog::genAllPreviousScore(
            $country_level->getId(),
            $my_team_id,
            false,
          ),
          ScoreLog::genPreviousScore(
            $country_level->getId(),
            $my_team_id,
            true,
          ),
        ); // TODO: Combine Awaits

        // If my team has scored
        if ($my_previous_score) {
          $captured_by = 'you';
          $data_captured = $my_name;
          // If any other team has scored
        } else if ($other_previous_score) {
          $captured_by = 'opponent';
          $completed_by =
            await MultiTeam::genCompletedLevel($country_level->getId()); // TODO: Combine Awaits
          $data_captured = '';
          foreach ($completed_by as $c) {
            $data_captured .= ' '.$c->getName();
          }
        } else {
          $captured_by = 'no';
          $data_captured = 'no';
        }
      } else {
        $captured_by = 'no';
        $data_captured = 'no';
      }
      $country_data = (object) array(
        'status' => $active,
        'captured' => $captured_by,
        'datacaptured' => $data_captured,
      );
      /* HH_FIXME[1002] */
      /* HH_FIXME[2011] */
      $map_data->{$country->getIsoCode()} = $country_data;
    }

    $this->jsonSend($map_data);
  }
}

$map = new MapDataController();
$map->sendData();
