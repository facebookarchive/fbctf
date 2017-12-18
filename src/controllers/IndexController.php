<?hh // strict

class IndexController extends Controller {
  <<__Override>>
  public function getTitle(): string {
    $custom_org = \HH\Asio\join(Configuration::gen('custom_org'));
    return tr($custom_org->getValue()).' '.tr('CTF');
  }

  <<__Override>>
  protected function getFilters(): array<string, mixed> {
    return array(
      'GET' => array(
        'page' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
        'action' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
      ),
    );
  }

  <<__Override>>
  protected function getPages(): array<string> {
    return array(
      'main',
      'countdown',
      'rules',
      'registration',
      'login',
      'error',
      'mobile',
      'game',
      'admin',
    );
  }

  public function renderMainContent(): :xhp {
    $custom_org = \HH\Asio\join(Configuration::gen('custom_org'));
    if ($custom_org->getValue() === 'Facebook') {
      $welcome_msg =
        tr(
          'Welcome to the Facebook Capture the Flag Competition. By clicking "Play," you will be entered into the official CTF challenge. Good luck in your conquest.',
        );
    } else {
      $welcome_msg =
        'Welcome to the '.
        $custom_org->getValue().
        ' Capture the Flag Competition. By clicking "Play," you will be entered into the official CTF challenge. Good luck in your conquest.';
    }
    return
      <div class="fb-row-container full-height fb-scroll">
        <main
          role="main"
          class=
            "fb-main page--landing row-fluid no-shrink center-vertically fb-img-glitch">
          <div class="fb-container fb-centered-main">
            <h1 class="fb-glitch" data-text={tr('Conquer the world')}>
              {tr('Conquer the world')}
            </h1>
            <p class="typed-text">
              {$welcome_msg}
            </p>
            <div class="fb-actionable">
              <a href="/index.php?page=countdown" class="fb-cta cta--yellow">
                {tr('Play')}
              </a>
            </div>
          </div>
        </main>
      </div>;
  }

  public async function genRenderCountdownContent(): Awaitable<:xhp> {
    if (SessionUtils::sessionActive()) {
      $play_nav =
        <form class="fb-form inner-container">
          <p>
            {tr(
              'Get ready for the CTF to start and access the gameboard now!',
            )}
          </p>
          <div class="form-el--actions">
            <a href="/index.php?p=game" class="fb-cta cta--yellow">
              {tr('Gameboard')}
            </a>
          </div>
        </form>;
    } else {
      $registration = await Configuration::gen('registration');
      if ($registration->getValue() === '1') {
        $registration_button =
          <a
            style="margin-left: 1em;"
            href="/index.php?page=registration"
            class="fb-cta cta--yellow">
            {tr('Register Team')}
          </a>;
      } else {
        $registration_button = <a></a>;
      }
      $play_nav =
        <form class="fb-form inner-container">
          <p>
            {tr('Get ready for the CTF to start and register your team now!')}
          </p>
          <div class="form-el--actions">
            {$registration_button}
            <a
              style="margin-left: 1em;"
              href="/index.php?page=login"
              class="fb-cta cta--yellow">
              {tr('Login')}
            </a>
          </div>
        </form>;
    }
    list($config_game, $config_next_game) = await \HH\Asio\va(
      Configuration::gen('game'),
      Configuration::gen('next_game'),
    );
    $game = $config_game->getValue();
    $next_game = $config_next_game->getValue();
    if ($game === '1') {
      $next_game_text = tr('In Progress');
      $countdown = array('--', '--', '--', '--');
    } else if ($next_game === '0' || intval($next_game) < time()) {
      $next_game_text = tr('Soon');
      $countdown = array('--', '--', '--', '--');
    } else {
      $next_game_text = date(tr('date and time format'), $next_game);
      $game_start = new DateTime();
      $game_start->setTimestamp(intval($next_game));
      $now = new DateTime('now');
      $countdown_diff = $now->diff($game_start);
      $countdown = explode('-', $countdown_diff->format('%d-%h-%i-%s'));
    }
    return
      <div class="fb-row-container full-height fb-scroll">
        <main
          role="main"
          class=
            "fb-main page--game-status row-fluid no-shrink center-vertically fb-img-glitch">
          <div class="fb-container fb-centered-main">
            <h3 class="title-lead">{tr('Upcoming Game')}</h3>
            <h1 class="fb-glitch" data-text={$next_game_text}>
              {$next_game_text}
            </h1>
            <ul class="upcoming-game-countdown">
              <li>
                <span class="count-number">{$countdown[0]}</span>
                {tr('_days')}
              </li>
              <li>
                <span class="count-number">{$countdown[1]}</span>
                {tr('_hours')}
              </li>
              <li>
                <span class="count-number">{$countdown[2]}</span>
                {tr('_minutes')}
              </li>
              <li>
                <span class="count-number">{$countdown[3]}</span>
                {tr('_seconds')}
              </li>
            </ul>
            {$play_nav}
          </div>
        </main>
      </div>;
  }

