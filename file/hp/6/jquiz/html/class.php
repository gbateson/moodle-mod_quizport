<?php
class quizport_file_hp_6_jquiz_html extends quizport_file_hp_6_jquiz {
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
            // not an hp6 file
            return false;
        }
        if (! strpos($this->filecontents, '<div id="QNav" class="QuestionNavigation">')) {
            // not a jquiz file
            return false;
        }
        return $this;
    }
}
?>