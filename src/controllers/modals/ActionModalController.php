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
      case 'pause-game':
        $title =
          <h4>
            {tr('pause_')}<span class="highlighted">{tr('Game')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>{tr('Are you sure you want to pause the current game?')}</p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="pause_game" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'unpause-game':
        $title =
          <h4>
            {tr('unpause_')}<span class="highlighted">{tr('Game')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>{tr('Are you sure you want to unpause the current game?')}</p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="unpause_game" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'delete-team':
        $title =
          <h4>
            {tr('delete_')}<span class="highlighted">{tr('Team')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>
              {tr(
                'Are you sure you want to delete this team? All data for this team will be irreversibly removed, including scoring logs. If you prefer to retain data, you can disable the team instead.',
              )}
            </p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="delete_team" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'delete-level':
        $title =
          <h4>
            {tr('delete_')}<span class="highlighted">{tr('Level')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>
              {tr(
                'Are you sure you want to delete this level? All data for this level will be irreversibly removed, including scores.',
              )}
            </p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="delete_level" class="fb-cta cta--yellow">
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
              <a href="#" class="fb-cta cta--yellow js-close-modal">
                {tr('OK')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'restore-database':
        $title =
          <h4>
            {tr('restore_')}<span class="highlighted">{tr('Database')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>
              {tr(
                'Are you sure you want to restore the database? This will overwrite ALL existing data!',
              )}
            </p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="restore_database" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'reset-database':
        $title =
          <h4>
            {tr('reset_')}<span class="highlighted">{tr('Database')}</span>
          </h4>;
        $content =
          <div class="action-main">
            <p>
              {tr(
                'Are you sure you want to reset the database? This will destroy ALL data! Admin accounts will remain.',
              )}
            </p>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('No')}
              </a>
              <a href="#" id="reset_database" class="fb-cta cta--yellow">
                {tr('Yes')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      case 'account':
        $title =
          <h4>
            {tr('account_')}<span class="highlighted">{tr('Settings')}</span>
          </h4>;
        $oauth_header = '';
        if (Configuration::getFacebookOAuthSettingsExists() === true) {
          $linked = \HH\Asio\join(
            Team::genTeamOAuthTokenExists(
              'facebook_oauth',
              SessionUtils::sessionTeam(),
            ),
          );
          $button_text = tr('Facebook');
          $button =
            <a
              name="facebook-oauth-button"
              href="#"
              class="fb-cta cta--yellow js-trigger-facebook-oauth">
              {tr('Link Your')}<br />{tr($button_text)}<br />{tr('Account')}
            </a>;
          if ($linked === true) {
            $button =
              <a
                name="facebook-oauth-button"
                href="#"
                class="fb-cta cta--yellowe">
                {tr($button_text)}<br />{tr('Account Is Linked')}
              </a>;
          }
          $oauth_header =
            <p>
              {tr(
                'You may link your FBCTF account on this instance with your other providers.  Note that this will provide your email address to the administrators of this FBCTF instance.',
              )}
            </p>;
          $facebook_oauth_content =
            <div class="facebook-link-form">
              <div class="action-actionable">
                {$button}
              </div>
              <br />
              <span class="facebook-link-response highlighted--blue"></span>
              <br />
            </div>;
        } else {
          $facebook_oauth_content = '';
        }
        if (Configuration::getGoogleOAuthFileExists() === true) {
          $linked = \HH\Asio\join(
            Team::genTeamOAuthTokenExists(
              'google_oauth',
              SessionUtils::sessionTeam(),
            ),
          );
          $button_text = tr('Google');
          $button =
            <a
              name="google-oauth-button"
              href="#"
              class="fb-cta cta--yellow js-trigger-google-oauth">
              {tr('Link Your')}<br />{tr($button_text)}<br />{tr('Account')}
            </a>;
          if ($linked === true) {
            $button =
              <a
                name="google-oauth-button"
                href="#"
                class="fb-cta cta--yellowe">
                {tr($button_text)}<br />{tr('Account Is Linked')}
              </a>;
          }
          $oauth_header =
            <p>
              {tr(
                'You may link your FBCTF account on this instance with your other providers.  Note that this will provide your email address to the administrators of this FBCTF instance.',
              )}
            </p>;
          $google_oauth_content =
            <div class="google-link-form">
              <div class="action-actionable">
                {$button}
              </div>
              <br />
              <span class="google-link-response highlighted--blue"></span>
              <br />
            </div>;
        } else {
          $google_oauth_content = '';
        }

        $team =
          \HH\Asio\join(MultiTeam::genTeam(SessionUtils::sessionTeam()));
        $team_name = $team->getName();

        $content =
          <div class="action-main">
            {tr('Change your team name.')}
            <form class="fb-form-no-padding team-name-form">
              <input name="set_team_name" type="hidden" value="" />
              <div class="form-el el--text">
                <input
                  placeholder={tr('Set your team name')}
                  name="team_name"
                  type="text"
                  value={$team_name}
                  autocomplete="off"
                />
                <input
                  type="hidden"
                  name="csrf_token"
                  value={SessionUtils::CSRFToken()}
                />
              </div>
              <div class="action-actionable">
                <a
                  class=
                    "fb-cta cta--yellow js-trigger-account-team-name-save">
                  {tr('Update')}
                </a>
              </div>
              <br />
              <span class="team-name-form-response highlighted--blue"></span>
            </form>
            {$oauth_header}
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                {$facebook_oauth_content}
              </div>
              <div class="col col-pad col-2-2">
                {$google_oauth_content}
              </div>
            </div>
            <p>
              {tr(
                'Setup your FBCTF Live Sync credentials.  These credentials must be the SAME on all other FBCTF instances that you are linking.  DO NOT use your account password.',
              )}
            </p>
            <br />
            <form class="fb-form-no-padding account-link-form">
              <input name="set_livesync_password" type="hidden" value="" />
              <div class="form-el el--text">
                <input
                  placeholder={tr('Set your live sync username')}
                  name="livesync_username"
                  type="text"
                  autocomplete="off"
                />
                <input
                  placeholder={tr('Set your live sync password')}
                  name="livesync_password"
                  type="password"
                  autocomplete="off"
                />
                <input
                  type="hidden"
                  name="csrf_token"
                  value={SessionUtils::CSRFToken()}
                />
              </div>
              <div class="action-actionable">
                <a class="fb-cta cta--yellow js-trigger-account-save">
                  {tr('Submit')}
                </a>
              </div>
              <span class="account-link-form-response highlighted--blue">
              </span>
            </form>
            <div class="action-actionable">
              <a href="#" class="fb-cta cta--red js-close-modal">
                {tr('Close')}
              </a>
            </div>
          </div>;
        return tuple($title, $content);
      default:
        invariant(false, 'Invalid modal name %s', strval($modal));
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
