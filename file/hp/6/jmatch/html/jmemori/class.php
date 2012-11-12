<?php
class quizport_file_hp_6_jmatch_html_jmemori extends quizport_file_hp_6_jmatch_html {
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // not an html file
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty or non-existant file
            return false;
        }

        if (! strpos($this->filecontents, 'div id="MainDiv" class="StdDiv"')) {
            // not a HP file
            return false;
        }

        if (! strpos($this->filecontents, 'div id="MatchDiv"')) {
            // not a jmatch file
            return false;
        }

        if (strpos($this->filecontents, 'function CheckPair(id){')) {
            if (strpos($this->filecontents, 'M = new Array();')) {
                if (strpos($this->filecontents, 'clickarray = new Array();')) {
                    // jmemori
                    return $this;
                }
            }
        }

        // not a jmemori file
        return false;
    }
}
?>