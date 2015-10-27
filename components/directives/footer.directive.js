;(function() {
  'use strict';

  angular
    .module('bbc_recipes')
    .directive('footerSection', tinMainNav);

  function tinMainNav() {

    // Definition of directive
    var directiveDefinitionObject = {
      restrict: 'E',
      templateUrl: 'components/directives/footer.html'
    };

    return directiveDefinitionObject;
  }

})();