  public function renderRulesContent(): :xhp {
    return
      <div class="fb-column-container full-height">
        <main role="main" class="fb-main page--rules fb-scroll">
          <header class="fb-section-header fb-container">
            <h1 class="fb-glitch" data-text={tr('Official CTF Rules')}>
              {tr('Official CTF Rules')}
            </h1>
            <p class="inner-container typed-text">
              {tr(
                'Following actions are prohibited, unless explicitly told otherwise by event Admins.',
              )}
            </p>
          </header>
          <div class="fb-rules">
            <section>
              <header class="rule-section-header">
                <h3>{tr('Rule')} 1</h3>
                <h6>{tr('Cooperation')}</h6>
              </header>
              <div class="rule-main">
                <p>
                  {tr(
                    'No cooperation between teams with independent accounts. Sharing of keys or providing revealing hints to other teams is cheating, don’t do it.',
                  )}
                </p>
                <p></p>
              </div>
            </section>
            <section>
              <header class="rule-section-header">
                <h3>{tr('Rule')} 2</h3>
                <h6>{tr('Attacking Scoreboard')}</h6>
              </header>
              <div class="rule-main">
                <p>
                  {tr(
                    'No attacking the competition infrastructure. If bugs or vulns are found, please alert the competition organizers immediately.',
                  )}
                </p>
                <p></p>
              </div>
            </section>
            <section>
              <header class="rule-section-header">
                <h3>{tr('Rule')} 3</h3>
                <h6>{tr('Sabotage')}</h6>
              </header>
              <div class="rule-main">
                <p>
                  {tr(
                    'Absolutely no sabotaging of other competing teams, or in any way hindering their independent progress.',
                  )}
                </p>
                <p></p>
              </div>
            </section>
            <section>
              <header class="rule-section-header">
                <h3>{tr('Rule')} 4</h3>
                <h6>{tr('Bruteforcing')}</h6>
              </header>
              <div class="rule-main">
                <p>
                  {tr(
                    'No brute forcing of challenge flag/ keys against the scoring site.',
                  )}
                </p>
                <p></p>
              </div>
            </section>
            <section>
              <header class="rule-section-header">
                <h3>{tr('Rule')} 5</h3>
                <h6>{tr('Denial Of Service')}</h6>
              </header>
              <div class="rule-main">
                <p>
                  {tr(
                    'DoSing the CTF platform or any of the challenges is forbidden.',
                  )}
                </p>
                <p></p>
              </div>
            </section>
            <section>
              <header class="rule-section-header">
                <h3>{tr('Legal')}</h3>
                <h6>{tr('Disclaimer')}</h6>
              </header>
              <div class="rule-main">
                <p>
                  {tr(
                    'By participating in the contest, you agree to release Facebook and its employees, and the hosting organization from any and all liability, claims or actions of any kind whatsoever for injuries, damages or losses to persons and property which may be sustained in connection with the contest. You acknowledge and agree that Facebook et al is not responsible for technical, hardware or software failures, or other errors or problems which may occur in connection with the contest.',
                  )}
                </p>
              </div>
            </section>
            <p>
              {tr(
                'If you have any questions about what is or is not allowed, please ask an organizer.',
              )}
            </p>
            <p></p>
            <p>{tr('Have fun!')}</p>
            <p></p>
          </div>
        </main>
      </div>;
  }

  public async function genRenderLogosSelection(): Awaitable<:xhp> {
    return <emblem-carousel />;
  }

