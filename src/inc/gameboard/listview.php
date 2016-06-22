<?hh // strict

require_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class ListviewController {
  public async function genRender(): Awaitable<:xhp> {
    await tr_start();
    $listview_div = <div class="listview-container"></div>;
    $listview_table = <table></table>;

    $active_levels = await Level::genAllActiveLevels();
    foreach ($active_levels as $level) {
      $country = await Country::gen(intval($level->getId()));
      $category = await Category::genSingleCategory($level->getCategoryId());
      $previous_score = await ScoreLog::genPreviousScore($level->getId(), SessionUtils::sessionTeam(), false);
      if ($previous_score) {
        $span_status = <span class="fb-status status--yours">{tr('Captured')}</span>;
      } else {
        $span_status = <span class="fb-status status--open">{tr('Open')}</span>;
      }
      $listview_table->appendChild(
        <tr data-country={$country->getName()}>
          <td style="width: 38%;">{$country->getName()} ({$level->getTitle()})</td>
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

$listview_generated = new ListviewController();
echo \HH\Asio\join($listview_generated->genRender());
