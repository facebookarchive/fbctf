var $ = require('jquery');

function isFilterSet(filterName) {
  return document.cookie.search(filterName) >= 0;
}

module.exports = {
  getFilterName: function(filterSelector, filters) {
    for (var key in filters) {
      if (filters[key] === filterSelector) {
        return key;
      }
    }
  },

  setFilterState: function(filterName, filterValue) {
    var d = new Date();
    // Persist with expiration 24 hours
    d.setTime(d.getTime() + (24 * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = filterName + "=" + filterValue + "; " + expires;
  },

  getFilterState: function(filterName) {
    var name = filterName + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == ' ') c = c.substring(1);
      if (c.indexOf(name) === 0) return c.substring(name.length, c.length);
    }
    return '';
  },

  rememberFilters: function(filters) {
    for (var key in filters) {
      if (isFilterSet(key)) {
        var ftype;
        if (this.getFilterState(key) === 'on') {
          if (key.search('Filter-Main') === 0) {
            ftype = key.split('-')[2];
            $('#' + filters[key]).prop("checked", true).trigger("click");
            $('div[data-tab="' + ftype + '"]').addClass('active');
          } else {
            $('#' + filters[key]).prop("checked", true).trigger("click");
          }
        } else { // Cookie for this filter is not 'on'
          if (key.search('Filter-Main') === 0) {
            ftype = key.split('-')[2];
            $('div[data-tab="' + ftype + '"]').removeClass('active');
          }
        }
      } else { // No previous value in the cookie for this filter
        if ($('#' + filters[key]).is(':checked')) {
          this.setFilterState(key, 'on');  
        } else {
          this.setFilterState(key, 'off');
        }
      }
    }
  },

  resetMainFilters: function() {
    this.resetFilters(true, false);
  },

  resetNotMainFilters: function() {
    this.resetFilters(false, true);
  },

  resetAllFilters: function() {
    this.resetFilters(true, true);
  },

  resetFilters: function(mainOnly, secondOnly) {
    var mainFilters = [
      'Filter-Main-category',
      'Filter-Main-status'
    ];
    var filters = this.detectFilters();
    for (var key in filters) {
      if (mainOnly) {
        if (mainFilters.indexOf(key) > -1) {
          this.setFilterState(key, 'off');
        }
      }
      if (secondOnly) {
        if (mainFilters.indexOf(key) < 0) {
          this.setFilterState(key, 'off');
        } 
      }
    }
  },

  detectFilters: function() {
    var filterList = [
      'Filter-category',
      'Filter-status'
    ];
    var filterSelector = 'fb--module--filter--';
    var newFilterList = {
      'Filter-Main-category' : 'fb--module--filter--category',
      'Filter-Main-status' : 'fb--module--filter--status'
    };
    var filterType;
    for (var i = 0; i < filterList.length; i++) {
      filterType = filterList[i].split('-')[1];
      $('div[data-tab="' + filterType + '"] input').each(function() {
        var selec = filterSelector + filterType + '--' + $(this).val().toLowerCase();
        newFilterList[filterList[i] + '-' + $(this).val()] = selec;
      });
    }
    return newFilterList;
  }
};
