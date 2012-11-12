<?php
class quizport_file_hp_6_sequitur_xml extends quizport_file_hp_6_sequitur {
    function is_quizfile() {
        if (preg_match('/\.sqt$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
}
?>