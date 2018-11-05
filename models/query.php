<?php
namespace recipe\orm;

require_once F_ROOT . '/exceptions/db.php';

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
