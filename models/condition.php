<?php

namespace recipe\orm\condition;
use recipe\orm as orm;

require_once F_ROOT . '/exceptions/validation.php';

/**
 * Creates a condition based on the value of the named column.
 *
 * column() is a leaf function, i.e. it is one of the possible beginnings of a
 * condition.
 * No validation of the column is done, whether for existence, safety, belonging
 * to the table it will be used as a condition for, etc.
 *
 * @param string $name The name of the column to compare to.
 *
 * @return A Condition object which will run comparisons on this column.
 *
 * @throws InvalidArgumentException If $name is not a string.
 */
function column($name) {
	if (!is_string($name))
		throw new orm\InvalidArgumentException("Column name must be string");

	return new Condition($name, Condition::T_COL, Condition::K_COL);
}

/**
 * Creates a condition based on the given constant.
 *
 * value() is a leaf function, i.e. it is one of the possible beginnings of a
 * condition.
 * The value must be string, int or boolean typed.
 *
 * @param mixed $value The constant value to compare to.
 *
 * @return A Condition object which will run comparisons on this constant.
 *
 * @throws InvalidArgumentException If $name is not a string, int or boolean.
 */
function value($value) {
	if (orm\castable_int($value)) {
		return new Condition($value, Condition::T_INT, Condition::K_CONST);
	} else if (is_bool($value)) {
		return new Condition($value, Condition::T_BOOL, Condition::K_CONST);
	} else if (is_string($value)) {
		return new Condition($value, Condition::T_STR, Condition::K_CONST);
	}

	throw new orm\InvalidArgumentException("Value was not int, bool or string");
}

/**
 * Creates a disjunction of conditions, i.e. logical OR.
 *
 * any() is a leaf function, i.e. it is one of the possible beginnings of a
 * condition.
 * Takes a variable amount of arguments, which must all be Condition objects.
 *
 * @param object $vararg A list of Condition objects to disjoin.
 *
 * @return A Condition object which is true if any passed conditions are true.
 *
 * @throws InvalidArgumentException If nothing is passed, or if anything other
 *                                  than condition objects are passed.
 * @throws InvalidConditionException If the passed conditions are not boolean or
 *                                   integral.
 */
function any() {
	if (!func_num_args())
		throw new orm\InvalidArgumentException("Nothing passed to any()");

	$conditions = func_get_args();
	guard_junction($conditions, "any");

	return new Condition(null, CONDITION::T_BOOL, Condition::K_ANY, $conditions);
}

/**
 * Creates a conjunction of conditions, i.e. logical AND.
 *
 * all() is a leaf function, i.e. it is one of the possible beginnings of a
 * condition.
 * Takes a variable amount of arguments, which must all be Condition objects.
 *
 * @param object $vararg A list of Condition objects to conjoin.
 *
 * @return A Condition object which is true if all passed conditions are true.
 *
 * @throws InvalidArgumentException If nothing is passed, or if anything other
 *                                  than condition objects are passed.
 * @throws InvalidConditionException If the passed conditions are not boolean or
 *                                   integral.
 */
function all() {
	if (!func_num_args())
		throw new orm\InvalidArgumentException("Nothing passed to all()");

	$conditions = func_get_args();
	guard_junction($conditions, "all");

	return new Condition(null, CONDITION::T_BOOL, Condition::K_ALL, $conditions);
}

/**
 * Negates the passed condition, i.e. logical NOT.
 *
 * not() is a leaf function, i.e. it is one of the possible beginnings of a
 * condition.
 *
 * @param object $condition The Condition object to negate.
 *
 * @return A Condition object which is true if all passed conditions are true.
 *
 * @throws InvalidArgumentException If anything other than a condition object
 *                                  is passed.
 * @throws InvalidConditionException If the passed condition is not boolean or
 *                                   integral. (while mysql would accept a
 *                                   string, that feels like a bad idea)
 */
function not($condition) {
	guard_junction([$condition], "not");

	return new Condition(null, Condition::T_BOOL, Condition::K_NOT, [$condition]);
}

/**
 * Internal helper function.
 */
function guard_junction($conditions, $func_name) {
	foreach ($conditions as $condition) {
		if (!is_a($condition, "recipe\\orm\\condition\\Condition"))
			throw new orm\InvalidArgumentException("Non-condition passed to $func_name()");

		switch ($condition->get_type()) {
		case Condition::T_INT:
		case Condition::T_BOOL:
		case Condition::T_COL:
			break;
		default:
			throw new InvalidConditionException("Non-int or bool condition passed to $func_name()");
		}
	}
}

