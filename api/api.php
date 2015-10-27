<?php
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
use \Slim\Slim;
use \Slim\LogWriter;
use \Config\Database\DbHandler;
use \Config\SecureSessionHandler;

\Slim\Slim::registerAutoloader();

$_ENV['SLIM_MODE'] = APP_ENV;

$app = new \Slim\Slim();
$app->setName( 'BBC_API' );

// Only invoked if mode is "production"
$app->configureMode( 'production', function () use ( $app ) {
	$app->config( array(
		'log.enable' => true,
		'debug' => false
	));
	$handle = fopen( 'debug.log', 'w' );
	$app->log->setWriter( new \Slim\LogWriter( $handle ) );
});

// Only invoked if mode is "development"
$app->configureMode( 'development', function () use ( $app ) {
	$app->config( array(
		'log.enable' => false,
		'debug' => true
	));
});

/**
 * Perform an API get status
 * This is not much than a ping on the API server
 */
$app->get(
	'/status', function() {
		$response['status'] = 'ok';
		echoRespnse( 200, $response );
		exit;
	}
);

/**
 * Try to perform a user log in
 * If the user is authenticated, then start the session cookie
 */
$app->post(
	'/login', function() use ( $app ) {
		// check for required params
		verify_required_params( array( 'email', 'password' ) );

		// reading post params
		$email    = $app->request()->post( 'email' );
		$password = $app->request()->post( 'password' );
		$response = array(
			'request' => 'login'
		);

		// Sanitize data
		$email    = filter_var(  $email, FILTER_SANITIZE_EMAIL );
		$password = filter_var( $password, FILTER_SANITIZE_STRING );

		// Validate data
		if ( ! ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ) {
			$response['error'] = true;
			$response['msg']   = 'Input data not valid.';
			echoRespnse( 401, $response );
			$app->stop();
		}

		$db = new DbHandler();

		// check for correct email and password
		if ( $db_uid = $db->checkUserExisits( $email ) ) {
			if ( $db->userLogin( $email, $password ) ) {
				$response['error'] = false;
				$response['login'] = true;
				$response['msg']   = 'User logged in.';
				echoRespnse( 200, $response );
				$app->stop();
			}
			else {
				$response['error'] = true;
				$response['msg']   = 'Password wrong.';
				echoRespnse( 401, $response );
				$app->stop();
			}
		}
		else {
			$response['error'] = true;
			$response['msg']   = 'User not found.';
			echoRespnse( 401, $response );
			$app->stop();
		}
	}
);

/**
 * Register a new user
 */
$app->post(
	'/register', function() use ( $app ) {
		// check for required params
		verify_required_params( array(
			'name',
			'email',
			'email_confirm',
			'password',
			'password_confirm'
		));

		// reading post params
		$user = array(
			'name'             => $app->request()->post( 'name' ),
			'email'            => $app->request()->post( 'email' ),
			'email_confirm'    => $app->request()->post( 'email_confirm' ),
			'password'         => $app->request()->post( 'password' ),
			'password_confirm' => $app->request()->post( 'password_confirm' ),
		);

		// prepare the answer
		$response = array(
			'request' => 'register'
		);

		// Sanitize data
		$user['name']             = filter_var(  $user['name'], FILTER_SANITIZE_STRING );
		$user['email']            = filter_var(  $user['email'], FILTER_SANITIZE_EMAIL );
		$user['email_confirm']    = filter_var(  $user['email_confirm'], FILTER_SANITIZE_EMAIL );
		$user['password']         = filter_var( $user['password'], FILTER_SANITIZE_STRING );
		$user['password_confirm'] = filter_var( $user['password_confirm'], FILTER_SANITIZE_STRING );

		//Make sure the 2 emails are the same
		if ( $user['email'] !== $user['email_confirm'] ) {
			$response['error'] = true;
			$response['msg']   = 'Email verification failed.';
			echoRespnse( 401, $response );
			$app->stop();
		}

		//Make sure the 2 passwords are the same
		if ( $user['password'] !== $user['password_confirm'] ) {
			$response['error']   = true;
			$response['msg']     = 'Password verification failed.';
			echoRespnse( 401, $response );
			$app->stop();
		}

		// Validate data
		if ( ! ( filter_var( $user['email'], FILTER_VALIDATE_EMAIL ) ) ) {
			$response['error'] = true;
			$response['msg']   = 'Email not valid.';
			echoRespnse( 401, $response );
			$app->stop();
		}

		$db = new DbHandler();

		// Try to register a new user
		if ( $db->userRegister( $user ) ) {
			$response['error']    = false;
			$response['register'] = true;

			// Log the user in
			if ( $db->userLogin( $user['email'], $user['password'] ) ) {
				$response['error'] = false;
				$response['login'] = true;
				$response['msg']   = 'User correctly registered.';
				echoRespnse( 200, $response );
				$app->stop();
			}
			else {
				$response['error'] = true;
				$response['msg']   = 'Username or passeord wrong.';
				echoRespnse( 401, $response );
				$app->stop();
			}
		}
		else {
			$response['error'] = true;
			$response['msg']   = 'Another user with the same email already exists in the database.';
			echoRespnse( 401, $response );
			$app->stop();
		}
	}
);

