<?hh // strict

class ActionModalController extends ModalController {
  private function getModal(string $modal): (:xhp, :xhp) {
    switch ($modal) {
      case 'begin-game':
        $title =
          <h4>
            {tr('begin_')}<span class="highlighted">{tr('Game')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>
              {tr(
                'Are you sure you want to kick off the game? Logs will be cleared and progressive scoreboard will start',
              )}
            </p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="begin_game" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'end-game':
        $title =
          <h4>{tr('end_')}<span class="highlighted">{tr('Game')}</span></h4>;
        $content =
          <div class="action-main">
            <p>{tr('Are you sure you want to finish the current game?')}</p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="end_game" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'logout':
        $title =
          <h4>
            {tr('status_')}<span class="highlighted">{tr('Logout')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>{tr('Are you sure you want to logout from the game?')}</p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="index.php?p=logout" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'save':
        $title =
          <h4>
            {tr('status_')}<span class="highlighted">{tr('Saved')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>{tr('All changes have been successfully saved.')}</p>
            <div class="action-actionable">
              <a
                href="#"
                class="fb-cta cta--yellow js-close-modal js-confirm-save">
                {tr('OK')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'error':
        $title =
          <h4>
            {tr('status_')}
            <span class="highlighted--red">{tr('Error')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <div class="error-text">
              <p>
                {tr(
                  'Sorry your form was not saved. Please correct the all errors and save again.',
                )}
              </p>
            </div>
            <ul class="errors-list"></ul>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--yellow js-close-modal">
                {tr('OK')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'cancel':
        $title =
          <h4>
            {tr('cancel_')}
            <span class="admin-section-name highlighted"></span>
          </h4>;
        $content =
          <div class="action-main">
            <p>
              {tr(
                'Are you sure you want to cancel? You have unsaved changes that will be reverted.',
              )}
            </p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" class="fb-cta cta--yellow js-close-modal">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'import-done':
        $title =
          <h4>
            {tr('status_')}<span class="highlighted">{tr('Imported')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>{tr('Items have been imported successfully')}</p>
            <div class="action-actionable">
              <a
                href="#"
                class="fb-cta cta--yellow js-close-modal js-confirm-save">
                {tr('OK')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      default:
        invariant(false, "Invalid modal name $modal");
    }
  }

  <<__Override>>
  public async function genRender(string $modal): Awaitable<:xhp> {
    list($title, $content) = $this->getModal($modal);

    return
      <div class="fb-modal-content">
        <header class="modal-title">
          {$title}
          <a href="#" class="js-close-modal">
            <svg class="icon icon--close">
              <use href="#icon--close" />
            </svg>
          </a>
        </header>
        {$content}
      </div>;
  }
}
