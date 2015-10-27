<?php
namespace Config\Database;

class DB_Connect {

	private $conn;

	function __construct() {
	}

	/**
	 * Establishing database connection
	 * @return database connection handler
	 */
	function connect() {
		include_once dirname( __FILE__ ) . '/db.config.php';

		// Connecting to mysql database
		$this->conn = mysqli_connect( DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME ) or die();

		$this->conn->set_charset( DB_CHARSET );

		// returing connection resource
		return $this->conn;
	}

}

?>