class Condition {
	const K_ALL = "k_all";
	const K_ANY = "k_any";
	const K_COL = "k_column";
	const K_CONST = "k_const";
	const K_CONTAINS = "k_cont";
	const K_EQ = "k_eq";
	const K_GE = "k_ge";
	const K_GT = "k_gt";
	const K_LE = "k_le";
	const K_LT = "k_lt";
	const K_NEQ = "k_neq";
	const K_NOT = "k_not";
	const K_NULL = "k_null";

	const T_BOOL = "t_bool";
	const T_COL = "t_column";
	const T_INT = "t_int";
	const T_STR = "t_str";

	private $kind;
	private $type;
	private $value;
	private $children;

	/**
	 * Constructs a Condition with the passed values.
	 *
	 * Performs only minimal validation and should not be used externally.
	 *
	 * @throws InvalidArgumentException If $children is not an array or contains
	 *                                  non-conditions.
	 */
	function __construct($value, $type, $kind, $children = []) {
		if (!is_array($children))
			throw new orm\InvalidArgumentException("Children must be an array");
		$this->value = $value;
		$this->type = $type;
		$this->kind = $kind;
		$this->children = [];

		foreach ($children as $child) {
			$this->add_child($child);
		}
	}

	/**
	 * True if the string value of $this contains $str anywhere.
	 *
	 * Escapes control characters such as \, % and _.
	 * Comparison is case-insensitive.
	 *
	 * @param string $str The string to search for.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to contains $str anywhere.
	 *
	 * @throws InvalidArgumentException If $str is not a string.
	 * @throws InvalidConditionException If $this is not a string-typed
	 *                                   Condition.
	 */
	function contains($str) {
		return $this->string_condition("contains", $str, "%", "%");
	}

	/**
	 * True if the string value of $this starts with $str.
	 *
	 * Escapes control characters such as \, % and _.
	 * Comparison is case-insensitive.
	 *
	 * @param mixed $str Either a string, or a string valued Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to starts with $str.
	 *
	 * @throws InvalidArgumentException If $str is neither a string or string-
	 *                                  typed Condition.
	 * @throws InvalidConditionException If $this is not a string-typed
	 *                                   Condition.
	 */
	function starts_with($str) {
		return $this->string_condition("starts_with", $str, "", "%");
	}

	/**
	 * True if the string value of $this ends with $str.
	 *
	 * Escapes control characters such as \, % and _.
	 * Comparison is case-insensitive.
	 *
	 * @param mixed $str Either a string, or a string valued Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to ends with $str.
	 *
	 * @throws InvalidArgumentException If $str is neither a string or string-
	 *                                  typed Condition.
	 * @throws InvalidConditionException If $this is not a string-typed
	 *                                   Condition.
	 */
	function ends_with($str) {
		return $this->string_condition("ends_with", $str, "%", "");
	}

	/**
	 * True if the value of $this is null.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is null.
	 */
	function null_value() {
		return new Condition(null, Condition::T_BOOL, Condition::K_NULL,
		                     [$this]);
	}

	/**
	 * True if the value of $this is equal to $value.
	 *
	 * @param mixed $value Either a string, boolean, or integral
	 *                     constant or Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is equal to $value.
	 *
	 * @throws InvalidArgumentException If $value is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 * @throws InvalidConditionException If the type of $value doesn't
	 *                                   sufficiently match $this' type.
	 */
	function eq($value) {
		return $this->comparison_condition($value, Condition::K_EQ);
	}

	/**
	 * True if the value of $this is not equal to $value.
	 *
	 * @param mixed $value Either a string, boolean, or integral
	 *                     constant or Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is not equal to $value.
	 *
	 * @throws InvalidArgumentException If $value is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 * @throws InvalidConditionException If the type of $value doesn't
	 *                                   sufficiently match $this' type.
	 */
	function neq($value) {
		return $this->comparison_condition($value, Condition::K_NEQ);
	}

	/**
	 * True if the value of $this is less than $value.
	 *
	 * The less than operator in mysql can be used for lexicographical
	 * comparisons, integer comparisons and even boolean comparisons
	 * (true == 1), so all those types are accepted here.
	 *
	 * @param mixed $value Either a string, boolean, or integral
	 *                     constant or Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is less than $value.
	 *
	 * @throws InvalidArgumentException If $value is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 * @throws InvalidConditionException If the type of $value doesn't
	 *                                   sufficiently match $this' type.
	 */
	function lt($value) {
		return $this->comparison_condition($value, Condition::K_LT);
	}

	/**
	 * True if the value of $this is less than or equal to $value.
	 *
	 * The less than or equal to operator in mysql can be used for
	 * lexicographical comparisons, integer comparisons and even boolean
	 * comparisons (true == 1), so all those types are accepted here.
	 *
	 * @param mixed $value Either a string, boolean, or integral
	 *                     constant or Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is less than or equal to $value.
	 *
	 * @throws InvalidArgumentException If $value is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 * @throws InvalidConditionException If the type of $value doesn't
	 *                                   sufficiently match $this' type.
	 */
	function le($value) {
		return $this->comparison_condition($value, Condition::K_LE);
	}

