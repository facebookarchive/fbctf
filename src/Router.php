<?hh // strict

class Router {
  public static async function genRoute(): Awaitable<string> {
    await tr_start();
    $page = idx(Utils::getGET(), 'p');
    if (!is_string($page)) {
      $page = 'index';
    }
    $ajax = Utils::getGET()->get('ajax') === 'true';
    $modal = Utils::getGET()->get('modal');

    if ($ajax) {
      return await self::genRouteAjax($page);
    } else if ($modal !== null) {
      $xhp = await self::genRouteModel($page, strval($modal));
      return strval($xhp);
    } else {
      $response = await self::genRouteNormal($page);
      return strval($response);
    }
  }

  private static async function genRouteModel(
    string $page,
    string $modal,
  ): Awaitable<:xhp> {
    switch ($page) {
      case 'action':
        return await (new ActionModalController())->genRender($modal);
      case 'tutorial':
        return await (new TutorialModalController())->genRender($modal);
      case 'country':
        return await (new CountryModalController())->genRender($modal);
      case 'scoreboard':
        return await (new ScoreboardModalController())->genRender($modal);
      case 'team':
        return await (new TeamModalController())->genRender($modal);
      case 'command-line':
        return await (new CommandLineModalController())->genRender($modal);
      case 'choose-logo':
        return await (new ChooseLogoModalController())->genRender($modal);
      default:
        throw new NotFoundRedirectException();
    }
  }

  private static async function genRouteAjax(
    string $page,
  ): Awaitable<string> {
    SessionUtils::sessionStart();
    switch ($page) {
      case 'index':
        return await (new IndexAjaxController())->genHandleRequest();
      case 'admin':
        SessionUtils::enforceLogin();
        SessionUtils::enforceAdmin();
        return await (new AdminAjaxController())->genHandleRequest();
      case 'game':
        SessionUtils::enforceLogin();
        return await (new GameAjaxController())->genHandleRequest();
      default:
        throw new NotFoundRedirectException();
    }
  }

  private static async function genRouteNormal(string $page): Awaitable<:xhp> {
    SessionUtils::sessionStart();
    switch ($page) {
      case 'admin':
        SessionUtils::enforceLogin();
        SessionUtils::enforceAdmin();
        return await (new AdminController())->genRender();
      case 'index':
        return await (new IndexController())->genRender();
      case 'game':
        SessionUtils::enforceLogin();
        return await (new GameboardController())->genRender();
      case 'view':
        return await (new ViewModeController())->genRender();
      case 'logout':
        // TODO: Make a confirmation modal?
        SessionUtils::sessionLogout();
        invariant(false, 'should not reach here');
      default:
        throw new NotFoundRedirectException();
    }
  }
}
