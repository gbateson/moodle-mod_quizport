<?php
class quizport_file_hp_6_rhubarb_html extends quizport_file_hp_6_rhubarb {
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

        if (! strpos($this->filecontents, '<form id="Rhubarb" action="" onsubmit="CheckGuess(); return false">')) {
            // not an rhubarb file
            return false;
        }

        return $this;
    }
}
?>