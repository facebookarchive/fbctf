<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class LeaderboardModuleController {
  public async function genRender(): Awaitable<:xhp> {
    await tr_start();
    $leaderboard_ul = <ul></ul>;

    $my_team = await MultiTeam::genTeam(SessionUtils::sessionTeam());
    $my_rank = await Team::genMyRank(SessionUtils::sessionTeam());

    // If refresing is enabled, do the needful
    $gameboard = await Configuration::gen('gameboard');
    if ($gameboard->getValue() === '1') {
      $leaders = await MultiTeam::genLeaderboard();
      $rank = 1;
      $l_max = (count($leaders) > 5) ? 5 : count($leaders);
      for ($i = 0; $i < $l_max; $i++) {
        $team = $leaders[$i];
        $xlink_href = '#icon--badge-'.$team->getLogo();
        $leaderboard_ul->appendChild(
          <li class="fb-user-card">
            <div class="user-avatar">
              <svg class="icon--badge">
                <use href={$xlink_href} />

              </svg>
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

$leaderboard_generated = new LeaderboardModuleController();
echo \HH\Asio\join($leaderboard_generated->genRender());
