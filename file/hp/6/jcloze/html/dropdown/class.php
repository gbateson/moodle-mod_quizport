<?php
class quizport_file_hp_6_jcloze_html_dropdown extends quizport_file_hp_6_jcloze_html {
    function is_quizfile() {
        if (! preg_match('/\.html?$/', $this->filename)) {
            // wrong file type
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

        if (! strpos($this->filecontents, 'function Create_StateArray()')) {
            // not a Rottmeier file
            return false;
        }

        if (! strpos($this->filecontents, 'this.GapLocked = false;')) {
            // not a Rottmeier DropDown file
            return false;
        }

        return $this;
    }
}
?>