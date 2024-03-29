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
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 * Based off of a template @ http://docs.moodle.org/dev/Backup_1.9_conversion_for_developers
 *
 * @package    mod_cypherlab
 * @copyright  2011 Aparup Banerjee <aparup@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * cypherlab conversion handler
 */
class moodle1_mod_cypherlab_handler extends moodle1_mod_handler {

    /** @var moodle1_file_manager */
    protected $fileman = null;

    /** @var int cmid */
    protected $moduleid = null;

    /** @var string current subplugin being processed*/
    private $currentsubpluginname = null;

    /** @var array of a moodle1_cypherlab_[subplugin_name]_handler instances */
    private $subpluginhandlers = null;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the path /MOODLE_BACKUP/COURSE/MODULES/MOD/cypherlab does not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path(
                'cypherlab', '/MOODLE_BACKUP/COURSE/MODULES/MOD/cypherlab',
                array(
                    'renamefields' => array(
                        'description' => 'intro',
                        'format' => 'introformat',
                    )
                )
            )
            //@todo process user data
            //new convert_path('cypherlab_submission', '/MOODLE_BACKUP/COURSE/MODULES/MOD/cypherlab/SUBMISSIONS/SUBMISSION')
        );
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/cypherlab
     * data available
     */
    public function process_cypherlab($data) {
        global $CFG;

        // get the course module id and context id
        $instanceid     = $data['id'];
        $cminfo         = $this->get_cminfo($instanceid);
        $this->moduleid = $cminfo['id'];
        $contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

        //store cypherlab type for possible subplugin conversions.
        $this->currentsubpluginname = $data['cypherlabtype'];

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_cypherlab');

        // convert course files embedded into the intro
        $this->fileman->filearea = 'intro';
        $this->fileman->itemid   = 0;
        $data['intro'] = moodle1_converter::migrate_referenced_files($data['intro'], $this->fileman);

        // convert the introformat if necessary
        if ($CFG->texteditors !== 'textarea') {
            $data['intro'] = text_to_html($data['intro'], false, false, true);
            $data['introformat'] = FORMAT_HTML;
        }

        // start writing cypherlab.xml
        $this->open_xml_writer("activities/cypherlab_{$this->moduleid}/cypherlab.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $this->moduleid,
            'modulename' => 'cypherlab', 'contextid' => $contextid));
        $this->xmlwriter->begin_tag('cypherlab', array('id' => $instanceid));

        foreach ($data as $field => $value) {
            if ($field <> 'id') {
                $this->xmlwriter->full_tag($field, $value);
            }
        }

        //after writing the cypherlab type element, let the subplugin add on whatever it wants.
        $this->handle_cypherlab_subplugin($data);

        $this->xmlwriter->begin_tag('submissions');

        return $data;
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/cypherlab/SUBMISSIONS/SUBMISSION
     * data available
     */
    public function process_cypherlab_submission($data) {
        //@todo process user data
        //$this->write_xml('submission', $data, array('/submission/id'));
    }

    /**
     * This handles calls to subplugin conversion classes.
     * called from <cypherlabTYPE> within process_cypherlab()
     */
    public function handle_cypherlab_subplugin($data) {
        $handler = $this->get_subplugin_handler($this->currentsubpluginname);
        $this->log('Instantiated cypherlab subplugin handler for '.$this->currentsubpluginname.'.', backup::LOG_DEBUG);
        $handler->use_xml_writer($this->xmlwriter);

        $this->log('Processing cypherlab subplugin handler callback for '.$this->currentsubpluginname.'.', backup::LOG_DEBUG);
        $handler->append_subplugin_data($data);
    }

    /**
     * This is executed when we reach the closing </MOD> tag of our 'cypherlab' path
     */
    public function on_cypherlab_end() {
        // finish writing cypherlab.xml
        $this->xmlwriter->end_tag('submissions');
        $this->xmlwriter->end_tag('cypherlab');
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();

        // write inforef.xml
        $this->open_xml_writer("activities/cypherlab_{$this->moduleid}/inforef.xml");
        $this->xmlwriter->begin_tag('inforef');
        $this->xmlwriter->begin_tag('fileref');
        foreach ($this->fileman->get_fileids() as $fileid) {
            $this->write_xml('file', array('id' => $fileid));
        }
        $this->xmlwriter->end_tag('fileref');
        $this->xmlwriter->end_tag('inforef');
        $this->close_xml_writer();
    }

    /// internal implementation details follow /////////////////////////////////

    /**
     * Factory method returning the handler of the given cypherlab subplugin
     *
     * @param string $subplugin the name of the subplugin
     * @throws moodle1_convert_exception
     * @return moodle1_cypherlab_subplugin_handler the instance of the handler
     */
    protected function get_subplugin_handler($subplugin) {
        global $CFG; // we include other files here

        if (is_null($this->subpluginhandlers)) {
            $this->subpluginhandlers = array();
            $subplugins = core_component::get_plugin_list('cypherlab');
            foreach ($subplugins as $name => $dir) {
                $handlerfile  = $dir.'/backup/moodle1/lib.php';
                $handlerclass = "moodle1_mod_cypherlab_{$name}_subplugin_handler";
                if (!file_exists($handlerfile)) {
                    continue;
                }
                require_once($handlerfile);

                if (!class_exists($handlerclass)) {
                    throw new moodle1_convert_exception('missing_handler_class', $handlerclass);
                }
                $this->log('preparing cypherlab subplugin handler', backup::LOG_DEBUG, $handlerclass);
                $this->subpluginhandlers[$name] = new $handlerclass($this, $name);
                if (!$this->subpluginhandlers[$name] instanceof moodle1_cypherlab_subplugin_handler) {
                    throw new moodle1_convert_exception('wrong_handler_class', get_class($this->subpluginhandlers[$name]));
                }
            }
        }

        if (!isset($this->subpluginhandlers[$subplugin])) {
            // Generic handling, prevents breaking conversion process...
            $this->subpluginhandlers[$subplugin] = new moodle1_cypherlab_unsupported_subplugin_handler($this, $subplugin);
        }

        return $this->subpluginhandlers[$subplugin];
    }
}


/**
 * Base class for the cypherlab subplugin handler
 * Extend this for your own subplugin conversion handling purposes.
 */
abstract class moodle1_cypherlab_subplugin_handler extends moodle1_submod_handler {

    /**
     * @param moodle1_mod_handler $cypherlabhandler the handler of a module we are subplugin of
     * @param string $subpluginname the name of the subplugin
     */
    public function __construct(moodle1_mod_handler $cypherlabhandler, $subpluginname) {
        parent::__construct($cypherlabhandler, 'cypherlab', $subpluginname);
    }

    /**
     * Provides a xml_writer instance to this cypherlab subplugin type handler
     *
     * @param xml_writer $xmlwriter
     */
    public function use_xml_writer(xml_writer $xmlwriter) {
        $this->xmlwriter = $xmlwriter;
    }

    /**
     * a call back (entry point) to the subplugin conversion handler class.
     * $data are the elements of <cypherlab>, any (@todo sub paths containing subplugindata isn't handed through).
     */

    public function append_subplugin_data($data) {
        // an example that does nothing - you'll do nothing if you don't overide it
        return false;

        //you will probably want to do stuff with $this->xmlwriter here (within your overridden method) to write plugin specific data.
    }
}

/**
 * This class handles subplugins that do not exist or that are not supported
 */
class moodle1_cypherlab_unsupported_subplugin_handler extends moodle1_cypherlab_subplugin_handler {
}
