/**
 * Main application controller
 *
 * You can use this controller for your whole app if it is small
 * or you can have separate controllers for each logical section
 *
 */
;(function() {

  angular
    .module( 'bbc_recipes' )
    .controller( 'MainController', MainController )
    .controller('LoginController', LoginController)
    .controller( 'HomeController', HomeController )
    .controller( 'RecipeController', RecipeController );

  function MainController( $scope ) {
    $scope.url = 'api/';
  }

  function LoginController($scope, $rootScope, $location, AuthenticationService) {
    // try to log in the user
    $scope.login = function() {
      AuthenticationService.Login($scope.loginData.email, $scope.loginData.password, function (response) {
        console.log(response);
        if (response.success) {
            $location.path('/home');
            $rootScope.$apply();
          } else {
            $location.path('/login');
            $rootScope.$apply();
          }
      });
    }

    $scope.register = function() {
      // try to register a new user
      AuthenticationService.Login($scope.regData.reg_name, $scope.regData.email, $scope.regData.reg_email_confirm, $scope.regData.reg_password, $scope.regData.reg_password_confirm, function (response) {
        if (response.success) {
            $location.path('/');
          } else {
          }
      });
    }

  }

  function HomeController( $scope, $http ) {
    // Get the full list of recipes from API
    $http({
      method: 'GET',
      url: $scope.$parent.url + 'recipes',
      headers: { "Content-type": "application/x-www-form-urlencoded; charset=utf-8" }
    }).
    then( function( response ) {
      console.log(response);
      $scope.status = response.status;
      $scope.recipes = response.data.recipes;
    }, function( response ) {
      $scope.recipes = response.data.recipes || "Request failed";
      $scope.status = response.status;
    });

  }

  function RecipeController( $scope, $routeParams, $http ) {
    // Get the specific recipe details from API
    var recipe_id = $routeParams.id;

    $http({
      method: 'GET',
      url: $scope.$parent.url + 'recipe/' + recipe_id,
      headers: { "Content-type": "application/x-www-form-urlencoded; charset=utf-8" }
    }).
    then( function( response ) {
      $scope.status = response.status;
      $scope.recipe = response.data.recipe;
      console.log(response.data);
    }, function( response ) {
      $scope.recipe = response.data.recipe || "Request failed";
      $scope.status = response.status;
    });
  }

})();
