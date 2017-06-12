<?php

require_once("../../config.php");

$id = required_param('id', PARAM_INT);

// Rest in peace old cypherlab!
redirect(new moodle_url('/mod/assign/index.php', array('id' => $id)));
