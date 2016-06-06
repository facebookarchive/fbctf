// @flow

var $ = require('jquery');

function formatNumber(value) {
  return (parseInt(value) > 9) ? value : '0' + value;
}

function noClock() {
  $('aside[data-module="game-clock"] .clock-milliseconds').text('--');
  $('aside[data-module="game-clock"] .clock-seconds').text('--');
  $('aside[data-module="game-clock"] .clock-minutes').text('--');
  $('aside[data-module="game-clock"] .clock-hours').text('--');
  $('aside[data-module="game-clock"] .clock-days').text('--');
}

function setMilliseconds(value) {
  var formatted = formatNumber(value);
  $('aside[data-module="game-clock"] .clock-milliseconds').text(formatted);
}

function setSeconds(value) {
  var formatted = formatNumber(value);
  $('aside[data-module="game-clock"] .clock-seconds').text(formatted);
}

function setMinutes(value) {
  var formatted = formatNumber(value);
  $('aside[data-module="game-clock"] .clock-minutes').text(formatted);
}

function setHours(value) {
  var formatted = formatNumber(value);
  $('aside[data-module="game-clock"] .clock-hours').text(formatted);
}

function setDays(value) {
  var formatted = formatNumber(value);
  $('aside[data-module="game-clock"] .clock-days').text(formatted);
}

function getMilli() {
  return $('aside[data-module="game-clock"] .clock-milliseconds').text();
}

function getSeconds() {
  return $('aside[data-module="game-clock"] .clock-seconds').text();
}

function getMinutes() {
  return $('aside[data-module="game-clock"] .clock-minutes').text();
}

function getHours() {
  return $('aside[data-module="game-clock"] .clock-hours').text();
}

function getDays() {
  return $('aside[data-module="game-clock"] .clock-days').text();
}

module.exports = {
  isRunning: false,
  isStopped: function() {
    return getMilli() === '--' &&
      getSeconds() === '--' &&
      getMinutes() === '--' &&
      getHours() === '--' &&
      getDays() === '--';
  },
  isFinished: function() {
    return parseInt(getMilli()) === 0 &&
      parseInt(getSeconds()) === 0 &&
      parseInt(getMinutes()) === 0 &&
      parseInt(getHours()) === 0 &&
      parseInt(getDays()) === 0;
  },
  runClock: function() {
    if (this.isStopped() || this.isFinished()) {
      this.isRunning = false;
      noClock();
      return;
    }

    this.isRunning = true;
    var milli = getMilli();
    var new_milli = parseInt(milli) - 1;

    if (new_milli < 0) {
      var seconds = getSeconds();
      if (parseInt(seconds) > 0) {
        setMilliseconds('99');
      } else {
        setMilliseconds('0');
      }
      var new_seconds = parseInt(seconds) - 1;
      if (new_seconds < 0) {
        var minutes = getMinutes();
        if (parseInt(minutes) > 0) {
          setSeconds('59');
          setMilliseconds('99');
        } else {
          setSeconds('0');
        }
        var new_minutes = parseInt(minutes) - 1;
        if (new_minutes < 0) {
          var hours = getHours();
          if (parseInt(hours) > 0) {
            setMinutes('59');
            setSeconds('59');
            setMilliseconds('99');
          } else {
            setMinutes('0');
          }
          var new_hours = parseInt(hours) - 1;
          if (new_hours < 0) {
            var days = getDays();
            if (parseInt(days) > 0) {
              setHours('23');
              setMinutes('59');
              setSeconds('59');
              setMilliseconds('99');
            } else {
              setHours('0');
            }
            var new_days = parseInt(days) - 1;
            if (new_days <= 0) {
              setDays('0');
            } else {
              setDays(new_days);
            }
          } else {
            setHours(new_hours);
          }
        } else {
          setMinutes(new_minutes);
        }
      } else {
        setSeconds(new_seconds);
      }
    } else {
      setMilliseconds(new_milli);
    }

    // recurse after 10 ms
    setTimeout(this.runClock.bind(this), 10);
  }
};