$app->get(
	'/user/:userid', 'authenticate', function( $userUID ) use ( $app ) {
		sleep(2);
		$response = array(
			'request' => 'user'
		);

		$user_cookie = $app->getCookie( 'lq_user_id' );

		// The userid provided by the app url must be the same as the one stored inside the user's cookie
		$db = new DbHandler();

		if ( $userUID !== $user_cookie ) {
			$user_session = isset( $_COOKIE["BBC_session"] ) ? $_COOKIE["BBC_session"] : '';
			$db->logOut( $user_session );

			$response['error'] = true;
			$response['msg']   = 'Cannot verify the user identity. Please log in.';
			echoRespnse( 401, $response );
			$app->stop();
		}

		$user = array();
		$user = $db->getUser( $userUID );

		if ( !empty( $user ) ) {
			$response['error'] = false;
			$response['user']  = $user;
			$response['msg']   = 'User found.';
			echoRespnse( 200, $response );
			$app->stop();
		}

		$user_session = isset( $_COOKIE["BBC_session"] ) ? $_COOKIE["BBC_session"] : '';
		$db->logOut( $user_session );

		$response['error'] = true;
		$response['msg']   = 'The user is not present in the database.';
		echoRespnse( 401, $response );
		$app->stop();
});

$app->get(
	'/recipes', function() use ( $app ) {
		$db       = new DbHandler();
		$response = array(
			'request' => 'recipes'
		);

		$recipes = $db->getRecipes();

		if ( ! empty( $recipes['recipes'] ) ) {
			$response['error']   = false;
			$response['recipes'] = $recipes['recipes'];
			$response['msg']     = 'Recipes correctly fetched.';
			echoRespnse( 200, $response );
			$app->stop();
		}

		$response['error'] = true;
		$response['msg']   = 'Cannot retrieve the recipes.';
		echoRespnse( 200, $response );
		$app->stop();
});

$app->get(
	'/recipe/:recipe_uid', function( $recipe_uid ) use ( $app ) {
		$db       = new DbHandler();
		$response = array(
			'request' => 'recipe'
		);

		$recipe = $db->getRecipe( $recipe_uid );

		if ( ! empty( $recipe['recipe'] ) ) {
			$response['error']  = false;
			$response['recipe'] = $recipe['recipe'];
			$response['msg']    = 'Recipe data correctly fetched.';
			echoRespnse( 200, $response );
			$app->stop();
		}

		$response['error'] = true;
		$response['msg']   = 'Cannot retrieve the recipe information.';
		echoRespnse( 200, $response );
		$app->stop();
});

/**
 * Log out
 * terminate the current session
 */
$app->get(
	'/logout', function() use ( $app ) {
		// Dev only
		sleep(1);

		$user_session = isset( $_COOKIE["BBC_session"] ) ? $_COOKIE["BBC_session"] : '';
		$db           = new DbHandler();

		$response = array(
			'request' => 'logout'
		);

		// If the session cookie is not set
		// terminate the request
		if ( $user_session === '' ) {
			$response['error']      = true;
			$response['logged_out'] = true;
			$response['msg']        = 'Impossible to get the required user information to perform a log out.';

			echoRespnse( 200, $response );
			$app->stop();
		}

		$db->logOut( $user_session );
		$response['error']      = false;
		$response['logged_out'] = true;
		$response['msg']        = 'You are now logged out.';
		echoRespnse( 200, $response );
		$app->stop();

	}
);

/**
 * Verifying required params posted or not
 */
function verify_required_params( $required_fields ) {
	$error          = false;
	$error_fields   = '';
	$request_params = array();
	$request_params = $_REQUEST;

	// Handling PUT request params
	if ( $_SERVER['REQUEST_METHOD'] === 'PUT' ) {
		$app = \Slim\Slim::getInstance();
		parse_str( $app->request()->getBody(), $request_params );
	}
	foreach ( $required_fields as $field ) {
		if ( !isset( $request_params[$field] ) || strlen( trim( $request_params[$field] ) ) <= 0 ) {
			$error         = true;
			$error_fields .= $field . ', ';
		}
	}

	if ( $error ) {
		// Required field(s) are missing or empty
		// echo error json and stop the app
		$response            = array();
		$app                 = \Slim\Slim::getInstance();
		$response['error'] = true;
		$response['msg']   = 'Required field(s) ' . substr( $error_fields, 0, -2 ) . ' is missing or empty.';

		echoRespnse( 401, $response );
		$app->stop();
	}
}

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate( \Slim\Route $route ) {
	// Getting request headers
	$headers  = apache_request_headers();
	$response = array();
	$app = \Slim\Slim::getInstance();

  return;

	$user = array();
	$db   = new DbHandler();

	$userSession = $app->getCookie( 'BBC_recipes_session' );
	$userUID     = $app->getCookie( 'BBC_recipes_user_id' );

	$header  = array_change_key_case( $headers, CASE_UPPER );
	$api_key = isset( $header['BBC-API-KEY'] ) ? $header['BBC-API-KEY'] : '';

	if ( isset( $api_key ) ) {
	// 	// get the api key
	// 	// validating api key
		if ( $api_key !== '406cc6ed2c7471d7593461264c0db966' ) {
	// 		// api key is not present in users table
			$response['error'] = true;
			$response['msg']   = 'Access Denied. Invalid Api key.';
			echoRespnse( 401, $response );
			$app->stop();
		}
		if ( ! $db->authenticate( $userUID, $userSession ) ) {
			// authentication failed
			$response['error'] = true;
			$response['msg']   = 'Authentication failed.';
			echoRespnse( 401, $response );
			$app->stop();
		}
	}
	else {
		// api key is missing in header
		$response['error'] = true;
		$response['msg']   = 'Api key is misssing.';
		echoRespnse( 401, $response );
		$app->stop();
	}
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse( $status_code, $response ) {
	$app = \Slim\Slim::getInstance();

	// Http response code
	$app->status( $status_code );

	// setting response content type to json
	$app->contentType( 'application/json' );

	echo json_encode( $response );
}

$app->run();
?>
