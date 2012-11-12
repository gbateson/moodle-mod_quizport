<?php
class quizport_file_hp_6_jmatch_html_intro extends quizport_file_hp_6_jmatch_html {
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // not an html file
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty or non-existant file
            return false;
        }

        if (! strpos($this->filecontents, '<div class="Feedback" id="DivIntroPage">')) {
            // not a jmatch-intro file
            return false;
        }

        if (strpos($this->filecontents, '<div id="MainDiv" class="StdDiv">')) {
            if (strpos($this->filecontents, '<div id="MatchDiv" align="center">')) {
                // jmatch-intro v6
                return $this;
            }
        }

        if (strpos($this->filecontents, '<div class="StdDiv" id="CheckButtonDiv">')) {
            if (strpos($this->filecontents, 'F = new Array();')) {
                if (strpos($this->filecontents, 'D = new Array();')) { // overkill?
                    // jmatch-intro v6+ (drag and drop)
                    return $this;
                }
            }
        }

        // not a jmatch-intro file
        return false;
    }
}
?>