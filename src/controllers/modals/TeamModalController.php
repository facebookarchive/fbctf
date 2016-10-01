<?hh // strict

class TeamModalController extends ModalController {
  <<__Override>>
  public async function genRender(string $_): Awaitable<:xhp> {
    return
      <div class="fb-modal-content">
        <div class="modal-title">
          <h4>
            {tr('team_')}[ <span class="team-name highlighted"></span> ]
          </h4>
          <a href="#" class="js-close-modal">
            <svg class="icon icon--close"><use href="#icon--close" /></svg>
          </a>
        </div>
        <div class="fb-column-container fb-modal-main">
          <div class="col col-1-4 badge-column">
            <svg class="icon--badge"><use href=""></use></svg>
            <img class="icon--badge" src=""></img>
          </div>
          <div class="col col-1-4">
            <header>
              <span class="highlighted">{tr('team_members')}</span>
            </header>
            <ul class="team-members"></ul>
          </div>
          <div class="col col-1-4"></div>
          <div class="col col-1-4 rank-column">
            <div class="points-display">
              <span class="points-number fb-numbers"></span>
              <span class="points-label">{tr('Rank')}</span>
            </div>
            <a href="#" class="js-launch-modal" data-modal="scoreboard">
              {tr('Scoreboard')}
            </a>
          </div>
        </div>
        <footer class="modal-footer fb-column-container">
          <div class="col col-1-4">
            <header>
              <span class="highlighted">{tr('base_pts')}</span>
            </header>
            <div class="point-total points--base fb-numbers"></div>
          </div>
          <div class="col col-1-4">
            <header>
              <span class="highlighted">{tr('quiz_pts')}</span>
            </header>
            <div class="point-total points--quiz fb-numbers"></div>
          </div>
          <div class="col col-1-4">
            <header>
              <span class="highlighted">{tr('flag_pts')}</span>
            </header>
            <div class="point-total points--flag fb-numbers"></div>
          </div>
          <div class="col col-1-4">
            <header>
              <span class="highlighted">{tr('total_pts')}</span>
            </header>
            <div class="point-total points--total fb-numbers"></div>
          </div>
        </footer>
      </div>;
  }
}
