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

      $score = await ScoreLog::genPreviousScore(
        $level->getId(),
        $my_team->getId(),
        false,
      );

      $category = await Category::genSingleCategory($level->getCategoryId());
      $points = $level -> getPoints();
      $hint_cost = $level->getPenalty();
      if ($level->getHint() !== '') {
        // There is hint, can this team afford it?
        if ($level->getPenalty() > $my_team->getPoints()) { // Not enough points
          $hint_cost = -2;
          $hint = 'no';
        } else {
          $hint = await HintLog::genPreviousHint( //check for a previous hint
            $level->getId(),
            $my_team->getId(),
            false,
          );
          // Has this team requested this hint before?

          if ($hint) {
            $points -= $hint_cost;
            $hint_cost = 0;
          }
          // Has this team scored this level before?
          if ($score) {
            $hint_cost = 0;
          }
          $hint = ($hint_cost === 0) ? $level->getHint() : 'yes';
        }
      } else { // No hints
        $hint_cost = -1;
        $hint = 'no';
      }

      //Handle the wrong answer penalties
      $all_failures = await FailureLog::genAllFailures();
      $failures_cost = 0;
      $wrong_answer_penalty = $level->getWrongAnswerPenalty();
      $numIncorrectGuesses = 0;
      foreach($all_failures as $failure){
        if($level->getId() === $failure->getLevelId() and $my_team->getId() === $failure->getTeamId()){
          $failures_cost += $wrong_answer_penalty;
          $numIncorrectGuesses += 1;
        }
      }
      $points -= $failures_cost;
      $points = max($points,0);

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

      //All possible Answer choices for this question
      $choiceA = "";
      $choiceB = "";
      $choiceC = "";
      $choiceD = "";
      if($level->getIsShortAnswer()){
        $choiceA = "Short Answer";
        $choiceB = "Short Answer";
        $choiceC = "Short Answer";
        $choiceD = "Short Answer";
      }
      else{
        //$random = mt_rand(0,3);
        $choiceA = $level->getAnswerChoice1();
        $choiceB = $level->getAnswerChoice2();
        $choiceC = $level->getAnswerChoice3();
        $choiceD = $level->getAnswerChoice4();
      }
      //Handle if scored, ignore points calculated above and just set to value scored.
      //The reason for this is to get the bonus that is calculated when scored.
      if ($score) {
        $team_score = await ScoreLog::genLevelScoreByTeam(
          $my_team->getId(),
          $level->getId(),
        );
        $points = $team_score->getPoints();
      }

      //randomize order
      $choices = array($choiceA,$choiceB,$choiceC,$choiceD);
      shuffle($choices);

      $country_data = (object) array(
        'level_id' => $level->getId(),
        'title' => $level->getTitle(),
        'intro' => $level->getDescription(),
        'type' => $level->getType(),
        'points' => $points,
        'bonus' => $level->getBonus(),
        'category' => $category->getCategory(),
        'owner' => $owner,
        'completed' => $completed_by,
        'hint' => $hint,
        'hint_cost' => $hint_cost,
        'attachments' => $attachments_list,
        'links' => $links_list,
        'wrong_answer_penalty' => $wrong_answer_penalty,
        'numIncorrectGuesses' => $numIncorrectGuesses,
        'isShortAnswer' => $level->getIsShortAnswer(),
        'shuffledChoiceA' => $choices[0],
        'shuffledChoiceB' => $choices[1],
        'shuffledChoiceC' => $choices[2],
        'shuffledChoiceD' => $choices[3],
        'choiceA' => $choiceA,
        'choiceB' => $choiceB,
        'choiceC' => $choiceC,
        'choiceD' => $choiceD,
        'scored' => $score,
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
