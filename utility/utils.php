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

?>
