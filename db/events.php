<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of cypherlab event handlers
 *
 * @package mod_cypherlab
 * @category event
 * @copyright 1999 onwards Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$handlers = array();

/* List of events thrown from cypherlab module

cypherlab_finalize_sent - object course, object user, object cm, object cypherlab, fileareaname
cypherlab_file_sent     - object course, object user, object cm, object cypherlab, object file

*/