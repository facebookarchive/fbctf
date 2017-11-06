<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class AnnouncementsModuleController extends ModuleController {
  public async function genRender(): Awaitable<:xhp> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    await tr_start();
    $announcements = await Announcement::genAllAnnouncements();
    $announcements_ul = <ul class="activity-stream announcements-list"></ul>;
    if ($announcements) {
      foreach ($announcements as $announcement) {
        $announcements_ul->appendChild(
          <li>
            [ {time_ago($announcement->getTs())} ]
            <span class="announcement-highlight">
              {$announcement->getAnnouncement()}
            </span>
          </li>
        );
      }
    }

    return
      <div>
        <header class="module-header">
          <h6>{tr('Announcements')}</h6>
        </header>
        <div class="module-content">
          <div class="fb-section-border">
            <div class="module-top"></div>
            <div class="module-scrollable">
              {$announcements_ul}
            </div>
          </div>
        </div>
      </div>;
  }
}

/* HH_IGNORE_ERROR[1002] */
$announcements_generated = new AnnouncementsModuleController();
$announcements_generated->sendRender();
