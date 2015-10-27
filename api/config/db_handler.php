<?php
/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author andreasonny83@gmail.com
 */
namespace Config\Database;
use \Config\Database\DB_Connect;
use \Config\SecureSessionHandler;

class DbHandler {

	/**
	 * [$conn description]
	 * @var [type]
	 */
	private $conn;

	/**
	 * [$session description]
	 * @var [type]
	 */
	private $session;

	function __construct() {
		$db            = new DB_Connect();
		$this->session = new SecureSessionHandler( 'bbc_recipes' );
		$this->conn    = $db->connect();
	}

	/**
	 * Check user exists in the database
	 * @input_email	String		User login email id
	 * @return		Boolean		User login status success/fail
	 */
	public function checkUserExisits( $input_email ) {
		// fetching user by email
		$stmt = $this->conn->prepare( 'SELECT user_uid FROM BBC_users
			WHERE email = ? LIMIT 1' );
		$stmt->bind_param( 's', $input_email );
		$stmt->execute();
		$stmt->bind_result( $db_uid );
		$stmt->store_result();

		if ( $stmt->num_rows === 1 ) {
			// If 1 and only 1 user if Found
			$stmt->fetch();
			$stmt->close();

			return $db_uid;
		}
		else {
			// No user exists.
			$stmt->close();
			return false;
		}
	}

	private function checkUserPassword( $input_email, $input_password ) {
		// fetching user by email
		$stmt = $this->conn->prepare( 'SELECT user_uid, password, salt
			FROM BBC_users
			WHERE email = ? LIMIT 1' );
		$stmt->bind_param( 's', $input_email );
		$stmt->execute();
		$stmt->bind_result( $db_uid, $db_password, $db_salt );
		$stmt->store_result();
		$stmt->fetch();

		// Continue only if 1 and only 1 user if found
		if ( $stmt->num_rows !== 1 ) {
			$stmt->close();
			return false;
		}
		$stmt->close();

		// Salt the input password with the salt from the database
		$password = hash( 'sha512', $input_password );
		$password = hash( 'sha512', $input_password . $db_salt );

		// Check if the password matches
		if ( $db_password === $password ) {
			// password is correct
			return $db_uid;
		}

		return false;
	}

	/**
	 * Check user credentials
	 * @input_email		String		User login email id
	 * @input_password	String		User login password
	 * @return			Boolean		User login status success/fail
	 */
	public function userLogin( $input_email, $input_password ) {
		if ( ! $db_uid = $this->checkUserPassword( $input_email, $input_password ) ) {
			// user password is incorrect
			return false;
		}

		// Generate a new session every time
		$this->session->start();
		$this->session->refresh();

		// Expire the session after 2 weeks
		$now                = time();
		$session_expiration = $now + 1209600;
		$session_id         = session_id();

		$stmt = $this->conn->prepare( "UPDATE BBC_users
				SET session_id=?, session_expiration=?
				WHERE user_uid=?" );
		$stmt->bind_param( 'sis', $session_id, $session_expiration, $db_uid );
		$stmt->execute();
		$stmt->close();

		// store the user id into the user's cookie
		setcookie( 'bbc_user_id', $db_uid, $session_expiration, '/' );
		return true;
	}

	/**
	 * Register a new user
	 * @param  [type] $user [description]
	 * @return [type]       [description]
	 */
	public function userRegister( $user ) {
		$stmt = $this->conn->prepare( 'SELECT id FROM BBC_users WHERE email = ? OR username = ? LIMIT 1' );
		$stmt->bind_param( 'ss', $user['email'], $user['name'] );
		$stmt->execute();
		$stmt->store_result();
		$result = $stmt->num_rows;
		$stmt->close();

		if ( $result ) {
			// If a user with the same email already exists in the database
			// Terminate the request
			return false;
		}
		else {
			// create the new user
			$status     = 1;
			$user_level = 1;
			$now        = time();

			// Create a salted password
			$random_salt = hash( 'sha512', uniqid( mt_rand( 1, mt_getrandmax() ), true ) );
			$password    = hash( 'sha512', $user['password'] . $random_salt );
			$user_uid    = uniqid();

			// Register a new user
			$stmt = $this->conn->prepare( "INSERT INTO BBC_users(
				user_uid, username, email, password, salt, status, registration_date )
				VALUES ( ?, ?, ?, ?, ?, ?, ? )" );

			$stmt->bind_param( 'sssssii',
				$user_uid, $user['name'], $user['email'], $password, $random_salt, $status, $now );

			if ( $stmt->execute() ) {
				// Registration succeed
				$stmt->close();
				return true;
			}
			else {
				// can't perform the database request
				$stmt->close();
				return false;
			}
		}
	}

	/**
	 * getUser
	 * @userUID String		User unique id
	 * @return Array		The user name
	 */
	public function getUser( $userUID ) {
		$stmt = $this->conn->prepare( 'SELECT
			username
			FROM BBC_users WHERE user_uid = ? LIMIT 1' );
		$stmt->bind_param( 's', $userUID );
		$stmt->execute();
		$stmt->bind_result( $username );
		$stmt->store_result();
		$stmt->fetch();
		$stmt->close();

		if ( isset ( $username ) ) {
			return array(
				"username" => $username,
			);
		}
		else {
			return false;
		}
	}

	/**
	 * getRecipes
	 *
	 * @return  array  Return the recipes, if any, and other informations
	 */
	public function getRecipes() {
		$recipes = array();

		$stmt = $this->conn->prepare( 'SELECT
			uid, title, description, image
			FROM BBC_recipes
			WHERE status = 2' );


		$stmt->execute();
		$stmt->bind_result( $uid, $title, $description, $image );
		$stmt->store_result();
		while( $stmt->fetch() ) {

			$recipes[] = array(
				'uid'         => $uid,
				'title'       => $title,
				'description' => $description,
				'image'       => $image,
			);
		}

		$stmt->close();

		return array(
			'recipes' => $recipes,
		);
	}

	/**
	 * get a specific recipe
	 *
	 * @return  array  Return the recipe information
	 */
	public function getRecipe( $recipe_uid ) {
		$stmt = $this->conn->prepare( 'SELECT
			title, description, image, preparation
			FROM BBC_recipes
			WHERE uid = ?' );
		$stmt->bind_param( 's', $recipe_uid );

		$stmt->execute();
		$stmt->bind_result( $title, $description, $image, $preparation );
		$stmt->store_result();
		$stmt->fetch();
		$stmt->close();

		$recipe = array(
			'title'       => $title,
			'description' => $description,
			'image'       => $image,
			'preparation' => $preparation,
		);

		return array(
			'recipe' => $recipe,
		);
	}

	public function logOut( $user_session ) {
		$now = time() + 1;

		//Expire the current session
		if ( $stmt = $this->conn->prepare( "UPDATE BBC_users
			SET session_expiration = ?
			WHERE session_id = ?" ) ) {
				$stmt->bind_param( 'ii', $now, $user_session );
				$stmt->execute();
		}

		$this->session->forget();

		// unset cookies
		if ( isset( $_SERVER['HTTP_COOKIE'] ) ) {
			$cookies = explode( ';', $_SERVER['HTTP_COOKIE'] );
			foreach( $cookies as $cookie ) {
				$parts = explode( '=', $cookie );
				$name = trim( $parts[0] );
				setcookie( $name, '', time() - 1000 );
				setcookie( $name, '', time() - 1000, '/' );
			}
		}
	}

	/**
	 * Authenticate the request
	 * The request need to have the BBC-API-KEY correctly set
	 * and the user session needs to be verified
	 * @userUID		String		User unique ID
	 * @userSession	String		The session ID
	 * @return		Boolean
	 */
	public function authenticate( $userUID, $userSession ) {
		$stmt = $this->conn->prepare( 'SELECT
			session_id, session_expiration
			FROM BBC_users
			WHERE user_uid = ?'
		);
		$stmt->bind_param( 's', $userUID );
		$stmt->store_result();
		$stmt->bind_result( $db_session_id, $db_session_exp );
		$stmt->execute();
		$stmt->fetch();
		$stmt->close();

		if ( $db_session_id !== $userSession ) {
			return false;
		}

		if ( time() > $db_session_exp ) {
			return false;
		}

		return true;
	}

}
