<?php
class quizport_file_hp_6_sequitur_html extends quizport_file_hp_6_sequitur {
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // wrong file type
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty or non-existant file
            return false;
        }

        if (! strpos($this->filecontents, '<div class="StdDiv">')) {
            // not an tt3 file
            return false;
        }

        if (! strpos($this->filecontents, '<div id="ChoiceDiv">')) {
            // not an sequitur file
            return false;
        }

        if (! strpos($this->filecontents, '<div class="Story" id="Story">')) {
            // not an sequitur file
            return false;
        }

        return $this;
    }
}
?>