// @flow

var $ = require('jquery');

function isFilterSet(filterName) {
  return document.cookie.search(filterName) >= 0;
}

module.exports = {
  /**
   * filter name given selector
   * 
   * @param filterSelector (string)
   *   - selector to find the filter name
   *
   * @param filters (Object)
   *   - Object to map filter name and filter selector
   * @return string
   *   - filter name
   */
  getFilterName: function(filterSelector: string, filters: Object): string {
    for (let key in filters) {
      if (filters[key] === filterSelector) {
        return key;
      }
    }
    return '';
  },

  /**
   * persists filter state in a cookie
   * 
   * @param filterName (string)
   *   - filter name to be stored as a cookie
   *
   * @param filterValue (string)
   *   - filter state (on/off)
   */
  setFilterState: function(filterName: string, filterValue: string) {
    let d = new Date();
    // Persist with expiration 24 hours
    d.setTime(d.getTime() + (24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = filterName + "=" + filterValue + "; " + expires;
  },

  /**
   * retrieve filter state from cookies
   * 
   * @param filterName (string)
   *   - filter name to retrieve
   * @return string
   *   - state of filter ('on'/'off')
   */
  getFilterState: function(filterName: string): string {
    let name = filterName + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) === 0) return c.substring(name.length, c.length);
    }
    return '';
  },

  /**
   * set filter state based on persistent cookie
   * 
   * @param filters (Object)
   *   - Object to map filter name and filter selector
   */
  rememberFilters: function(filters: Object) {
    for (let key in filters) {
      if (isFilterSet(key)) {
        let ftype;
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

  /**
   * persist main filters state to 'off'
   */
  resetMainFilters: function() {
    this.resetFilters(true, false);
  },

  /**
   * persist not main filters state to 'off'
   */
  resetNotMainFilters: function() {
    this.resetFilters(false, true);
  },

  /**
   * persist all filters state to 'off'
   */
  resetAllFilters: function() {
    this.resetFilters(true, true);
  },

  /**
   * persist given filters state to 'off'
   * 
   * @param mainOnly (boolean)
   *   - apply to main filters
   * @param secondOnly (boolean)
   *   - apply to not main filters
   */
  resetFilters: function(mainOnly: boolean, secondOnly: boolean) {
    let mainFilters = [
      'Filter-Main-category',
      'Filter-Main-status'
    ];
    let filters = this.detectFilters();
    for (let key in filters) {
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

  /**
   * retrieve current state of each filter
   * 
   * @return Object
   *   - Object with all filter names maped to their selectors
   */
  detectFilters: function(): Object {
    let filterList = [
      'Filter-category',
      'Filter-status'
    ];
    let filterSelector = 'fb--module--filter--';
    let newFilterList = {
      'Filter-Main-category' : 'fb--module--filter--category',
      'Filter-Main-status' : 'fb--module--filter--status'
    };
    let filterType;
    for (let i = 0; i < filterList.length; i++) {
      filterType = filterList[i].split('-')[1];
      $('div[data-tab="' + filterType + '"] input').each(function() {
        let selec = filterSelector + filterType + '--' + $(this).val().toLowerCase();
        newFilterList[filterList[i] + '-' + $(this).val()] = selec;
      });
    }
    return newFilterList;
  }
};
