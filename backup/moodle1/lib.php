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
 *
 * @package    mod
 * @subpackage quizport
 * @copyright  2012 Gordon Bateson <gordonbateson@gmail.com>
 *             credit and thanks to Robin de vries <robin@celp.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * QuizPort conversion handler
 */
class moodle1_mod_quizport_handler extends moodle1_mod_handler {

    /** maximum size of moodle.xml that can be read using file_get_contents (256 KB) */
    const SMALL_FILESIZE = 256000;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * We are misusing this method as it offers a convenient way to
     * search for "quizport" in moodle.xml and replace with "taskchain"
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {

        // these are the substitutions we want to make in moodle.xml
        $search   = array('<NAME>quizport</NAME>',  'mod/quizport:',  '<TYPE>quizport</TYPE>',  '<NAME>mod_quizport_',  '<NAME>quizport_',  '<ITEMMODULE>quizport</ITEMMODULE>',  '<MODTYPE>quizport</MODTYPE>');
        $replace  = array('<NAME>taskchain</NAME>', 'mod/taskchain:', '<TYPE>taskchain</TYPE>', '<NAME>mod_taskchain_', '<NAME>taskchain_', '<ITEMMODULE>taskchain</ITEMMODULE>', '<MODTYPE>taskchain</MODTYPE>');

        $tempdir = $this->converter->get_tempdir_path();
        $moodle_xml = $tempdir.'/moodle.xml';
        $moodle_tmp = $tempdir.'/moodle.tmp';

        if (file_exists($moodle_xml)) {
            if (filesize($moodle_xml) < self::SMALL_FILESIZE) {
                $contents = file_get_contents($moodle_xml);
                $contents = str_replace($search, $replace, $contents);
                file_put_contents($moodle_xml, $contents);
            } else {
                // xml file is large, maybe entire Moodle 1.9 site,
                // so we process it one line at a time (slower but safer)
                if ($file_xml = fopen($moodle_xml, 'r')) {
                    if ($file_tmp = fopen($moodle_tmp, 'w')) {
                        while (! feof($file_xml)) {
                            if ($line = fgets($file_xml)) {
                                fputs($file_tmp, str_replace($search, $replace, $line));
                            }
                        }
                        fclose($file_tmp);
                    }
                    fclose($file_xml);
                }
                if ($file_xml && $file_tmp) {
                    unlink($moodle_xml);
                    rename($moodle_tmp, $moodle_xml);
                }
            }
        }

        return array();
    }
}
