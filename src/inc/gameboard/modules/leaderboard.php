<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class LeaderboardModuleController extends ModuleController {
  public async function genRender(): Awaitable<:xhp> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    await tr_start();
    $leaderboard_ul = <ul></ul>;

    list($my_team, $my_rank, $gameboard) = await \HH\Asio\va(
      MultiTeam::genTeam(SessionUtils::sessionTeam()),
      Team::genMyRank(SessionUtils::sessionTeam()),
      Configuration::gen('gameboard'),
    );

    // If refresing is enabled, do the needful
    if ($gameboard->getValue() === '1') {
      $leaders = await MultiTeam::genLeaderboard();
      $rank = 1;
      $l_max = (count($leaders) > 5) ? 5 : count($leaders);
      for ($i = 0; $i < $l_max; $i++) {
        $team = $leaders[$i];

        // TODO also duplicated in modules/teams.php. Needs to be un-duplicated.
        $logo_model = await $team->getLogoModel(); // TODO: Combine Awaits
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

        $xlink_href = '#icon--badge-'.$team->getLogo();
        $leaderboard_ul->appendChild(
          <li class="fb-user-card">
            <div class="user-avatar">
              {$image}
            </div>
            <div class="player-info">
              <h6>{$team->getName()}</h6>
              <span class="player-rank">{tr('Rank')}&nbsp;{$rank}</span>
              <br></br>
              <span class="player-score">
                {strval($team->getPoints())}&nbsp;{tr('pts')}
              </span>
            </div>
          </li>
        );
        $rank++;
      }
    }

    if ($my_team->getVisible() === true) {
      $leaderboard_limit = await Configuration::gen('leaderboard_limit');
      if ($my_rank >= intval($leaderboard_limit->getValue())) {
        $my_rank = intval($leaderboard_limit->getValue()) + 1 ."+";
      }
    } else {
      $my_rank = "N/A";
    }

    return
      <div>
        <header class="module-header">
          <h6>{tr('Leaderboard')}</h6>
        </header>
        <div class="module-content">
          <div class="fb-section-border">
            <div class="module-top player-info">
              <h5 class="player-name">{$my_team->getName()}</h5>
              <span class="player-rank">{tr('Your Rank')}: {$my_rank}</span>
              <br></br>
              <span class="player-score">
                {tr('Your Score')}: {strval($my_team->getPoints())}&nbsp;
                {tr('pts')}
              </span>
            </div>
            <div class="module-scrollable leaderboard-info">
              {$leaderboard_ul}
            </div>
          </div>
        </div>
      </div>;
  }
}

/* HH_IGNORE_ERROR[1002] */
$leaderboard_generated = new LeaderboardModuleController();
$leaderboard_generated->sendRender();
