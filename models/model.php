<?php
namespace recipe\orm;
use recipe\orm\condition as c;

require_once F_ROOT . '/exceptions/db.php';
require_once 'query.php';

/**
 * Provides a DB model through inheritance.
 *
 * Child classes get a constructor which loads from the DB, dynamic methods
 * to retrieve associated models, and a create/update method.
 */
abstract class Model {
	const T_BOOL = 0;
	const T_FLOAT = 1;
	const T_INT = 2;
	const T_STR = 3;

	static $skip_id = NULL;

	private $_meta = [ "persisted" => false, "connection" => NULL ];
	var $id;
	var $belongs;
	var $has;
	var $validations;

	/**
	 * Constructs a model of the child class.
	 *
	 * @param int $id (Optional) If provided, the model with this ID will be
	 *                loaded from the DB using reload(), so this can raise any
	 *                exceptions that reload() can.
	 * @param boolean $bulk Internal use only.
	 *
	 * @return class A model of the child class, which may or may not be marked
	 *               persisted, depending on whether an ID was provided.
	 *
	 * @throws InvalidIdException If ID is non-positive.
	 *
	 * @see reload()
	 */
	function __construct($id = NULL, $bulk = false) {
		// generated by PDO::fetchObject or fetch mode, just mark as persisted
		if ($bulk) $this->_meta["persisted"] = true;

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
	 * Calls the validate() and pre_save() hooks before operation.
	 *
	 * Does not currently re-wrap DB level errors.
	 *
	 * @return void
	 *
	 * @throws RecordNotPersistedException If an ID is provided for a non-
	 *                                     persisted record or the DB reports
	 *                                     that no rows changed.
	 * @throws PDOException DB level errors.
	 * @throws InvalidModelException If validation fails.
	 */
	function save() {
		$this->pre_save();
		$this->validate();

		$table = $this->table_name();
		$vars = get_object_vars($this);
		// prevent metadata/association variables from being written to the DB
		unset($vars["_meta"], $vars["has"], $vars["belongs"],
		      $vars["validations"]);

		$verb = "INSERT INTO";
		$where = "";
		if ($this->_meta["persisted"]) {
			$verb = "UPDATE";
			$where = " where id=:id";
			// if there's nothing to update, return before doing DB stuff
			if (!count($vars)) return;

			// prevent choosing a primary key, which are assumed to auto-
			// increment
		} else if ($vars["id"])
			throw new RecordNotPersistedException("ID provided for non-persisted record");
		else unset($vars["id"]);

		$stmt = "$verb $table SET ";
		$first = true;

		// TODO detect "dirtied" vars using $this->_meta and hashes of
		// variables to do more efficient updates
		foreach ($vars as $k => $v) {
			if ($k == "id") continue;

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
		if ($this->id == Model::$skip_id || $this->id < 1)
			throw new InvalidIdException;

		$query = new Query(get_class($this), $this->table_name(),
		                   c\column("id")->eq($this->id), Model::connector());
		$res = $query->find();

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
		if (count($args))
			throw new BadMethodCallException("Association methods accept no arguments");

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
			if ($this->has === NULL)
				throw new BadMethodCallException("No has association defined, belongs association exhausted");

			$has_key = array_search($class_name, $this->has);
			if ($has_key === false) {
				// TODO handle things that pluralise differently? box -> boxes
				// handle potential pluralisation (e.g. books -> Book)
				$class_name = substr($class_name, 0, -1);
				$has_key = array_search($class_name, $this->has);
				$table_name = Model::databasify($class_name);
			}

			if ($has_key !== false) {
				// if the model isn't persisted, it doesn't have an ID to search
				// by, but this return is done late to ensure the assocation
				// would have been valid anyway
				if (!$this->_meta["persisted"])
					return [];

				$association_col = $this->table_name() . "_id";
				$query = new Query($class_name, $table_name,
				                   c\column($association_col)->eq($this->id),
				                   Model::connector());
				return $query->limit(0)->find();
			}
		}

		// fell-through all checks - no relevant belongs or has
		$current_class = get_class($this);
		throw new BadMethodCallException("No association $name found for $current_class");
	}

	/**
	 * Allows a model to hook in an action before being saved.
	 *
	 * This will be called BEFORE any save actions, including statement
	 * generation. As such, any variables added or updated WILL be persisted.
	 * This will be called BEFORE validations. That is, if the pre-save hook
	 * results in invalid data, save() WILL raise an InvalidModelException.
	 *
	 * @see validate(), save()
	 */
	function pre_save() {
	}

	/**
	 * Allows a model to hook in validation of its state.
	 *
	 * If $this->validations is set in the correct format, the default
	 * implementation will perform validations as specified. The following are
	 * currently supported:
	 *
	 * [
	 *   "var_1" => [ "type" => Model::T_STR, "len" => 10 ],
	 *   "var_2" => [ "type" => Model::T_INT, "max" => 10, "min" => 0 ],
	 *   "var_3" => [ "type" => Model::T_BOOL ],
	 *   "var_4" => [ "type" => Model::T_FLOAT, "max" => 1.0, "min" => 0.0 ],
	 *   "var_5" => [ "exists" ]
	 * ]
	 *
	 * The len check implies the string typecheck, max and min imply the float
	 * typecheck, and the int typecheck uses castable_int, and as such strongly
	 * types the variable to be an int.
	 *
	 * The max, min and len checks are all exclusive. They also assume the value
	 * to check against is a valid numeric type.
	 *
	 * This will be called immediately BEFORE the pre_save() hook.
	 * Allows a model to validate its own state, such as checking that enum
	 * values are within permitted values, or that specific variables are in the
	 * right range or type of values.
	 * Validate MAY perform corrections of the model's state.
	 *
	 * @throws InvalidModelException If the model's state is invalid, or the
	 *                               $this->validations is not an array of the
	 *                               right shape.
	 *
	 * @see pre_save(), save()
	 */
	function validate() {
		if (!isset($this->validations))
			return;
		if (!is_array($this->validations))
			throw new InvalidModelException("validations is not an array");

		foreach ($this->validations as $var => $validation) {
			if (!is_array($validation))
				throw new InvalidModelException("$var's validations were not an array");

			// unset empty string unless we know the var's a string
			if (!((isset($validation["type"])
			    && $validation["type"] === Model::T_STR)
			    || isset($validation["len"])) && isset($this->$var)
			    && $this->$var === "")
				unset($this->$var);

			foreach ($validation as $kind => $allowed) {
				if ($allowed !== "exists" && !isset($this->$var))
					continue;

				if ($kind === "type") {
					switch ($allowed) {
					case Model::T_BOOL:
						if (!castable_bool($this->$var))
							throw new InvalidModelException("$var must be a boolean");
						break;
					case Model::T_FLOAT:
						if (!is_numeric($this->$var))
							throw new InvalidModelException("$var must be numeric");
						break;
					case Model::T_INT:
						if (!castable_int($this->$var))
							throw new InvalidModelException("$var must be an int");
						break;
					case Model::T_STR:
						if (!is_string($this->$var))
							throw new InvalidModelException("$var must be a string");
						break;
					default:
						throw new InvalidModelException("Unknown type $allowed for $var");
					}
				} else if ($kind === "min") {
					if (!is_numeric($this->$var))
						throw new InvalidModelException("Can't check min on non-numeric $var");

					if ($this->$var < $allowed)
						throw new InvalidModelException("$var is less than min $allowed");
				} else if ($kind === "max") {
					if (!is_numeric($this->$var))
						throw new InvalidModelException("Can't check max on non-numeric $var");

					if ($this->$var > $allowed)
						throw new InvalidModelException("$var is greater than max $allowed");
				} else if ($kind === "len") {
					if (!is_string($this->$var))
						throw new InvalidModelException("Can't check string length on non-string $var");

					if (strlen($this->$var) > $allowed)
						throw new InvalidModelException("$var is longer than max $allowed");
				} else if ($allowed === "exists") {
					if (!isset($this->$var) || $this->$var === NULL)
						throw new InvalidModelException("$var must exist");
				} else {
					throw new InvalidModelException("Unknown validation for $var: $kind");
				}
			}
		}
	}

	/**
	 * Creates a Query for the given table, using $args for the where clause.
	 *
	 * @param object $condition A Condition object, or null for no condition.
	 *
	 * @return object A query object, initialised with the arguments passed
	 *
	 * @throws InvalidArgumentException If args is not a Condition, or the called
	 *                                  class is Model.
	 *
	 * @see Query
	 */
	static function where($condition) {
		$class_name = get_called_class();
		if ($class_name == "Model")
			throw new InvalidArgumentException("where must be called on a subclass of Model");

		return new Query($class_name, Model::databasify($class_name),
		                 $condition, Model::connector());
	}

	private function connection() {
		// rudimentary connection storage - docs make it unclear if this is
		// actually a good idea, but connections should be short lived anyway.
		// One connection per model instance is required by the current save()
		// implementation, at least for non-persisted objects.
		if ($this->_meta["connection"]) return $this->_meta["connection"];

		$this->_meta["connection"] = Model::connect();
		return $this->_meta["connection"];
	}

	private function table_name() {
		return Model::databasify(get_class($this));
	}

	private static function connector() {
		return function() {
			return Model::connect();
		};
	}

	private static function connect() {
		// assumed to be present in secrets, included by entry require
		global $HOST, $DATABASE, $USERNAME, $PASSWORD;

		try {
			return new \PDO("mysql:host=$HOST;dbname=$DATABASE", $USERNAME,
			                $PASSWORD);
		} catch (\PDOException $e) {
			echo "Failed to get DB connection " . $e->getMessage();
			die();
		}
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

?>
