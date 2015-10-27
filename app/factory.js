;(function() {
  'use strict';

  angular
    .module('bbc_recipes')
    .factory('AuthenticationService', AuthenticationService);

  AuthenticationService.$inject = ['$http', '$cookieStore', '$rootScope'];


  function AuthenticationService($http, $cookieStore, $rootScope) {

    var service = {};

    service.Login = Login;
    // service.SetCredentials = SetCredentials;
    // service.ClearCredentials = ClearCredentials;

    return service;

    function Login(email, password, callback) {
      var data = $.param({
        email: email,
        password: password
      });

      $http({
        method: 'POST',
        url: 'api/login',
        data: data,
        headers: { "Content-type": "application/x-www-form-urlencoded; charset=utf-8" }
      }).
      then( function( response ) {
        response = { success: true };
        callback(response);
      }, function( response ) {
        response = { success: false };
        callback(response);
      });
    }

    function Register(name, email, reg_email_confirm, password, password_confirm) {
      var data = $.param({
        name: name,
        email: email,
        email_confirm: email_confirm,
        password: password,
        password_confirm: password_confirm
      });

      $http({
        method: 'POST',
        url: 'api/register',
        data: data,
        headers: { "Content-type": "application/x-www-form-urlencoded; charset=utf-8" }
      }).
      then( function( response ) {
        response = { success: true };
      }, function( response ) {
        response = { success: false };
      });
    }

  }

})();
