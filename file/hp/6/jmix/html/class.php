<?php
class quizport_file_hp_6_jmix_html extends quizport_file_hp_6_jmix {
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // not an html file
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty or non-existant file
            return false;
        }

        if (! strpos($this->filecontents, '<div id="MainDiv" class="StdDiv">')) {
            if (! strpos($this->filecontents, '<div class="StdDiv" id="CheckButtonDiv">')) {
                // not an hp6 file
                return false;
            }
        }

        if (! strpos($this->filecontents, '<div id="SegmentDiv">')) { // drop-down
            if (! strpos($this->filecontents, '<div id="Drop')) { // drag-and-drop
                // not a jmix file
                return false;
            }
        }

        // following check is not strict enough to filter out Sequitur
        //if (! strpos($this->filecontents, 'var Segments = new Array();')) { // drag-and-drop
        //    return false;
        //}

        return $this;
    }
}
?>