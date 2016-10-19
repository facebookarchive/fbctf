var $ = require('jquery');

function isWidgetSet(widgetName) {
  return document.cookie.search(widgetName) >= 0;
}

module.exports = {
  setWidgetStatus: function(widgetName, widgetValue) {
    var d = new Date();
    // Expiration is 24 hours
    d.setTime(d.getTime() + (24 * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = widgetName + "=" + widgetValue + "; " + expires;
  },

  setAllWidgetStatus: function(widgetValue) {
    var widgets = ['Leaderboard', 'Announcements', 'Activity', 'Teams', 'Filter', 'Game Clock'];
    for (var i = 0; i < widgets.length; i++) {
      this.setWidgetStatus(widgets[i], widgetValue);
    }
  },

  getWidgetStatus: function(widgetName) {
    var name = widgetName + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) === 0) return c.substring(name.length, c.length);
    }
    return '';
  },

  rememberWidgets: function(widgets) {
    for (var i = 0; i < widgets.length; i++) {
      if (isWidgetSet(widgets[i])) {
        if (this.getWidgetStatus(widgets[i]) === 'open') {
          $('aside[data-name="' + widgets[i] + '"]').addClass('active');
        } else {
          $('aside[data-name="' + widgets[i] + '"]').removeClass('active');
        }
      } else {
        this.setWidgetStatus(widgets[i], 'close');
      }
    }
  }
};
