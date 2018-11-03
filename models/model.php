<?php

abstract class Model {
	var $id;
	var $belongs;
	var $has;

	function __construct($id) {
		
	}

	function save() {
		echo get_class($this);
	}

	function reload() {

	}

	function _table() {

	}

/*	function __call($name, $args) {
		// for assocations
	}
 */
}

?>
