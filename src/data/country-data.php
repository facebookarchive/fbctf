<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class CountryDataController extends DataController {
  public async function genGenerateData(): Awaitable<void> {
    $my_team = await MultiTeam::genTeam(SessionUtils::sessionTeam());

    $countries_data = (object) array();

    // If gameboard refresing is disabled, exit
    $gameboard = await Configuration::gen('gameboard');
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($countries_data);
      exit(1);
    }

    $all_active_levels = await Level::genAllActiveLevels();
    foreach ($all_active_levels as $level) {
      $country = await Country::gen(intval($level->getEntityId()));
      if (!$country) {
        continue;
      }

      $category = await Category::genSingleCategory($level->getCategoryId());
      if ($level->getHint() !== '') {
        // There is hint, can this team afford it?
        if ($level->getPenalty() > $my_team->getPoints()) { // Not enough points
          $hint_cost = -2;
          $hint = 'no';
        } else {
          $hint = await HintLog::genPreviousHint(
            $level->getId(),
            $my_team->getId(),
            false,
          );
          $score = await ScoreLog::genPreviousScore(
            $level->getId(),
            $my_team->getId(),
            false,
          );
          // Has this team requested this hint or scored this level before?
          if ($hint || $score) {
            $hint_cost = 0;
          } else {
            $hint_cost = $level->getPenalty();
          }
          $hint = ($hint_cost === 0) ? $level->getHint() : 'yes';
        }
      } else { // No hints
        $hint_cost = -1;
        $hint = 'no';
      }

      // All attachments for this level
      $attachments_list = array();
      $has_attachments = await Attachment::genHasAttachments($level->getId());
      if ($has_attachments) {
        $all_attachments =
          await Attachment::genAllAttachments($level->getId());
        foreach ($all_attachments as $attachment) {
          array_push($attachments_list, $attachment->getFilename());
        }
      }

      // All links for this level
      $links_list = array();
      $has_links = await Link::genHasLinks($level->getId());
      if ($has_links) {
        $all_links = await Link::genAllLinks($level->getId());
        foreach ($all_links as $link) {
          array_push($links_list, $link->getLink());
        }
      }

      // All teams that have completed this level
      $completed_by = array();
      $completed_level = await MultiTeam::genCompletedLevel($level->getId());
      foreach ($completed_level as $c) {
        array_push($completed_by, $c->getName());
      }

      // Who is the first owner of this level
      if ($completed_level) {
        $owner = await MultiTeam::genFirstCapture($level->getId());
        $owner = $owner->getName();
      } else {
        $owner = 'Uncaptured';
      }
      $country_data = (object) array(
        'level_id' => $level->getId(),
        'title' => $level->getTitle(),
        'intro' => $level->getDescription(),
        'type' => $level->getType(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonus(),
        'category' => $category->getCategory(),
        'owner' => $owner,
        'completed' => $completed_by,
        'hint' => $hint,
        'hint_cost' => $hint_cost,
        'attachments' => $attachments_list,
        'links' => $links_list,
      );
      /* HH_FIXME[1002] */
      /* HH_FIXME[2011] */
      $countries_data->{$country->getName()} = $country_data;
    }

    $this->jsonSend($countries_data);
  }
}

$countryData = new CountryDataController();
\HH\Asio\join($countryData->genGenerateData());
