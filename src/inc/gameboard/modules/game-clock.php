<?hh // strict

require_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php');

/* HH_IGNORE_ERROR[1002] */
SessionUtils::sessionStart();

class ClockModuleController {
  private async function genGenerateIndicator(string $start_ts, string $end_ts): Awaitable<:xhp> {
    $seconds = intval($end_ts) - intval($start_ts);
    $s_each = intval($seconds/10);
    $now = time();
    $current_s = intval($now) - intval($start_ts);
    if ($s_each === 0) {
      $current = 0;
    } else {
      $current = intval($current_s/$s_each);
    }
    $indicator = <div class="indicator game-progress-indicator"></div>;
    $game = await Configuration::gen('game');
    if ($game->getValue() === '1') {
      $seconds = intval($end_ts) - intval($start_ts);
      $s_each = intval($seconds/10);
      $now = time();
      $current_s = intval($now) - intval($start_ts);
      $current = intval($current_s/$s_each);
      $indicator = <div class="indicator game-progress-indicator"></div>;
      for ($i=0; $i<10; $i++) {
        $indicator_classes = 'indicator-cell ';
        if ($current >= $i) {
          $indicator_classes .= 'active ';
        }
        $indicator->appendChild(
          <span class={$indicator_classes}></span>
        );
      }
    } else {
      for ($i=0; $i<10; $i++) {
        $indicator->appendChild(
          <span class="indicator-cell"></span>
        );
      }
    }
    return $indicator;
  }

  public async function genRender(): Awaitable<:xhp> {
    await tr_start();
    $timer  = await Configuration::gen('timer');
    $start_ts = await Configuration::gen('start_ts');
    $end_ts = await Configuration::gen('end_ts');
    $timer = $timer->getValue();
    $start_ts = $start_ts->getValue();
    $end_ts = $end_ts->getValue();

    $now = time();
    $init = intval($end_ts) - $now;

    if ($timer === '1') {
      $num_hours = intval(floor($init / 3600));
      $hours_int = ($num_hours >= 0) ? $num_hours : 0;
      $hours = sprintf("%02d", $hours_int);

      $num_minutes = intval(intval($init / 60) % 60);
      $minutes_int = ($num_minutes >= 0) ? $num_minutes : 0;
      $minutes = sprintf("%02d", $minutes_int);

      $num_seconds = intval($init % 60);
      $seconds_int = ($num_seconds >= 0) ? $num_seconds : 0;
      $seconds = sprintf("%02d", $seconds_int);

      if ($init > 0) {
        $milli_int = rand(0, 99);
      } else {
        $milli_int = 0;
      }
      $milliseconds = sprintf("%02d", $milli_int);
    } else {
      $hours = '--';
      $minutes = '--';
      $seconds = '--';
      $milliseconds = '--';
    }

    $indicator = await $this->genGenerateIndicator($start_ts, $end_ts);
    return
      <div>
        <header class="module-header">
          <h6>{tr('Game Clock')}</h6>
        </header>
        <div class="module-content module-scrollable">
          <div class="game-clock fb-numbers">
            <span class="clock-hours">{$hours}</span>:<span class="clock-minutes">{$minutes}</span>:<span class="clock-seconds">{$seconds}</span>:<span class="clock-milliseconds">{$milliseconds}</span>
          </div>
          <div class="game-progress fb-progress-bar">
            <span class="label label--left">[{tr('Start')}]</span>
            <span class="label label--right">[{tr('End')}]</span>
            {$indicator}
          </div>
        </div>
      </div>;
  }
}

$clock_generated = new ClockModuleController();
echo \HH\Asio\join($clock_generated->genRender());
