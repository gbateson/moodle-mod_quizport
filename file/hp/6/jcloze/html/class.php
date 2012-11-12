<?php
class quizport_file_hp_6_jcloze_html extends quizport_file_hp_6_jcloze {
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

        if (! strpos($this->filecontents, '<div id="ClozeDiv">')) {
            // not a jcloze file
            return false;
        }

        if (strpos($this->filecontents, 'function Create_StateArray()')) {
            // a Rottmeier DropDown or FindIt file
            return false;
        }

        if (strpos($this->filecontents, 'function Add_GlossFunctionality()')) {
            // a Rottmeier JGloss file
            return false;
        }

        return $this;
    }
}
?>