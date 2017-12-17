<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class ListviewController extends ModuleController {
  public async function genRender(): Awaitable<:xhp> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    await tr_start();
    $listview_div = <div class="listview-container"></div>;
    $listview_table = <table></table>;

    $active_levels = await Level::genAllActiveLevels();
    foreach ($active_levels as $level) {
      list($country, $category, $previous_score) = await \HH\Asio\va(
        Country::gen(intval($level->getEntityId())),
        Category::genSingleCategory($level->getCategoryId()),
        ScoreLog::genAllPreviousScore(
          $level->getId(),
          SessionUtils::sessionTeam(),
          false,
        ),
      ); // TODO: Combine Awaits
      if ($previous_score) {
        $span_status =
          <span class="fb-status status--yours">{tr('Captured')}</span>;
      } else {
        $span_status =
          <span class="fb-status status--open">{tr('Open')}</span>;
      }
      $listview_table->appendChild(
        <tr data-country={$country->getName()}>
          <td style="width: 38%;">
            {$country->getName()} ({$level->getTitle()})
          </td>
          <td style="width: 10%;">{strval($level->getPoints())}</td>
          <td style="width: 22%;">{$category->getCategory()}</td>
          <td style="width: 30%;">{$span_status}</td>
        </tr>
      );
    }
    $listview_div->appendChild($listview_table);

    return $listview_div;
  }
}

/* HH_IGNORE_ERROR[1002] */
$listview_generated = new ListviewController();
$listview_generated->sendRender();
