// @flow

var $ = require('jquery');

function noCountdown(container) {
  $('.countdown-days', container).text('--');
  $('.countdown-hours', container).text('--');
  $('.countdown-minutes', container).text('--');
  $('.countdown-seconds', container).text('--');
}

module.exports = {
  _container: null,
  _remaining: 0,
  _timeDiff: 0,
  _interval: null,
  
  startCountdown: function() {
    this._container = $('.upcoming-game-countdown');
    if( !this._container.length ) {
      return;
    }
    
    this._remaining = parseInt(this._container.data().remaining);
    
    if(!this._container.length || !this._remaining) {
      return noCountdown(this._container);
    }

    var self = this;
    this._interval = setInterval(
      function() {
        self.setTimeRemaining();
      },
      1000
    );
  },
  
  setTimeRemaining: function setTimeRemaining() {
    this._remaining -= 1;
    var secs = this._remaining;

    if( secs < 0 ) {
      noCountdown(this._container);
      if(this._interval) {
        clearInterval(this._interval);
      }
      return;
    }

    var days = parseInt((secs/(60*60*24)) % 24);
    var hours = parseInt((secs/(60*60)) % 24);
    var minutes = parseInt((secs/60) % 60);
    var seconds = parseInt(secs % 60);
    
    $('.countdown-days', this._container).text(days);
    $('.countdown-hours', this._container).text(hours);
    $('.countdown-minutes', this._container).text(minutes);
    $('.countdown-seconds', this._container).text(seconds);
  }
};