  public async function genRenderRegistrationNames(): Awaitable<:xhp> {
    $awaitables = Map {
      'login_facebook' => Configuration::gen('login_facebook'),
      'login_google' => Configuration::gen('login_google'),
      'registration_players' => Configuration::gen('registration_players'),
      'registration_facebook' => Configuration::gen('registration_facebook'),
      'registration_google' => Configuration::gen('registration_google'),
      'registration_type' => Configuration::gen('registration_type'),
      'ldap' => Configuration::gen('ldap'),
      'logos_selection' => $this->genRenderLogosSelection(),
      'facebook_enabled' => Integration::facebookOAuthEnabled(),
      'google_enabled' => Integration::googleOAuthEnabled(),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $login_facebook = $awaitables_results['login_facebook'];
    $login_google = $awaitables_results['login_google'];
    $registration_players = $awaitables_results['registration_players'];
    $registration_facebook = $awaitables_results['registration_facebook'];
    $registration_google = $awaitables_results['registration_google'];
    $registration_type = $awaitables_results['registration_type'];
    $ldap = $awaitables_results['ldap'];
    $logos_selection = $awaitables_results['logos_selection'];
    $facebook_enabled = $awaitables_results['facebook_enabled'];
    $google_enabled = $awaitables_results['google_enabled'];

    invariant(
      $login_facebook instanceof Configuration,
      'login_facebook should be of type Configuration',
    );
    invariant(
      $login_google instanceof Configuration,
      'login_google should be of type Configuration',
    );
    invariant(
      $registration_players instanceof Configuration,
      'registration_players should be of type Configuration',
    );
    invariant(
      $registration_facebook instanceof Configuration,
      'registration_facebook should be of type Configuration',
    );
    invariant(
      $registration_google instanceof Configuration,
      'registration_google should be of type Configuration',
    );
    invariant(
      $registration_type instanceof Configuration,
      'registration_type should be of type Configuration',
    );
    invariant(
      $ldap instanceof Configuration,
      'ldap should be of type Configuration',
    );

    $players = intval($registration_players->getValue());
    $names_ul = <ul></ul>;

    for ($i = 1; $i <= $players; $i++) {
      $name_ = 'registration_name_'.$i;
      $email_ = 'registration_email_'.$i;
      $names_ul->appendChild(
        <li class="fb-column-container">
          <div class="col col-2-4 form-el el--text">
            <label for="">{tr('Name')}</label>
            <input class="registration-name" name={$name_} type="text" />
          </div>
          <div class="col col-2-4 form-el el--text">
            <label for="">{tr('Email')}</label>
            <input class="registration-email" name={$email_} type="email" />
          </div>
        </li>
      );
    }

    if ($registration_type->getValue() === '2') {
      $token_field =
        <div class="form-el el--text">
          <label for="">{tr('Token')}</label>
          <input autocomplete="off" name="token" type="text" />
        </div>;
    } else {
      $token_field = <div></div>;
    }

    $ldap_domain_suffix = "";
    if ($ldap->getValue() === '1') {
      $ldap_domain_suffix = await Configuration::gen('ldap_domain_suffix');
      $ldap_domain_suffix = $ldap_domain_suffix->getValue();
    }

    $page_header =
      <header class="fb-section-header fb-container">
        <h1 class="fb-glitch" data-text="Team Registration">
          {tr('Team Registration')}
        </h1>
      </header>;

    $oauth_header = '';
    $oauth_form = '';

    if ((($facebook_enabled === true) || ($google_enabled === true)) &&
        (($registration_facebook->getValue() === '1') ||
         ($registration_google->getValue() === '1'))) {
      $oauth_header =
        <header class="fb-section-header fb-container">
          <p class="inner-container">
            {tr(
              'Register to play Capture The Flag with one of the options below. Once you select an option, you will be registered and logged in.',
            )}
          </p>
        </header>;
      if (($facebook_enabled === true) &&
          (($login_facebook->getValue() === '1') ||
           ($registration_facebook->getValue() === '1'))) {
        $facebook_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=facebook"
              class="fb-cta cta--yellow">
              {tr('Register with Facebook Account')}
            </a>
          </div>;
      } else {
        $facebook_button = '';
      }
      if (($google_enabled === true) &&
          (($login_google->getValue() === '1') ||
           ($registration_google->getValue() === '1'))) {
        $google_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=google"
              class="fb-cta cta--yellow">
              {tr('Register with Google Account')}
            </a>
          </div>;
      } else {
        $google_button = '';
      }
      $oauth_form =
        <div class="fb-oauth-registration">
          {$facebook_button}
          {$google_button}
        </div>;
    }

    $registration_header =
      <header class="fb-section-header fb-container">
        <p class="inner-container">
          {tr(
            'Or register to use username and password here. Once you have registered, you will be logged in.',
          )}
        </p>
      </header>;

    $registration_form =
      <div class="fb-registration">
        <form class="fb-form">
          <input type="hidden" name="action" value="register_names" />
          <fieldset class="form-set multiple-registration-list">
            {$names_ul}
          </fieldset>
          <br /><br />
          <fieldset class="form-set fb-container container--small">
            <div class="form-el el--text">
              <label for="">{tr('Team Name')}</label>
              <input
                autocomplete="off"
                name="team_name"
                type="text"
                maxlength={20}
                autofocus={true}
              />
              {$ldap_domain_suffix}
            </div>
            <div class="form-el el--text">
              <label for="">{tr('Password')}</label>
              <input autocomplete="off" name="password" type="password" />
            </div>
            {$token_field}
          </fieldset>
          <div class="fb-choose-emblem">
            <h6>{tr('Choose an Emblem')}</h6>
            <h6>
              <a href="#" id="custom-emblem-link">
                {tr('or upload your own')}
              </a>
            </h6>
            <div class="custom-emblem">
              <input
                autocomplete="off"
                name="custom-emblem"
                id="custom-emblem-input"
                type="file"
                accept="image/*"
              />
              <img id="custom-emblem-preview" src="" height={62} width={80}>
              </img>
            </div>
            <div class="emblem-carousel">
              <div id="custom-emblem-carousel-notice">
                <div class="center-wrapper">
                  <h6>
                    <a href="#" id="custom-emblem-clear-link">
                      {tr(
                        'Clear your custom emblem to use a default emblem.',
                      )}
                    </a>
                  </h6>
                </div>
              </div>
              {$logos_selection}
            </div>
          </div>
          <div class="form-el--actions fb-container container--small">
            <p>
              <button
                id="register_button"
                class="fb-cta cta--yellow"
                type="button">
                {tr('Sign Up')}
              </button>
            </p>
          </div>
        </form>
      </div>;

    return
      <main
        role="main"
        class="fb-main page--team-registration full-height fb-scroll">
        {$page_header}
        {$oauth_header}
        {$oauth_form}
        {$registration_header}
        {$registration_form}
      </main>;
  }

  public async function genRenderRegistrationNoNames(): Awaitable<:xhp> {
    $awaitables = Map {
      'login_facebook' => Configuration::gen('login_facebook'),
      'login_google' => Configuration::gen('login_google'),
      'registration_facebook' => Configuration::gen('registration_facebook'),
      'registration_google' => Configuration::gen('registration_google'),
      'registration_type' => Configuration::gen('registration_type'),
      'ldap' => Configuration::gen('ldap'),
      'logos_selection' => $this->genRenderLogosSelection(),
      'facebook_enabled' => Integration::facebookOAuthEnabled(),
      'google_enabled' => Integration::googleOAuthEnabled(),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $login_facebook = $awaitables_results['login_facebook'];
    $login_google = $awaitables_results['login_google'];
    $registration_facebook = $awaitables_results['registration_facebook'];
    $registration_google = $awaitables_results['registration_google'];
    $registration_type = $awaitables_results['registration_type'];
    $ldap = $awaitables_results['ldap'];
    $logos_selection = $awaitables_results['logos_selection'];
    $facebook_enabled = $awaitables_results['facebook_enabled'];
    $google_enabled = $awaitables_results['google_enabled'];

    invariant(
      $login_facebook instanceof Configuration,
      'login_facebook should be of type Configuration',
    );
    invariant(
      $login_google instanceof Configuration,
      'login_google should be of type Configuration',
    );
    invariant(
      $registration_facebook instanceof Configuration,
      'registration_facebook should be of type Configuration',
    );
    invariant(
      $registration_google instanceof Configuration,
      'registration_google should be of type Configuration',
    );
    invariant(
      $registration_type instanceof Configuration,
      'registration_type should be of type Configuration',
    );
    invariant(
      $ldap instanceof Configuration,
      'ldap should be of type Configuration',
    );

    if ($registration_type->getValue() === '2') {
      $token_field =
        <div class="form-el el--text">
          <label for="">{tr('Token')}</label>
          <input autocomplete="off" name="token" type="text" />
        </div>;
    } else {
      $token_field = <div></div>;
    }

    $ldap_domain_suffix = "";
    if ($ldap->getValue() === '1') {
      $ldap_domain_suffix = await Configuration::gen('ldap_domain_suffix');
      $ldap_domain_suffix = $ldap_domain_suffix->getValue();
    }

    $page_header =
      <header class="fb-section-header fb-container">
        <h1 class="fb-glitch" data-text="Team Registration">
          {tr('Team Registration')}
        </h1>
      </header>;

    $oauth_header = '';
    $oauth_form = '';

    if ((($facebook_enabled === true) || ($google_enabled === true)) &&
        (($registration_facebook->getValue() === '1') ||
         ($registration_google->getValue() === '1'))) {
      $oauth_header =
        <header class="fb-section-header fb-container">
          <p class="inner-container">
            {tr(
              'Register to play Capture The Flag with one of the options below. Once you select an option, you will be registered and logged in.',
            )}
          </p>
        </header>;
      if (($facebook_enabled === true) &&
          (($login_facebook->getValue() === '1') ||
           ($registration_facebook->getValue() === '1'))) {
        $facebook_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=facebook"
              class="fb-cta cta--yellow">
              {tr('Register with Facebook Account')}
            </a>
          </div>;
      } else {
        $facebook_button = '';
      }
      if (($google_enabled === true) &&
          (($login_google->getValue() === '1') ||
           ($registration_google->getValue() === '1'))) {
        $google_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=google"
              class="fb-cta cta--yellow">
              {tr('Register with Google Account')}
            </a>
          </div>;
      } else {
        $google_button = '';
      }
      $oauth_form =
        <div class="fb-oauth-registration">
          {$facebook_button}
          {$google_button}
        </div>;
    }

    $registration_header =
      <header class="fb-section-header fb-container">
        <p class="inner-container">
          {tr(
            'Or register to use username and password here. Once you have registered, you will be logged in.',
          )}
        </p>
      </header>;

    $registration_form =
      <div class="fb-registration">
        <form class="fb-form">
          <input type="hidden" name="action" value="register_team" />
          <fieldset class="form-set fb-container container--small">
            <div class="form-el el--text">
              <label for="">{tr('Team Name')}</label>
              <input
                autocomplete="off"
                name="team_name"
                type="text"
                maxlength={20}
                autofocus={true}
              />
              {$ldap_domain_suffix}
            </div>
            <div class="form-el el--text">
              <label for="">{tr('Password')}</label>
              <input autocomplete="off" name="password" type="password" />
            </div>
            <div id="password_error" class="el--text completely-hidden">
              <label for=""></label>
              <h6 style="color:red;">{tr('Password is too simple')}</h6>
            </div>
            {$token_field}
          </fieldset>
          <div class="fb-choose-emblem">
            <h6>{tr('Choose an Emblem')}</h6>
            <h6>
              <a href="#" id="custom-emblem-link">
                {tr('or upload your own')}
              </a>
            </h6>
            <div class="custom-emblem">
              <input
                autocomplete="off"
                name="custom-emblem"
                id="custom-emblem-input"
                type="file"
                accept="image/*"
              />
              <img id="custom-emblem-preview" src="" height={62} width={80}>
              </img>
            </div>
            <div class="emblem-carousel">
              <div id="custom-emblem-carousel-notice">
                <div class="center-wrapper">
                  <h6>
                    <a href="#" id="custom-emblem-clear-link">
                      {tr(
                        'Clear your custom emblem to use a default emblem.',
                      )}
                    </a>
                  </h6>
                </div>
              </div>
              {$logos_selection}
            </div>
          </div>
          <div class="form-el--actions fb-container container--small">
            <p>
              <button
                id="register_button"
                class="fb-cta cta--yellow"
                type="button">
                {tr('Sign Up')}
              </button>
            </p>
          </div>
        </form>
      </div>;

    return
      <main
        role="main"
        class="fb-main page--team-registration full-height fb-scroll">
        {$page_header}
        {$oauth_header}
        {$oauth_form}
        {$registration_header}
        {$registration_form}
      </main>;
  }

  public async function genRenderRegistrationContent(): Awaitable<:xhp> {
    $awaitables = Map {
      'registration' => Configuration::gen('registration'),
      'registration_facebook' => Configuration::gen('registration_facebook'),
      'registration_google' => Configuration::gen('registration_google'),
      'registration_names' => Configuration::gen('registration_names'),
      'facebook_enabled' => Integration::facebookOAuthEnabled(),
      'google_enabled' => Integration::googleOAuthEnabled(),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $registration = $awaitables_results['registration'];
    $registration_facebook = $awaitables_results['registration_facebook'];
    $registration_google = $awaitables_results['registration_google'];
    $registration_names = $awaitables_results['registration_names'];
    $facebook_enabled = $awaitables_results['facebook_enabled'];
    $google_enabled = $awaitables_results['google_enabled'];

    invariant(
      $registration instanceof Configuration,
      'registration should be of type Configuration',
    );
    invariant(
      $registration_facebook instanceof Configuration,
      'registration_facebook should be of type Configuration',
    );
    invariant(
      $registration_google instanceof Configuration,
      'registration_google should be of type Configuration',
    );
    invariant(
      $registration_names instanceof Configuration,
      'registration_names should be of type Configuration',
    );

    if ($registration->getValue() === '1') {
      if ($registration_names->getValue() === '1') {
        return await $this->genRenderRegistrationNames();
      } else {
        return await $this->genRenderRegistrationNoNames();
      }
    } else if ((($facebook_enabled === true) || ($google_enabled === true)) &&
               (($registration_facebook->getValue() === '1') ||
                ($registration_google->getValue() === '1'))) {
      if (($facebook_enabled === true) &&
          ($registration_facebook->getValue() === '1')) {
        $facebook_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=facebook"
              class="fb-cta cta--yellow">
              {tr('Register with Facebook Account')}
            </a>
          </div>;
      } else {
        $facebook_button = '';
      }
      if (($google_enabled === true) &&
          ($registration_google->getValue() === '1')) {
        $google_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=google"
              class="fb-cta cta--yellow">
              {tr('Register with Google Account')}
            </a>
          </div>;
      } else {
        $google_button = '';
      }
      return
        <main
          role="main"
          class="fb-main page--registration full-height fb-scroll">
          <header class="fb-section-header fb-container">
            <h1 class="fb-glitch" data-text={tr('Team Registration')}>
              {tr('Team Registration')}
            </h1>
            <p class="inner-container">
              {tr(
                'Register to play Capture The Flag with one of the options below. Once you have registered, you will be logged in.',
              )}
            </p>
          </header>
          <div class="fb-registration">
            {$facebook_button}
            {$google_button}
          </div>
        </main>;
    } else {
      return
        <div class="fb-row-container full-height fb-scroll">
          <main
            role="main"
            class=
              "fb-main page--game-status row-fluid no-shrink center-vertically fb-img-glitch">
            <div class="fb-container fb-centered-main">
              <h3 class="title-lead">{tr('Team Registration')}</h3>
              <h1 class="fb-glitch" data-text={tr('Not Available')}>
                {tr('Not Available')}
              </h1>
              <form class="fb-form inner-container">
                <p>
                  {tr('Team Registration will be open soon, stay tuned!')}
                </p>
                <div class="form-el--actions">
                  <a
                    href="/index.php?page=registration"
                    class="fb-cta cta--yellow">
                    {tr('Try Again')}
                  </a>
                </div>
              </form>
            </div>
          </main>
        </div>;
    }
  }

  public async function genRenderLoginContent(): Awaitable<:xhp> {
    header('Login-Page: true');
    if (SessionUtils::sessionActive()) {
      throw new IndexRedirectException();
    }
    $awaitables = Map {
      'login' => Configuration::gen('login'),
      'login_facebook' => Configuration::gen('login_facebook'),
      'login_google' => Configuration::gen('login_google'),
      'registration' => Configuration::gen('registration'),
      'registration_facebook' => Configuration::gen('registration_facebook'),
      'registration_google' => Configuration::gen('registration_google'),
      'ldap' => Configuration::gen('ldap'),
      'facebook_enabled' => Integration::facebookOAuthEnabled(),
      'google_enabled' => Integration::googleOAuthEnabled(),
    };
    $awaitables_results = await \HH\Asio\m($awaitables);

    $login = $awaitables_results['login'];
    $login_facebook = $awaitables_results['login_facebook'];
    $login_google = $awaitables_results['login_google'];
    $registration = $awaitables_results['registration'];
    $registration_facebook = $awaitables_results['registration_facebook'];
    $registration_google = $awaitables_results['registration_google'];
    $ldap = $awaitables_results['ldap'];
    $facebook_enabled = $awaitables_results['facebook_enabled'];
    $google_enabled = $awaitables_results['google_enabled'];

    invariant(
      $login instanceof Configuration,
      'login should be of type Configuration',
    );
    invariant(
      $login_facebook instanceof Configuration,
      'login_facebook should be of type Configuration',
    );
    invariant(
      $login_google instanceof Configuration,
      'login_google should be of type Configuration',
    );
    invariant(
      $registration instanceof Configuration,
      'registration should be of type Configuration',
    );
    invariant(
      $registration_facebook instanceof Configuration,
      'registration_facebook should be of type Configuration',
    );
    invariant(
      $registration_google instanceof Configuration,
      'registration_google should be of type Configuration',
    );
    invariant(
      $ldap instanceof Configuration,
      'ldap should be of type Configuration',
    );

    $ldap_domain_suffix = "";
    if ($ldap->getValue() === '1') {
      $ldap_domain_suffix = await Configuration::gen('ldap_domain_suffix');
      $ldap_domain_suffix = $ldap_domain_suffix->getValue();
    }

    if ((($facebook_enabled === true) || ($google_enabled === true)) &&
        (($login_facebook->getValue() === '1') ||
         ($login_google->getValue() === '1'))) {
      if (($facebook_enabled === true) &&
          ($login_facebook->getValue() === '1')) {
        $facebook_button_text = tr('Login with Facebook Account');
        if ($registration_facebook->getValue() === '1') {
          $facebook_button_text =
            tr('Login or Register with Facebook Account');
        }
        $facebook_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=facebook"
              class="fb-cta cta--yellow">
              {$facebook_button_text}
            </a>
          </div>;
      } else {
        $facebook_button = '';
      }
      if (($google_enabled === true) && ($login_google->getValue() === '1')) {
        $google_button_text = tr('Login with Google Account');
        if ($registration_google->getValue() === '1') {
          $google_button_text = tr('Login or Register with Google Account');
        }
        $google_button =
          <div class="form-el--actions">
            <a
              href="/data/integration_login.php?type=google"
              class="fb-cta cta--yellow">
              {$google_button_text}
            </a>
          </div>;
      } else {
        $google_button = '';
      }
      $oauth_header_message =
        tr(
          'Or login with these one of these options (existing account is required):',
        );
      if (($registration_facebook->getValue() === '1') ||
          ($registration_google->getValue() === '1')) {
        $oauth_header_message =
          tr('Or login/register with these one of these options:');
      }
      $oauth_header =
        <header class="fb-section-header fb-container">
          <p class="inner-container">
            {$oauth_header_message}
          </p>
        </header>;
      $oauth_form =
        <div class="fb-login">
          {$facebook_button}
          {$google_button}
        </div>;
    } else {
      $oauth_header = '';
      $oauth_form = '';
    }

    if ($login->getValue() === '1') {
      $login_team =
        <input
          autocomplete="off"
          name="team_name"
          type="text"
          maxlength={20}
          autofocus={true}
        />;
      $login_select = "off";
      $login_select_config = await Configuration::gen('login_select');
      if ($login_select_config->getValue() === '1') {
        $login_select = "on";
        $login_team = <select name="team_id" />;
        $login_team->appendChild(<option value="0">{tr('Select')}</option>);
        $all_active_teams = await MultiTeam::genAllActiveTeams();
        foreach ($all_active_teams as $team) {
          error_log('Getting '.$team->getName());
          $login_team->appendChild(
            <option value={strval($team->getId())}>
              {$team->getName()}
            </option>,
          );
        }
      }

      $registration_button = '';
      if ($registration->getValue() === '1') {
        $registration_button =
          <a href="/index.php?page=registration" class="fb-cta cta--blue">
            {tr('Sign Up')}
          </a>;
        $header_message =
          tr(
            'Please login here with username and password. If you have not registered, you may do so by clicking "Sign Up" below.',
          );
      } else {
        $header_message = tr('Please login here with username and password.');
      }

      $login_header =
        <header class="fb-section-header fb-container">
          <h1 class="fb-glitch" data-text={tr('Team Login')}>
            {tr('Team Login')}
          </h1>
          <p class="inner-container">
            {$header_message}
          </p>
        </header>;

      $login_form =
        <div class="fb-login">
          <form class="fb-form">
            <input type="hidden" name="action" value="login_team" />
            <input type="hidden" name="login_select" value={$login_select} />
            <fieldset class="form-set fb-container container--small">
              <div class="form-el el--text">
                <label for="">{tr('Team Name')}</label>
                {$login_team} {$ldap_domain_suffix}
              </div>
              <div class="form-el el--text">
                <label for="">{tr('Password')}</label>
                <input autocomplete="off" name="password" type="password" />
              </div>
            </fieldset>
            <div class="form-el--actions">
              <button
                id="login_button"
                class="fb-cta cta--yellow"
                type="button">
                {tr('Login')}
              </button>
            </div>
            <div class="form-el--footer">
              {$registration_button}
            </div>
          </form>
        </div>;

      $login_form =
        <main role="main" class="fb-main page--login full-height fb-scroll">
          {$login_header}
          {$login_form}
          {$oauth_header}
          {$oauth_form}
        </main>;

      return $login_form;
    } else if (Utils::getGET()->get('admin') === 'true') {
      return
        <main role="main" class="fb-main page--login full-height fb-scroll">
          <header class="fb-section-header fb-container">
            <h1 class="fb-glitch" data-text={tr('Admin Login')}>
              {tr('Admin Login')}
            </h1>
            <p class="inner-container">
              {tr(
                'Team login is disabled. Only admins can login at this time. ',
              )}
            </p>
          </header>
          <div class="fb-login">
            <form class="fb-form">
              <input type="hidden" name="action" value="login_team" />
              <input type="hidden" name="login_select" value={"off"} />
              <fieldset class="form-set fb-container container--small">
                <div class="form-el el--text">
                  <label for="">{tr('Team Name')}</label>
                  <input
                    autocomplete="off"
                    name="team_name"
                    type="text"
                    maxlength={20}
                  />
                </div>
                <div class="form-el el--text">
                  <label for="">{tr('Password')}</label>
                  <input
                    autocomplete="off"
                    name="password"
                    type="password"
                  />
                </div>
              </fieldset>
              <div class="form-el--actions">
                <button
                  id="login_button"
                  class="fb-cta cta--yellow"
                  type="button">
                  {tr('Login')}
                </button>
              </div>
            </form>
          </div>
        </main>;
    } else if ((($facebook_enabled === true) || ($google_enabled === true)) &&
               (($login_facebook->getValue() === '1') ||
                ($login_google->getValue() === '1'))) {

      if (($registration_facebook->getValue() === '1') ||
          ($registration_google->getValue() === '1')) {
        $header_message =
          tr('Login/Register with these one of these options:');
      } else {
        $header_message =
          tr(
            'Login with these one of these options (existing account is required):',
          );
      }

      $login_header =
        <header class="fb-section-header fb-container">
          <h1 class="fb-glitch" data-text={tr('Team Login')}>
            {tr('Team Login')}
          </h1>
          <p class="inner-container">
            {$header_message}
          </p>
        </header>;

      return
        <main role="main" class="fb-main page--login full-height fb-scroll">
          {$login_header}
          {$oauth_form}
          <div class="form-el--actions">
            <a href="/index.php?page=login&admin=true" class="fb-cta">
              {tr('Admin Login')}
            </a>
          </div>
        </main>;
    } else {
      return
        <div class="fb-row-container full-height fb-scroll">
          <main
            role="main"
            class=
              "fb-main page--game-status row-fluid no-shrink center-vertically fb-img-glitch">
            <div class="fb-container fb-centered-main">
              <h3 class="title-lead">{tr('Team Login')}</h3>
              <h1 class="fb-glitch" data-text={tr('Not Available')}>
                {tr('Not Available')}
              </h1>
              <form class="fb-form inner-container">
                <p>{tr('Team Login will be open soon, stay tuned!')}</p>
                <div class="form-el--actions">
                  <a href="/index.php?page=login" class="fb-cta cta--yellow">
                    {tr('Try Again')}
                  </a>
                </div>
                <div class="form-el--actions">
                  <a href="/index.php?page=login&admin=true" class="fb-cta">
                    {tr('Admin Login')}
                  </a>
                </div>
              </form>
            </div>
          </main>
        </div>;
    }
  }

  public function renderErrorPage(): :xhp {
    return
      <main role="main" class="fb-main page--login full-height fb-scroll">
        <header class="fb-section-header fb-container">
          <h1 class="fb-glitch" data-text={tr('ERROR')}>{tr('ERROR')}</h1>
        </header>
        <div class="fb-actionable">
          <h1>¯\_(ツ)_/¯</h1>
          <a href="/index.php" class="fb-cta cta--yellow">
            {tr('Start Over')}
          </a>
        </div>
      </main>;
  }

  public async function genRenderMobilePage(): Awaitable<:xhp> {
    $branding_xhp = await $this->genRenderBranding();
    return
      <div class="fb-row-container full-height page--mobile">
        <main
          role="main"
          class="fb-main row-fluid center-vertically fb-img-glitch">
          <div class="fb-container fb-centered-main">
            <h1 class="fb-glitch" data-text={tr('Window is too small')}>
              {tr('Window is too small')}
            </h1>
            <p>
              {tr(
                'For the best CTF experience, please make window size bigger.',
              )}
            </p>
            <p>{tr('Thank you.')}</p>
            <div class="fb-actionable">
              <a href="/index.php" class="fb-cta cta--yellow">
                {tr('Reload')}
              </a>
            </div>
          </div>
        </main>
        <div class="row-fixed">
          {$branding_xhp}
        </div>
      </div>;
  }

  public async function genRenderMainNav(): Awaitable<:xhp> {
    if (SessionUtils::sessionActive()) {
      $right_nav =
        <ul class="nav-right">
          <li>
            <a href="/index.php?p=logout" data-active="logout">
              {tr('Logout')}
            </a>
          </li>
          <li></li>
          <li>
            <a href="/index.php?p=game" data-active="gameboard">
              {tr('Gameboard')}
            </a>
          </li>
        </ul>;
    } else {
      $right_nav =
        <ul class="nav-right">
          <li>
            <a
              href="/index.php?page=registration"
              data-active="registration">
              {tr('Registration')}
            </a>
          </li>
          <li></li>
          <li>
            <a href="/index.php?page=login" data-active="login">
              {tr('Login')}
            </a>
          </li>
        </ul>;
    }
    $left_nav =
      <ul class="nav-left">
        <li>
          <a href="/index.php?page=countdown" data-active="countdown">
            {tr('Play CTF')}
          </a>
        </li>
        <li></li>
        <li>
          <a href="/index.php?page=rules" data-active="rules">
            {tr('Rules')}
          </a>
        </li>
      </ul>;
    $branding_gen = await $this->genRenderBranding();
    $branding =
      <div class="branding">
        <a href="/">
          <div class="branding-rules">
            {$branding_gen}
          </div>
        </a>
      </div>;

    return
      <nav class="fb-main-nav fb-navigation">
        {$left_nav}
        {$branding}
        {$right_nav}
      </nav>;
  }

  public async function genRenderPage(string $page): Awaitable<:xhp> {
    switch ($page) {
      case 'main':
        return $this->renderMainContent();
      case 'error':
        return $this->renderErrorPage();
      case 'mobile':
        return await $this->genRenderMobilePage();
      case 'login':
        return await $this->genRenderLoginContent();
      case 'registration':
        return await $this->genRenderRegistrationContent();
      case 'rules':
        return $this->renderRulesContent();
      case 'countdown':
        return await $this->genRenderCountdownContent();
      case 'game':
        throw new GameRedirectException();
      case 'admin':
        throw new AdminRedirectException();
      default:
        return $this->renderMainContent();
    }
  }

  <<__Override>>
  public async function genRenderBody(string $page): Awaitable<:xhp> {
    list($rendered_page, $rendered_nav) = await \HH\Asio\va(
      $this->genRenderPage($page),
      $this->genRenderMainNav(),
    );
    return
      <body data-section="pages">
        <div class="fb-sprite" id="fb-svg-sprite"></div>
        <div class="fb-viewport">
          <div id="fb-main-nav">{$rendered_nav}</div>
          <div id="fb-main-content" class="fb-page">{$rendered_page}</div>
        </div>
        <script type="text/javascript" src="static/dist/js/app.js"></script>
      </body>;
  }
}
