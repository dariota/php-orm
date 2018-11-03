<?php

require_once "config.php";
require_once "$SECRETS";

abstract class Model {
	private $_meta = [ "persisted" => false, "connection" => NULL ];
	var $id;
	var $belongs;
	var $has;

	function __construct($id) {
		
	}

	function save() {
		$table = $this->table_name();
		$vars = get_object_vars($this);
		unset($vars["_meta"], $vars["has"], $vars["belongs"]);

		$verb = "INSERT INTO";
		$where = "";
		if ($this->_meta["persisted"]) {
			$verb = "UPDATE";
			$where = " where id=:id";
			if (!count($vars)) return;
		} else if ($vars["id"]) throw new RecordNotPersistedException("ID provided for non-persisted record");

		$stmt = "$verb $table SET ";
		$first = true;

		foreach ($vars as $k => $v) {
			if ($verb == "UPDATE" && $k == "id") continue;

			if (!$first) $stmt .= ",";

			$stmt .= "$k=:$k";
			$first = false;
		}

		if ($verb == "UPDATE") $stmt .= $where;

		$sth = $this->connection()->prepare($stmt);
		if ($sth->execute($vars)) {
			$sth->closeCursor();

			if ($verb != "UPDATE")
				$this->id = $this->connection()->lastInsertId();

			$this->_meta["persisted"] = true;
		} else {
			throw new RecordNotPersistedException("No columns changed");
		}
	}

	function reload() {

	}

	private function connection() {
		global $HOST, $DATABASE, $USERNAME, $PASSWORD;

		if ($this->_meta["connection"]) return $this->_meta["connection"];

		try {
			$this->_meta["connection"] = new PDO("mysql:host=$HOST;dbname=$DATABASE", $USERNAME, $PASSWORD);
			return $this->_meta["connection"];
		} catch (PDOException $e) {
			echo "Failed to get DB connection " . $e->getMessage();
			die();
		}
	}

	private function table_name() {
		return Model::databasify(get_class($this));
	}

	private static function databasify($string) {
		$string = substr(strtolower($string), 0, 64);
		$string = preg_replace(["~_{2,}~", "~[^\w_]+~"], ["_", ""], str_replace("-", "_", $string));
		return $string;
	}

/*	function __call($name, $args) {
		// for assocations
	}
 */
}

class RecordNotPersistedException extends Exception { }

?>
