<?php
namespace recipe\orm;
use recipe\orm\condition as c;

require_once F_ROOT . '/exceptions/db.php';
require_once F_ROOT . '/utility/utils.php';

class Query {
	private $class_name;
	private $table;
	private $connector;
	private $connection;
	private $condition;
	private $condition_params;
	private $limit_count = 1;

	/**
	 * Constructs a query object for a table with specific args.
	 *
	 * By default, a query retrieves only one object. Use limit() to change
	 * how many objects to retrieve.
	 * NOTE: The keys are not validated, and are vulnerable to sqli.
	 *
	 * @param string $class_name The name of the class to deserialise records
	 *                           to.
	 * @param string $table_name The name of the table to query.
	 * @param object $args The condition to use, or null for no condition.
	 * @param object $connector A callable, taking no arguments, returning a PDO
	 *                          connection.
	 *
	 * @throws InvalidArgumentException If param types are wrong, or args
	 *                                  array has non-string keys.
	 */
	function __construct($class_name, $table_name, $condition, $connector) {
		if ($condition && !c\Condition::is_instance($condition))
			throw new InvalidArgumentException("Condition must be a condition object or null");

		$this->class_name = $class_name;
		$this->table = $table_name;
		$this->connector = $connector;
		$this->condition = $condition;
		$this->condition_params = [];
	}

	/**
	 * Sets the limit of records that can be returned by find().
	 *
	 * @param int $limit The max number of records to be returned, set to 0 for
	 *                   no limit, default 1.
	 *
	 * @return object $this for function chaining.
	 *
	 * @throws InvalidArgumentException If $limit is negative or non-int.
	 */
	function limit($limit = 1) {
		if (!castable_int($limit))
			throw new InvalidArgumentException("limit must be non-negative int");

		$this->limit_count = $limit;
		return $this;
	}

	/**
	 * Retrieves the first $this->limit records matching the query described.
	 *
	 * @return mixed The retrieved record or array of records depending on
	 *               $this->limit.
	 *
	 * @throws RecordNotFoundException If no matching record is found and limit
	 *                                 is 1.
	 */
	function find() {
		// mostly just wrap PDO::fetchObject with some sql generation
		$stmt = "SELECT * FROM $this->table";

		if ($this->condition) {
			$stmt .= " WHERE $this->condition";
			$this->condition_params = $this->condition->params;
		}

		if ($this->limit_count)
			$stmt .= " LIMIT $this->limit_count";

		$sth = $this->connection()->prepare($stmt);
		$sth->execute($this->condition_params);

		if ($this->limit_count == 1) {
			$res = $sth->fetchObject($this->class_name,
			                         array(Model::$skip_id, true));
			$sth->closeCursor();
			if ($res === false)
				throw new RecordNotFoundException;

			return $res;
		} else {
			$results = $sth->fetchAll(\PDO::FETCH_CLASS, $this->class_name,
			                          array(Model::$skip_id, true));
			$sth->closeCursor();
			return $results;
		}
	}

	private function connection() {
		if ($this->connection)
			return $this->connection;

		$this->connection = call_user_func($this->connector);
		return $this->connection;
	}

}

?>
