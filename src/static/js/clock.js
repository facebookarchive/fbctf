// @flow

var $ = require('jquery');

var lastValues = {
  seconds: -1,
  minutes: -1,
  hours: -1,
  days: -1
};

function formatNumber(value) {
  return (value > 9) ? value : '0' + value;
}

function noClock() {
  $('aside[data-module="game-clock"] .clock-milliseconds').text('--');
  $('aside[data-module="game-clock"] .clock-seconds').text('--');
  $('aside[data-module="game-clock"] .clock-minutes').text('--');
  $('aside[data-module="game-clock"] .clock-hours').text('--');
  $('aside[data-module="game-clock"] .clock-days').text('--');
}

function setMilliseconds(value) {
  var formatted = value >= 100 ? value : '0' + (value >= 10 ? value : '0' + value);
  $('aside[data-module="game-clock"] .clock-milliseconds').text(formatted);
}

function setSeconds(value) {
  if(value !== lastValues.seconds) {
    lastValues.seconds = value;

    var formatted = formatNumber(value);
    $('aside[data-module="game-clock"] .clock-seconds').text(formatted);
  }
}

function setMinutes(value) {
  if(value !== lastValues.minutes) {
    lastValues.minutes = value;

    var formatted = formatNumber(value);
    $('aside[data-module="game-clock"] .clock-minutes').text(formatted);
  }
}

function setHours(value) {
  if(value !== lastValues.hours) {
    lastValues.hours = value;

    var formatted = formatNumber(value);
    $('aside[data-module="game-clock"] .clock-hours').text(formatted);
  }
}

function setDays(value) {
  if(value !== lastValues.days) {
    lastValues.days = value;

    var formatted = formatNumber(value);
    $('aside[data-module="game-clock"] .clock-days').text(formatted);
  }
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

function getRemaining() {
  return $('aside[data-module="game-clock"] .game-clock[data-remaining]').data().remaining;
}

module.exports = {
  isRunning: false,
  endTime: 0,
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
    var remaining = getRemaining();
    if (this.isStopped() || this.isFinished() || !remaining || remaining < 0) {
      this.isRunning = false;
      noClock();
      return;
    }

    this.isRunning = true;
    this.endTime = new Date().getTime() + getRemaining()*1000;
    this.tickDown();
  },
  stopClock: function() {
    noClock();
    this.isRunning = false;
  },
  tickDown: function() {
    var remaining = this.endTime - new Date().getTime();
    if(remaining <= 0) {
      return this.stopClock();
    }

    var milliseconds = remaining % 1000;
    var seconds = Math.floor((remaining / 1000) % 60);
    var minutes = Math.floor((remaining / (1000 * 60)) % 60);
    var hours = Math.floor((remaining / (1000 * 60 * 60)) % 24);
    var days = Math.floor(remaining / (1000 * 60 * 60 * 24));

    setMilliseconds(milliseconds);
    setSeconds(seconds);
    setMinutes(minutes);
    setHours(hours);
    setDays(days);

    // recurse after 10 ms
    setTimeout(this.tickDown.bind(this), 10);
  }
};
