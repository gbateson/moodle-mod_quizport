<?php
class quizport_file_hp_6_jcross_xml extends quizport_file_hp_6_jcross {
    function is_quizfile() {
        if (preg_match('/\.jcw$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
}
?>