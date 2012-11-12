<?php
class quizport_file_hp_6_jmatch_html_flashcard extends quizport_file_hp_6_jmatch_html {
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // not an html file
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty or non-existant file
            return false;
        }

        if (strpos($this->filecontents, '<div id="MainDiv" class="StdDiv">')) {
            if (strpos($this->filecontents, '<table class="FlashcardTable" border="0" cellspacing="0">')) {
                // hp6 jmatch flashcard
                return $this;
            }
        }

        // not a jmatch-intro file
        return false;
    }
}
?>