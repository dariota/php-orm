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

	switch (strtolower(strval($input))) {
	case 'true':
		$input = true;
		return true;
	case 'false':
		$input = false;
		return true;
	default:
		return false;
	}
}

?>
