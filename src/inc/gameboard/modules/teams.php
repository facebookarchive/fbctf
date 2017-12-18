<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class TeamModuleController extends ModuleController {
  public async function genRender(): Awaitable<:xhp> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    await tr_start();
    $leaderboard = await MultiTeam::genLeaderboard();
    $rank = 1;

    $list = <ul class="grid-list"></ul>;

    $gameboard = await Configuration::gen('gameboard');
    if ($gameboard->getValue() === '1') {
      $leaderboard_size = count($leaderboard);
      $leaderboard_limit = await Configuration::gen('leaderboard_limit');
      $leaderboard_limit_value = intval($leaderboard_limit->getValue());

      if (($leaderboard_size <= $leaderboard_limit_value) ||
          ($leaderboard_limit_value === 0)) {
        $leaderboard_count = $leaderboard_size;
      } else {
        $leaderboard_count = $leaderboard_limit_value;
      }
      for ($i = 0; $i < $leaderboard_count; $i++) {
        $leader = $leaderboard[$i];
        $logo_model = await $leader->getLogoModel(); // TODO: Combine Awaits
        if ($logo_model->getCustom()) {
          $image =
            <img class="icon--badge" src={$logo_model->getLogo()}></img>;
        } else {
          $iconbadge = '#icon--badge-'.$logo_model->getName();
          $image =
            <svg class="icon--badge">
              <use href={$iconbadge} />
            </svg>;
        }
        $list->appendChild(
          <li>
            <a href="#" data-team={$leader->getName()}>
              {$image}
            </a>
          </li>
        );
      }
    }

    return
      <div>
        <header class="module-header">
          <h6>{tr('Teams')}</h6>
        </header>
        <div class="module-content">
          <div class="fb-section-border">
            <!--
              Removing the option for people to select their own team for now
            --> <!--
              <div class="module-top"> <div class="radio-tabs"> <input
              type="radio" name="fb--module--teams" id="fb--module--teams--all"
              checked={true}/> <label for="fb--module--teams--all"
              class="click-effect"><span>{tr('Everyone')}</span></label> <
              input type="radio" name="fb--module--teams"
              id="fb--module--teams--your-team"/> <label
              for="fb--module--teams--your-team" class="click-effect"><span>{
              tr('Your Team')}</span></label> </div> </div>
            -->
            <div class="module-scrollable">
              {$list}
            </div>
          </div>
        </div>
      </div>;
  }
}

/* HH_IGNORE_ERROR[1002] */
$teams_generated = new TeamModuleController();
$teams_generated->sendRender();
