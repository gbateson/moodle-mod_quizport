<?php
class quizport_file_hp_6_jquiz_xml extends quizport_file_hp_6_jquiz {
    function is_quizfile() {
        if (preg_match('/\.jqz$/', $this->filename)) {
            return $this;
        } else {
            return false;
        }
    }
}
?>