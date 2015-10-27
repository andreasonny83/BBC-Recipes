/**
 *
 * BBC recipes
 *
 */
;(function() {


  /**
   * Definition of the main app module and its dependencies
   */
  angular
    .module('bbc_recipes', [
      'ngRoute',
      'ngCookies',
      'ngAnimate'
    ])
    .config( config )
    .run( run );

  // safe dependency injection
  // this prevents minification issues
  config.$inject = ['$routeProvider', '$locationProvider', '$httpProvider', '$compileProvider'];

  /**
   * App routing
   *
   * You can leave it here in the config section or take it out
   * into separate file
   *
   */
  function config( $routeProvider, $locationProvider, $httpProvider, $compileProvider ) {
    // routes
    $routeProvider
      .when('/home', {
        templateUrl: 'views/home.html',
        controller: 'HomeController',
        controllerAs: 'homeCtrl'
      })
      .when('/recipe/:id', {
        templateUrl: 'views/recipe.html',
        controller: 'RecipeController',
        controllerAs: 'recipeCtrl'
      })
      .otherwise({
        redirectTo: '/login',
        templateUrl: 'views/login.html',
        controller: 'LoginController',
        controllerAs: 'loginCtrl'
      });
  }


  /**
   * Intercept any request or response inside authInterceptor
   * or handle what should happend on 40x, 50x errors
   *
   */
  angular
    .module('bbc_recipes')
    .factory('authInterceptor', authInterceptor);

  authInterceptor.$inject = ['$rootScope', '$location'];

  function authInterceptor( $rootScope, $location ) {

    return {

      // intercept every request
      request: function(config) {
        config.headers = config.headers || {};
        return config;
      },

      // Catch 404 errors
      responseError: function(response) {
        if (response.status === 404) {
          $location.path('/');
          return $q.reject(response);
        } else {
          return $q.reject(response);
        }
      }
    };
  }

  /**
   * Run block
   */
  run.$inject = ['$rootScope', '$location', '$cookieStore', '$http'];

  function run( $rootScope, $location, $cookieStore, $http ) {
    // Uncomment when the Authorization script is completed
    // 
    // keep user logged in after page refresh
    // $rootScope.globals = $cookieStore.get( 'globals' ) || {};
    // console.log($rootScope);
    // if ( $rootScope.globals.currentUser ) {
    //   $http.defaults.headers.common['Authorization'] = 'Basic ' + $rootScope.globals.currentUser.authdata; // jshint ignore:line
    // }
    // $rootScope.$on( '$locationChangeStart', function ( event, next, current ) {
    //   // redirect to login page if not logged in and trying to access a restricted page
    //   var restrictedPage = $.inArray($location.path(), ['/login', '/register']) === -1;
    //   var loggedIn = $rootScope.globals.currentUser;
    //   if (restrictedPage && !loggedIn) {
    //     $location.path( '/login' );
    //   }
    // });
  }

})();
