<?hh // strict

class ViewModeController extends Controller {
  <<__Override>>
  protected function getTitle(): string {
    $custom_org = \HH\Asio\join(Configuration::gen('custom_org'));
    return tr($custom_org->getValue()).' | '.tr('View mode');
  }

  <<__Override>>
  protected function getFilters(): array<string, mixed> {
    return array(
      'GET' => array(
        'page' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
      ),
    );
  }

  <<__Override>>
  protected function getPages(): array<string> {
    return array('main');
  }

  public async function genRenderMainContent(): Awaitable<:xhp> {
    $branding_gen = await $this->genRenderBranding();
    return
      <div id="fb-gameboard" class="fb-gameboard gameboard--viewmode">
        <div class="gameboard-header">
          <nav class="fb-navigation fb-gameboard-nav">
            <div class="branding">
              <a href="/">
                <div class="branding-rules">
                  {$branding_gen}
                </div>
              </a>
            </div>
          </nav>
        </div>
        <div class="fb-map"></div>
        <div class="fb-module-container container--row">
          <aside
            data-name={tr('Leaderboard')}
            class="module--outer-left active"
            data-module="leaderboard-viewmode">
          </aside>
          <aside
            data-name={tr('Activity')}
            class="module--inner-right activity-viewmode active"
            data-module="activity-viewmode">
          </aside>
          <aside
            data-name={tr('Game Clock')}
            class="module--outer-right active"
            data-module="game-clock">
          </aside>
        </div>
      </div>;
  }

  public async function genRenderPage(string $page): Awaitable<:xhp> {
    switch ($page) {
      case 'main':
        return await $this->genRenderMainContent();
        break;
      default:
        return await $this->genRenderMainContent();
        break;
    }
  }

  <<__Override>>
  public async function genRenderBody(string $page): Awaitable<:xhp> {
    $rendered_page = await $this->genRenderPage($page);
    return
      <body data-section="viewer-mode">
        <div class="fb-sprite" id="fb-svg-sprite"></div>
        <div id="fb-main-content" class="fb-page">
          {$rendered_page}
        </div>
        <script type="text/javascript" src="static/dist/js/app.js"></script>
      </body>;
  }
}
