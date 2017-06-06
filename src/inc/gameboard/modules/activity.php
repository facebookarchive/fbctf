<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class ActivityModuleController {
  public async function genRender(): Awaitable<:xhp> {
    await tr_start();
    $activity_ul = <ul class="activity-stream"></ul>;

    $all_activity = await ActivityLog::genAllActivity();
    $config = await Configuration::gen('language');
    $language = $config->getValue();
    foreach ($all_activity as $activity) {
      $subject = $activity->getSubject();
      $entity = $activity->getEntity();
      $ts = $activity->getTs();
      if (($subject !== '') && ($entity !== '')) {
        $class_li = '';
        $class_span = '';
        list($subject_type, $subject_id) =
          explode(':', $activity->getSubject());
        list($entity_type, $entity_id) = explode(':', $activity->getEntity());
        if ($subject_type === 'Team') {
          if (intval($subject_id) === SessionUtils::sessionTeam()) {
            $class_li = 'your-team';
            $class_span = 'your-name';
          } else {
            $class_li = 'opponent-team';
            $class_span = 'opponent-name';
          }
        }
        if ($entity_type === 'Country') {
          $formatted_entity = locale_get_display_region(
            '-'.$activity->getFormattedEntity(),
            $language,
          );
        } else {
          $formatted_entity = $activity->getFormattedEntity();
        }
        $activity_ul->appendChild(
          <li class={$class_li}>
            [ {time_ago($ts)} ]
            <span class={$class_span}>
              {$activity->getFormattedSubject()}
            </span>&nbsp;{tr($activity->getAction())}&nbsp;
            {$formatted_entity}
          </li>
        );
      } else {
        $activity_ul->appendChild(
          <li class={'opponent-team'}>
            [ {time_ago($ts)} ]
            <span class={'opponent-name'}>
              {$activity->getFormattedMessage()}
            </span>
          </li>
        );
      }
    }

    return
      <div>
        <header class="module-header">
          <h6>{tr('Activity')}</h6>
        </header>
        <div class="module-content">
          <div class="fb-section-border">
            <div class="module-scrollable">
              {$activity_ul}
            </div>
          </div>
        </div>
      </div>;
  }
}

$activity_generated = new ActivityModuleController();
echo \HH\Asio\join($activity_generated->genRender());
