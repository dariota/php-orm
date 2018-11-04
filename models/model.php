<?php

require_once "config.php";
require_once "$SECRETS";

abstract class Model {
	private static $skip_id = NULL;
	private $_meta = [ "persisted" => false, "connection" => NULL ];
	var $id;
	var $belongs;
	var $has;

	function __construct($id = NULL) {
		if ($id == Model::$skip_id) return;
		if ($id < 1) throw new InvalidIdException;

		$this->id = $id;
		$this->reload();
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
		if ($this->id == Model::$skip_id || $this->id < 1) throw new InvalidIdException;

		$stmt = "SELECT * FROM " . $this->table_name() . " where id=:id";
		$sth = $this->connection()->prepare($stmt);
		$sth->bindValue("id", $this->id);
		$sth->execute();

		$class_name = get_class($this);
		$res = $sth->fetchObject($class_name, array(Model::$skip_id));
		if ($res === false) throw new RecordNotFoundException("Couldn't find $class_name with ID $this->id");

		foreach (get_object_vars($res) as $key => $value) {
			if ($key == "_meta") continue;
			$this->$key = $value;
		}
		$this->_meta["persisted"] = true;
	}

	function __call($name, $args) {
		if (count($args)) throw new BadMethodCallException("Association methods accept no arguments");
		$class_name = ucfirst($name);
		$table_name = Model::databasify($class_name);

		$belong_key = false;
		if ($this->belongs !== NULL)
			$belong_key = array_search($class_name, $this->belongs);

		if ($belong_key !== false) {
			$association_col = $table_name . "_id";
			return new $class_name($this->$association_col);
		} else {
			if ($this->has === NULL) throw new BadMethodCallException("No has association defined, belongs association exhausted");

			$has_key = array_search($class_name, $this->has);
			if ($has_key === false) {
				$class_name = substr($class_name, 0, -1);
				$has_key = array_search($class_name, $this->has);
				$table_name = Model::databasify($class_name);
			}

			if ($has_key !== false) {
				$association_col = $this->table_name() . "_id";
				$stmt = "SELECT id FROM $table_name where $association_col = :id";
				$sth = $this->connection()->prepare($stmt);
				$sth->bindValue("id", $this->id);
				$sth->execute();
				$ids = $sth->fetchAll();
				$sth->closeCursor();

				$result_set = array();
				foreach ($ids as $row) {
					$result_set[] = new $class_name($row["id"]);
				}
				return $result_set;
			}
		}

		$current_class = get_class($this);
		throw new BadMethodCallException("No association $name found for $current_class");
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
}

class RecordNotPersistedException extends Exception { }
class RecordNotFoundException extends Exception { }
class InvalidIdException extends Exception { }

?>
