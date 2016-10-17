<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class TeamModuleController {
  public async function genRender(): Awaitable<:xhp> {
    await tr_start();
    $leaderboard = await MultiTeam::genLeaderboard();
    $rank = 1;

    $list = <ul class="grid-list"></ul>;

    $gameboard = await Configuration::gen('gameboard');
    if ($gameboard->getValue() === '1') {
      foreach ($leaderboard as $leader) {
        $iconbadge = '#icon--badge-'.$leader->getLogo();
        $list->appendChild(
          <li>
            <a href="#" data-team={$leader->getName()}>
              <svg class="icon--badge">
                <use href={$iconbadge} />

              </svg>
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

$teams_generated = new TeamModuleController();
echo \HH\Asio\join($teams_generated->genRender());
