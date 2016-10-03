<?hh // strict

class TutorialModalController extends ModalController {
  private function getStep(
    string $step,
  ): (string, string, string, ?:xhp, :xhp) {
    switch ($step) {
      case 'tool-bars':
        $content =
          <div class="main-text">
            <p>
              {tr(
                'Tool bars are located on all edges of the gameboard. Tap a category to expand and close each tool bar.',
              )}
            </p>
          </div>;
        return tuple($step, tr('Tool_Bars'), 'game-clock', null, $content);
      case 'game-clock':
        $content =
          <div class="main-text">
            <p>
              {tr(
                'Tap the "Game Clock" to keep track of time during gameplay. Don’t let time get the best of you.',
              )}
            </p>
          </div>;
        return tuple($step, tr('Game_Clock'), 'captures', null, $content);
      case 'captures':
        $header =
          <div class="header-graphic">
            <svg class="icon--country-australia--captured">
              <use href="#icon--country-australia--captured"></use>
            </svg>
          </div>;
        $content =
          <div class="main-text">
            <p>
              {tr('Countries marked with an ')}
              <svg class="icon--team-indicator your-team">
                <use href="#icon--team-indicator"></use>
              </svg>
              {tr('are captured by you.')}
            </p>
            <p>
              {tr('Countries marked with an ')}
              <svg class="icon--team-indicator opponent-team">
                <use href="#icon--team-indicator"></use>
              </svg>{tr(' are owned by others.')}
            </p>
          </div>;
        return tuple($step, tr('Captures'), 'zoom', $header, $content);
      case 'zoom':
        $header =
          <div class="header-graphic">
            <svg class="icon--country-group--europe">
              <use href="#icon--country-group--europe"></use>
            </svg>
            <svg class="icon--tutorial--zoom">
              <use href="#icon--tutorial--zoom"></use>
            </svg>
          </div>;
        $content =
          <div class="main-text">
            <p>{tr('Tap Plus[+] to Zoom In. Tap Minus[-] to Zoom Out.')}</p>
            <p>{tr('Click and Drag to move left, right, up and down.')}</p>
          </div>;
        return tuple($step, tr('Zoom'), 'command-lines', $header, $content);
      case 'command-lines':
        $header =
          <div class="header-graphic">
            <div class="fb-column-container tutorial-graphic--command-lines">
              <span class="indicator">/atk</span>
              <ul>
                <li>United_Arab_Emirates</li>
                <li>United_Kingdom</li>
                <li class="highlighted">United_States</li>
                <li>Uruguay</li>
                <li>Uzbekistan</li>
              </ul>
            </div>
          </div>;
        $content =
          <div class="main-text">
            <p>
              {tr(
                'Tap Forward Slash [/] to activate computer commands. A list of commands can be found under "Rules".',
              )}
            </p>
          </div>;
        return tuple(
          $step,
          tr('Command_Line'),
          'navigation',
          $header,
          $content,
        );
      case 'navigation':
        $content =
          <div class="main-text">
            <p>
              {tr(
                'Click "Nav" to access main navigation links like Rules of Play, Registration, Blog, Jobs & more.',
              )}
            </p>
          </div>;
        return tuple($step, tr('Navigation'), 'scoreboard', null, $content);
      case 'scoreboard':
        $content =
          <div class="main-text">
            <p>
              {tr(
                'Track your competition by clicking "scoreboard" to access real-time game statistics and graphs.',
              )}
            </p>
          </div>;
        return tuple($step, tr('Scoreboard'), 'game-on', null, $content);
      case 'game-on':
        $content =
          <div class="main-text">
            <p>{tr('Have fun, be the best and conquer the world.')}</p>
          </div>;
        return tuple($step, tr('Game_On'), '', null, $content);
      default:
        invariant(false, 'invalid tutorial name');
    }
  }

  <<__Override>>
  public async function genRender(string $step): Awaitable<:xhp> {
    list($step, $name, $next_step, $header, $content) = $this->getStep($step);

    return
      <div class="fb-modal-content fb-tutorial" data-tutorial-step={$step}>
        {$header}
        <div class="modal-title">
          <h4>{tr('tutorial_')}<span class="highlighted">{$name}</span></h4>
        </div>
        <div class="tutorial-content">
          {$content}
          <div class="tutorial-navigation fb-column-container">
            <ul class="tutorial-progress"></ul>
            <div class="tutorial-actionable">
              <a
                href="#"
                class="fb-cta cta--yellow"
                data-next-tutorial={$next_step}>
                {tr('Next')}
              </a>
            </div>
          </div>
        </div>
        <div class="tutorial-skip">
          <a href="#" class="fb-cta js-close-tutorial">
            {tr('Skip to play')}
          </a>
        </div>
      </div>;
  }
}
