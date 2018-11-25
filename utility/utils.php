<?php

namespace recipe\orm;

function castable_int(&$input) {
	$stringified = strval($input);

	if (ctype_digit($stringified)) {
		$input = (int) $stringified;
		return true;
	} else {
		return false;
	}
}

function castable_bool(&$input) {
	if (is_bool($input))
		return true;

	# accepts ints so it roundtrips correctly
	switch (strtolower(strval($input))) {
	case '1':
	case 'true':
		$input = true;
		return true;
	case '0':
	case 'false':
		$input = false;
		return true;
	default:
		return false;
	}
}

?>