	/**
	 * True if the value of $this is greater than $value.
	 *
	 * The greater than operator in mysql can be used for lexicographical
	 * comparisons, integer comparisons and even boolean comparisons
	 * (true == 1), so all those types are accepted here.
	 *
	 * @param mixed $value Either a string, boolean, or integral
	 *                     constant or Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is greater than $value.
	 *
	 * @throws InvalidArgumentException If $value is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 * @throws InvalidConditionException If the type of $value doesn't
	 *                                   sufficiently match $this' type.
	 */
	function gt($value) {
		return $this->comparison_condition($value, Condition::K_GT);
	}

	/**
	 * True if the value of $this is greater than or equal to $value.
	 *
	 * The greater than or equal to operator in mysql can be used for
	 * lexicographical comparisons, integer comparisons and even boolean
	 * comparisons (true == 1), so all those types are accepted here.
	 *
	 * @param mixed $value Either a string, boolean, or integral
	 *                     constant or Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is greater than or equal to $value.
	 *
	 * @throws InvalidArgumentException If $value is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 * @throws InvalidConditionException If the type of $value doesn't
	 *                                   sufficiently match $this' type.
	 */
	function ge($value) {
		return $this->comparison_condition($value, Condition::K_GE);
	}

	/**
	 * True if the value of $this is in the set $set.
	 *
	 * Note that constants referred to below include Conditions created by the
	 * value() leaf function.
	 *
	 * @param array $value An array of string, boolean, or integral constants.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is in $set.
	 *
	 * @throws InvalidArgumentException If any element of $set is not a string,
	 *                                  boolean, or integral constant, or
	 *                                  if $set is not an non-empty array.
	 * @throws InvalidConditionException If $this' type isn't sufficiently
	 *                                   similar to any of the elements' types.
	 */
	function in($set) {
	}

	/**
	 * The bitwise AND of $this and $mask, integral.
	 *
	 * @param int $mask A bitmask to bitwise AND $this with.
	 *
	 * @return A Condition object of integral type representing the result.
	 *
	 * @throws InvalidArgumentException If $str is not an integral constant or
	 *                                  Condition object.
	 */
	function bit_and($mask) {
	}

	function get_type() {
		return $this->type;
	}

	private function comparison_condition($value, $kind) {
		$type = Condition::determine_type($value);
		$this->assert_inhabits($type);

		if (!is_a($value, "recipe\\orm\\condition\\Condition")) {
			$value = new Condition($value, $type, Condition::K_CONST);
		}
		return new Condition(null, Condition::T_BOOL, $kind,
		                     [$this, $value]);
	}

	private function string_condition($func_name, $str, $before, $after) {
		if (!is_string($str))
			throw new orm\InvalidArgumentException("Non-string passed to $func_name()");
		$this->assert_inhabits(Condition::T_STR);

		$escaped = Condition::escape_str_control($str);

		$str_check = new Condition($before . $escaped . $after,
		                           Condition::T_STR, Condition::K_CONST);
		$result = new Condition(null, Condition::T_BOOL, Condition::K_CONTAINS,
		                        [$this, $str_check]);

		return $result;
	}

	private function add_child($child) {
		if (is_a($child, "recipe\\orm\\condition\\Condition")) {
			array_push($this->children, $child);
		} else {
			throw new orm\InvalidArgumentException("Non-condition added as child of Condition");
		}
	}

	private function assert_inhabits($type) {
		// a column can be any type
		if ($this->type == Condition::T_COL || $type == Condition::T_COL)
			return;

		// a strict match is obviously fine
		if ($this->type == $type)
			return;

		// integers can often be reasonably used as booleans
		if ($type == Condition::T_BOOL && $this->type == Condition::T_INT)
			return;

		throw new InvalidConditionException("Condition is not $type typed");
	}

	private static function determine_type($value) {
		if (is_a($value, "recipe\\orm\\condition\\Condition")) {
			return $value->get_type();
		} else {
			if (orm\castable_int($value)) {
				$type = Condition::T_INT;
			} else if (is_bool($value)) {
				$type = Condition::T_BOOL;
			} else if (is_string($value)) {
				$type = Condition::T_STR;
			} else {
				throw new orm\InvalidArgumentException("Non-int, bool or string value passed");
			}
			return $type;
		}
	}

	private static function escape_str_control($str) {
		return str_replace("_", "\\_",
		         str_replace("%", "\\%",
		           str_replace("\\", "\\\\", $str)));
	}
}

class InvalidConditionException extends \Exception { }

?>
