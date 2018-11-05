<?php
/**
 * Provides a single point of entry to require the framework. Assumes a config
 * file exists one level up from itself, containing a variable $SECRETS which
 * contains the absolute path to a file declaring $HOST, $USERNAME, $PASSWORD,
 * $DATABASE, which allow connection to a mysql DB.
 */

namespace recipe\orm;

define("F_ROOT", dirname(__FILE__));
require_once F_ROOT . "/models/model.php";
require_once "../config.php";
require_once "$SECRETS";

?>
