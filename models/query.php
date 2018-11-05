<?php
namespace recipe\orm;

require_once F_ROOT . '/exceptions/db.php';
require_once F_ROOT . '/utility/utils.php';

class Query {
	private $class_name;
	private $table;
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
	 * @param array $args The conditions to use [ "column" => "expected value"].
	 * @param object $connection A PDO connection.
	 *
	 * @throws InvalidArgumentException If param types are wrong, or args
	 *                                  array has non-string keys.
	 */
	function __construct($class_name, $table_name, $args, $connection) {
		$this->class_name = $class_name;
		$this->table = $table_name;
		$this->connection = $connection;
		$this->condition = Query::construct_where($args, true);
		$this->condition_params = $args;
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

		if (strlen($this->condition))
			$stmt .= " WHERE $this->condition";

		if ($this->limit_count)
			$stmt .= " LIMIT $this->limit_count";

		$sth = $this->connection->prepare($stmt);
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

	private static function construct_where($args, $conjuction) {
		$where = "";
		$first = true;
		foreach (array_keys($args) as $key) {
			if (!is_string($key))
				throw new InvalidArgumentException("args keys must be strings");
			if (!$first)
				$where .= $conjuction ? " AND " : " OR ";

			$where .= "$key=:$key";
		}

		return $where;
	}

}

?>
