<?php

namespace recipe\orm\condition;

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
 *                                   integral.
 */
function not($condition) {
}

class Condition {

	/**
	 * True if the string value of $this contains $str anywhere.
	 *
	 * Escapes control characters such as \, % and _.
	 * Comparison is case-insensitive.
	 *
	 * @param mixed $str Either a string, or a string valued Condition object.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to contains $str anywhere.
	 *
	 * @throws InvalidArgumentException If $str is neither a string or string-
	 *                                  typed Condition.
	 * @throws InvalidConditionException If $this is not a string-typed
	 *                                   Condition.
	 */
	function contains($str) {
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
	}

	/**
	 * True if the value of $this is null.
	 *
	 * @return A Condition object which is true if the column or value referred
	 *         to is null.
	 */
	function null_value() {
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
	 * @throws InvalidArgumentException If $str is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 */
	function eq($value) {
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
	 * @throws InvalidArgumentException If $str is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 */
	function neq($value) {
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
	 * @throws InvalidArgumentException If $str is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 */
	function lt($value) {
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
	 * @throws InvalidArgumentException If $str is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 */
	function le($value) {
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
	 * @throws InvalidArgumentException If $str is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 */
	function gt($value) {
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
	 * @throws InvalidArgumentException If $str is not a string, boolean, or
	 *                                  integral constant or Condition object.
	 */
	function ge($value) {
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

}

?>
