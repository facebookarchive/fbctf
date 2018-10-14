var $ = require('jquery');
var d3 = require('d3');

var COLOR_LIGHT_BLUE = "#cff8fa";

module.exports = {
  data: {},

  // Set up event listeners
  init: function(data) {
    this.data = data;
    var self = this;

    $('.fb-graphic').each(function() {
      var $graphic = $(this),
          datafile = $graphic.data('file');

      if ($graphic.hasClass('initialized')) {
        return;
      }
      $graphic.addClass('initialized');
      self.build(this, datafile);
    });

    // Scoreboard filter
    $('input[name="fb-scoreboard-filter"]').on('change', function(event) {
      event.preventDefault();
      var team = $(this).val(),
          $teamLine = $('.scoreboard-graphic-container .team-score-line[data-team="' + team + '"]');
      if (this.checked) {
        $teamLine.show();
      } else {
        $teamLine.hide();
      }
    });
  },

  /**
   * build the graph
   *
   * @param svgEl (object)
   *   - the svg element to attach the graphic to
   *
   * @param datafile (string)
   *   - the file to load, which contains the data
   */
  build: function(svgEl, datafile) {
    var self = this;

    if (datafile === undefined) {
      return;
    }
    var $container = $(svgEl).closest('.scoreboard-graphic-container');

    $.get(datafile, function(data) {
      var scores = data;
      var maxScore = 0;
      $.each(scores, function() {
        $.each(this.values, function() {
          if (parseInt(this.score) > maxScore) {
            maxScore = parseInt(this.score);
          }
        });
      });
      var maxYaxis = maxScore + 30;

      var graphic = d3.select(svgEl),
          MARGIN = {
            left: 60,
            right: 20,
            bottom: 40
          },
          WIDTH = $container.length > 0 ? $container.width() - MARGIN.left - MARGIN.right : 820 - MARGIN.left - MARGIN.right,
          HEIGHT = 220 - MARGIN.bottom,

          X_START = 1,
          X_LENGTH = self.data.CONF.progressiveCount,
          xRange = d3.scale.linear().range([0, WIDTH]).domain([X_START, X_LENGTH]),
          /*yRange   = d3.scale.linear().range([HEIGHT, 0]).domain([d3.min(minMaxArray, function (d) {
           return d.score;
           }), d3.max(minMaxArray, function (d) {
           return d.score + 30;
           }) ]),*/
          yRange = d3.scale.linear().range([HEIGHT, 0]).domain([0, maxYaxis]),

          xAxis = d3.svg.axis().tickFormat("").scale(xRange).ticks(X_LENGTH),

          yAxis = d3.svg.axis().scale(yRange).ticks(6).orient("left");

      graphic.append("svg:g").attr("class", "x axis").attr("transform", "translate(" + MARGIN.left + "," + HEIGHT + ")").call(xAxis)
        .selectAll('line').attr('transform', 'translate(0, -6)');

      // Add the text label for the X axis
      graphic.append("text")
        .attr("transform", "rotate(0)")
        .attr("y", HEIGHT + MARGIN.right)
        .attr("x", (WIDTH + MARGIN.left / 2))
        .attr("dy", "1em")
        .attr("stroke", "#fff")
        .style("text-anchor", "middle")
        .text("Time");

      graphic.append("svg:g").attr("class", "y axis").attr("transform", "translate(" + MARGIN.left + ",0)").call(yAxis)
        .selectAll('line').attr('transform', 'translate(6,0)');

      // Add the text label for the Y axis
      graphic.append("text")
        .attr("transform", "rotate(-90)")
        .attr("y", 0)
        .attr("x", 0 - (HEIGHT / 2))
        .attr("dy", "1em")
        .attr("stroke", "#fff")
        .style("text-anchor", "middle")
        .text("Score");

      var lineFunc = d3.svg.line().x(function(d) {
        return xRange(d.time) + MARGIN.left;
      }).y(function(d) {
        return yRange(d.score);
      }).interpolate('linear');

      graphic.append('svg:rect')
        .attr('width', WIDTH)
        .attr('height', HEIGHT)
        .attr('x', MARGIN.left)
        .attr('fill', '#142e35');

      var graphLine = graphic.append('svg:g').attr('class', 'mouseline').attr('opacity', "0");

      graphLine.append('svg:path')
        .attr('stroke', COLOR_LIGHT_BLUE)
        .attr('stroke-width', 2)
        .attr('d', "M0,0L0," + HEIGHT);

      graphLine.append('circle')
        .attr('cx', 0)
        .attr('cy', 5)
        .attr('r', 5)
        .attr('stroke', COLOR_LIGHT_BLUE)
        .attr('fill', 'black')
        .attr('stroke-width', 2);

      scores.forEach(function(d) {
        graphic.append('svg:path')
          .attr('d', lineFunc(d.values))
          .attr('class', 'team-score-line')
          .attr('stroke', d.color)
          .attr('data-team', d.team)
          .attr('stroke-width', 2)
          .attr('fill', 'none');
      });

      graphic.append('svg:rect')
        .attr('width', WIDTH)
        .attr('height', HEIGHT)
        .attr('x', MARGIN.left)
        .attr('fill', 'none')
        .attr('pointer-events', 'all')
        .on('mouseout', function() {
          d3.select(".mouseline").attr("opacity", "0");
        })
        .on('mouseover', function() {
          d3.select(".mouseline").attr("opacity", "1");
        })
        .on('mousemove', function() {
          var xCoor = d3.mouse(this)[0];
          d3.select('.mouseline').attr('transform', 'translate(' + xCoor + ',0)');
        });
    }, 'json').fail(function(jqxhr, status, error) {
      console.error("There was a problem retrieving the game scores.");
      console.log(status);
      console.log(error);
      console.error("/error");
    });
  }
};
