<?hh // strict

class GameAjaxController extends AjaxController {
  <<__Override>>
  protected function getFilters(): array<string, mixed> {
    return array(
      'POST' => array(
        'level_id' => FILTER_VALIDATE_INT,
        'answer' => FILTER_UNSAFE_RAW,
        'csrf_token' => FILTER_UNSAFE_RAW,
        'action' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
        'page' => array(
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => array('regexp' => '/^[\w-]+$/'),
        ),
      ),
    );
  }

  <<__Override>>
  protected function getActions(): array<string> {
    return array('answer_level', 'get_hint', 'open_level');
  }

  <<__Override>>
  protected async function genHandleAction(
    string $action,
    array<string, mixed> $params,
  ): Awaitable<string> {
    if ($action !== 'none') {
      // CSRF check
      if (idx($params, 'csrf_token') !== SessionUtils::CSRFToken()) {
        return Utils::error_response('CSRF token is invalid', 'game');
      }
    }

    switch ($action) {
      case 'none':
        return Utils::error_response('Invalid action', 'game');
      case 'answer_level':
        $scoring = await Configuration::gen('scoring');
        if ($scoring->getValue() === '1') {
          $check_base =
            await Level::genCheckBase(must_have_int($params, 'level_id'));
          $check_answer = await Level::genCheckAnswer(
            must_have_int($params, 'level_id'),
            must_have_string($params, 'answer'),
          );
          // Check if level is not a base
          if ($check_base) {
            return Utils::error_response('Failed', 'game');
            // Check if answer is valid
          } else if ($check_answer) {
            // Give points!
            await Level::genScoreLevel(
              must_have_int($params, 'level_id'),
              SessionUtils::sessionTeam(),
            );
            // Update teams last score
            await Team::genLastScore(SessionUtils::sessionTeam());
            //Invalidate score cache
            MultiTeam::invalidateMCRecords('POINTS_BY_TYPE');
            MultiTeam::invalidateMCRecords('LEADERBOARD');
            MultiTeam::invalidateMCRecords('TEAMS_BY_LEVEL');
            MultiTeam::invalidateMCRecords('TEAMS_FIRST_CAP');
            return Utils::ok_response('Success', 'game');
          } else {
            await FailureLog::genLogFailedScore(
              must_have_int($params, 'level_id'),
              SessionUtils::sessionTeam(),
              must_have_string($params, 'answer'),
            );
            return Utils::error_response('Failed', 'game');
          }
        } else {
          return Utils::error_response('Failed', 'game');
        }
      case 'get_hint':
        $requested_hint = await Level::genLevelHint(
          must_have_int($params, 'level_id'),
          SessionUtils::sessionTeam(),
        );
        if ($requested_hint !== null) {
          return Utils::hint_response($requested_hint, 'OK');
        } else {
          return Utils::hint_response('', 'ERROR');
        }
      case 'open_level':
        return Utils::ok_response('Success', 'admin');
      default:
        return Utils::error_response('Invalid action', 'game');
    }
  }
}
