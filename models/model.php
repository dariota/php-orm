<?php

/**
 * Provides a DB model through inheritance.
 *
 * Child classes get a constructor which loads from the DB, dynamic methods
 * to retrieve associated models, and a create/update method.
 */
abstract class Model {
	private static $skip_id = NULL;
	private $_meta = [ "persisted" => false, "connection" => NULL ];
	var $id;
	var $belongs;
	var $has;

	/**
	 * Constructs a model of the child class.
	 *
	 * @param int $id (Optional) If provided, the model with this ID will be
	 *                loaded from the DB using reload(), so this can raise any
	 *                exceptions that reload() can.
	 *
	 * @return class A model of the child class, which may or may not be marked
	 *               persisted, depending on whether an ID was provided.
	 *
	 * @throws InvalidIdException If ID is non-positive.
	 *
	 * @see reload()
	 */
	function __construct($id = NULL) {
		// prevent recursive calls to __construct through PDO::fetchObject
		if ($id == Model::$skip_id) return;
		if ($id < 1) throw new InvalidIdException;

		$this->id = $id;
		$this->reload();
	}

	/**
	 * Creates or updates the model in the DB.
	 *
	 * If the model is not persisted, it will be created and its $id variable
	 * will be populated with the value assigned by the DB.
	 * If the model is persisted, it will instead be updated with the current
	 * variable values.
	 *
	 * Does not currently re-wrap DB level errors.
	 *
	 * @return void
	 *
	 * @throws RecordNotPersistedException If an ID is provided for a non-
	 *                                     persisted record or the DB reports
	 *                                     that no rows changed.
	 * @throws PDOException DB level errors.
	 */
	function save() {
		$table = $this->table_name();
		$vars = get_object_vars($this);
		// prevent metadata/association variables from being written to the DB
		unset($vars["_meta"], $vars["has"], $vars["belongs"]);

		$verb = "INSERT INTO";
		$where = "";
		if ($this->_meta["persisted"]) {
			$verb = "UPDATE";
			$where = " where id=:id";
			// if there's nothing to update, return before doing DB stuff
			if (!count($vars)) return;

			// prevent choosing a primary key, which are assumed to auto-
			// increment
		} else if ($vars["id"]) throw new RecordNotPersistedException("ID provided for non-persisted record");

		$stmt = "$verb $table SET ";
		$first = true;

		// TODO detect "dirtied" vars using $this->_meta and hashes of
		// variables to do more efficient updates
		foreach ($vars as $k => $v) {
			if ($verb == "UPDATE" && $k == "id") continue;

			if (!$first) $stmt .= ",";

			$stmt .= "$k=:$k";
			$first = false;
		}

		if ($verb == "UPDATE") $stmt .= $where;

		// prepare, bind, and execute the PDO for safety
		$sth = $this->connection()->prepare($stmt);
		if ($sth->execute($vars)) {
			// shouldn't be required as we don't reuse the statement, but it
			// can't hurt
			$sth->closeCursor();

			// for inserts, we don't know the ID until executed, but need it
			// after in case of future updates
			if ($verb != "UPDATE")
				$this->id = $this->connection()->lastInsertId();

			$this->_meta["persisted"] = true;
		} else {
			// DB reported no rows changed, when at least (exactly?) one should
			// have
			throw new RecordNotPersistedException("No columns changed");
		}
	}

	/**
	 * Loads the record with $this->id, overwriting the variables in $this.
	 *
	 * @throws InvalidIdException If $this->id is non-positive or NULL.
	 * @throws RecordNotFoundException If no record is found with the given ID.
	 */
	function reload() {
		// this condition is also guarded by the constructor, but ID could have
		// been modified in the meantime
		if ($this->id == Model::$skip_id || $this->id < 1) throw new InvalidIdException;

		// mostly just wrap PDO::fetchObject with some sql generation, but also
		// overwriting an existing object instead of creating a new one
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

	/**
	 * Loads associated records specified in $this->has or $this->belongs.
	 *
	 * Intercepts undefined method calls and determines if they're an attempt
	 * to load an associated record.
	 *
	 * For example, assume a book has a single author who can have many books.
	 * <pre>class Book extends Model { var $belongs = ["Author"] }</pre>
	 * <pre>class Author extends Model { var $has = ["Book"] }</pre>
	 *
	 * This method allows loading of all of an author's books:
	 * <pre>$author->books() == $author->book()</pre>
	 *
	 * Or a given book's author:
	 * <pre>$book->author()</pre>
	 *
	 * @return mixed A single model if the association is belongs, or a
	 *               (possibly empty) array if the association is has.
	 *
	 * @throws BadMethodCallException If no matching association is found.
	 * @throws RecordNotFoundException If the association is belongs and no
	 *                                 associated record is found.
	 */
	function __call($name, $args) {
		// broadly handle improper calls, since associations never take args
		if (count($args)) throw new BadMethodCallException("Association methods accept no arguments");

		// uppercase the method to get the presumed class name (e.g. books ->
		// Books, author -> Author)
		$class_name = ucfirst($name);
		$table_name = Model::databasify($class_name);

		// check if the association is a belongs, which is easier to handle
		$belong_key = false;
		if ($this->belongs !== NULL)
			$belong_key = array_search($class_name, $this->belongs);

		// strict comparison, array_search can return falsey values on success
		if ($belong_key !== false) {
			// pull out the expected association ID column, rely on constructor
			// to handle the load
			$association_col = $table_name . "_id";
			return new $class_name($this->$association_col);
		} else {
			if ($this->has === NULL) throw new BadMethodCallException("No has association defined, belongs association exhausted");

			$has_key = array_search($class_name, $this->has);
			if ($has_key === false) {
				// TODO handle things that pluralise differently? box -> boxes
				// handle potential pluralisation (e.g. books -> Book)
				$class_name = substr($class_name, 0, -1);
				$has_key = array_search($class_name, $this->has);
				$table_name = Model::databasify($class_name);
			}

			// TODO this uses N+1 queries for simplicity right now
			if ($has_key !== false) {
				// fetch the IDs of associated records
				$association_col = $this->table_name() . "_id";
				$stmt = "SELECT id FROM $table_name where $association_col = :id";
				$sth = $this->connection()->prepare($stmt);
				$sth->bindValue("id", $this->id);
				$sth->execute();
				$ids = $sth->fetchAll();
				$sth->closeCursor();

				// use the constructor to retrieve each associated record for
				// simplicity
				$result_set = array();
				foreach ($ids as $row) {
					$result_set[] = new $class_name($row["id"]);
				}
				return $result_set;
			}
		}

		// fell-through all checks - no relevant belongs or has
		$current_class = get_class($this);
		throw new BadMethodCallException("No association $name found for $current_class");
	}

	private function connection() {
		// assumed to be present in secrets, included by entry require
		global $HOST, $DATABASE, $USERNAME, $PASSWORD;

		// rudimentary connection storage - docs make it unclear if this is
		// actually a good idea, but connections should be short lived anyway
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
		// limit table name length, convert hyphens to underscores, drop non-
		// word characters, remove duplicate underscores
		// eg: TableName -> tablename
		//     tabLE--N!ame -> table_name
		//     a*65 -> a*64
		$string = substr(strtolower($string), 0, 64);
		$string = preg_replace(["~_{2,}~", "~[^\w_]+~"], ["_", ""], str_replace("-", "_", $string));
		return $string;
	}
}

class RecordNotPersistedException extends Exception { }
class RecordNotFoundException extends Exception { }
class InvalidIdException extends Exception { }

?>
