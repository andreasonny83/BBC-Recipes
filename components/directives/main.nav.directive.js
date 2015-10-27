;(function() {

  'use strict';

  angular
    .module('bbc_recipes')
    .directive('mainNav', tinMainNav);

  function tinMainNav() {

    // Definition of directive
    var directiveDefinitionObject = {
      restrict: 'E',
      templateUrl: 'components/directives/main-nav.html',
      link: function(scope, elem, attrs, ctrl) {
        elem.on('click', function(e) {
          if($(e.target).hasClass('menu-item')) {
            $('.responsive-wrapper').slideToggle( 150, 'swing');
          }
        });
      }
    };

    return directiveDefinitionObject;
  }

})();
