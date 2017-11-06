<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class WorldMapController extends ModuleController {
  public async function genRender(): Awaitable<:xhp> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $worldMap = await $this->genRenderWorldMap();
    return
      <svg
        id="fb-gameboard-map"
        xmlns="http://www.w3.org/2000/svg"
        amcharts="http://amcharts.com/ammap"
        xlink="http://www.w3.org/1999/xlink"
        viewBox="0 0 1008 651"
        preserveAspectRatio="xMidYMid meet">
        <defs>
          <amcharts:ammap
            projection="mercator"
            leftLongitude="-169.6"
            topLatitude="83.68"
            rightLongitude="190.25"
            bottomLatitude="-55.55">
          </amcharts:ammap>
        </defs>
        <g class="view-controller">
          {$worldMap}
          <g class="country-hover"></g>
        </g>
      </svg>;
  }

  public async function genRenderWorldMap(): Awaitable<:xhp> {
    $svg_countries = <g class="countries"></g>;

    $all_levels = await Level::genAllLevels();
    $all_map_countries = await Country::genAllCountriesForMap();

    $levels_map = Map {};
    foreach ($all_levels as $level) {
      $levels_map[$level->getEntityId()] = $level;
    }

    foreach ($all_map_countries as $country) {
      $gameboard = await Configuration::gen('gameboard');
      if ($gameboard->getValue() === '1') {
        $level = $levels_map->get($country->getId());
        $is_active_level = $level !== null && $level->getActive();
        $path_class =
          ($country->getUsed() && $is_active_level) ? 'land active' : 'land';
        $map_indicator = 'map-indicator ';
        $data_captured = null;

        if ($level) {
          $my_previous_score = await ScoreLog::genAllPreviousScore(
            $level->getId(),
            SessionUtils::sessionTeam(),
            false,
          );
          $other_previous_score = await ScoreLog::genPreviousScore(
            $level->getId(),
            SessionUtils::sessionTeam(),
            true,
          );
          if ($my_previous_score) {
            $map_indicator .= 'captured--you';
            $data_captured = SessionUtils::sessionTeamName();
          } else if ($other_previous_score) {
            $map_indicator .= 'captured--opponent';
            $completed_by =
              await MultiTeam::genCompletedLevel($level->getId());
            $data_captured = '';
            foreach ($completed_by as $c) {
              $data_captured .= ' '.$c->getName();
            }
          }
        }
      } else {
        $path_class = 'land';
        $map_indicator = 'map-indicator ';
        $data_captured = null;
      }

      $g =
        <g>
          <path
            id={$country->getIsoCode()}
            title={$country->getName()}
            class={$path_class}
            d={$country->getD()}>
          </path>
          <g transform={$country->getTransform()} class={$map_indicator}>
            <path d="M0,9.1L4.8,0h0.1l4.8,9.1v0L0,9.1L0,9.1z"></path>
          </g>
        </g>;
      if ($data_captured) {
        $g->setAttribute('data-captured', $data_captured);
      }
      $svg_countries->appendChild($g);
    }

    return $svg_countries;
  }
}

/* HH_IGNORE_ERROR[1002] */
$map = new WorldMapController();
$map->sendRender();
