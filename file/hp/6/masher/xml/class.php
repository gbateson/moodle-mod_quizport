<?php
class quizport_file_hp_6_masher_xml extends quizport_file_hp_6_masher {
    function is_unitfile() {
        if (empty($this->fullpath)) {
            // there's no path
            return false;
        }

        if (! preg_match('/\.(jms)$/', $this->filename)) {
            // this is not a Hot Potatoes masher file
            return false;
        }

        if (! is_readable($this->fullpath)) {
            // file does not exist or is not readable
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty file - shouldn't happen !!
            return false;
        }

        // create xml tree for this file
        $xml = xmlize($this->filecontents, 0);

        // check we have the expected xml tree structure
        $root_tag = $this->hbs_software.'-'.$this->hbs_quiztype.'-file';
        if (empty($xml[$root_tag]['#']['hotpot-file-list'][0]['#'])) {
            // could not detect config file settings for this Hot Potatoes quiz - shouldn't happen !!
            return false;
        }

        // shortcut to the file list in this Hot Potatoes masher file
        $filelist = &$xml[$root_tag]['#']['hotpot-file-list'][0]['#'];
        $quizzes = array();

        $i = 0;
        while (isset($filelist['hotpot-file'][$i]['#'])) {

            // shortcut to the file info for this file
            $file = &$filelist['hotpot-file'][$i]['#'];
            // $file['data-file-name'][0]['#'] : C:\My Documents\QuizPorts\jquiz.jqz
            // $file['output-file-name'][0]['#']: jquiz.htm
            // $file['next-ex-file-name'][0]['#'] : jquiz-v6.htm
            // $file['output-type'][0]['#'] : 2

            $filename = '';
            if (isset($file['output-file-name'][0]['#'])) {
                if (is_readable($this->dirname.'/'.$file['output-file-name'][0]['#'])) {
                    $filename = $file['output-file-name'][0]['#'];
                }
            }
            if (! $filename) {
                // output file was not found, so look for the original data file
                if (isset($file['data-file-name'][0]['#'])) {
                    if (is_readable($this->dirname.'/'.$file['data-file-name'][0]['#'])) {
                        $filename = $file['data-file-name'][0]['#'];
                    }
                }
            }
            if ($filename) {
                // add filepath
                $quizzes[] = dirname($this->filepath).'/'.$filename;
            } else {
                print $i.'invalid file name: output:'.$file['output-file-name'][0]['#'].', input:'.$file['data-file-name'][0]['#'].'<br />';
            }
            $i++;
        } // end while

        if (count($quizzes)) {
            return $quizzes;
        } else {
            return false;
        }
    }

    function get_name($textonly=true) {
        if (! isset($this->name)) {
            $this->name = '';
            $this->title = '';

            if (! $this->xml_get_filecontents()) {
                // could not detect Hot Potatoes quiz type - shouldn't happen !!
                return false;
            }

            $this->title = $this->xml_value('unit-title');
            $this->name = trim(striptags($this->title));
        }
        if ($textonly) {
            return $this->name;
        } else {
            return $this->title;
        }
    }

    function get_title() {
        return $this->get_name(false);
    }
}
?>