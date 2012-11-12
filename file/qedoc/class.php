<?php
class quizport_file_qedoc extends quizport_file {
    function is_quizfile() {
        //if (preg_match('/http:\/\/www\.qedoc.net\/qqp\/jnlp\/\w+\.jnlp/i', $this->url)) {
        //    // e.g. http://www.qedoc.net/qqp/jnlp/PLJUB_019.jnlp
        //    return $this;
        //}
        if (preg_match('/http:\/\/www\.qedoc.(?:com|net)\/library\/\w+\.zip/i', $this->url)) {
            // e.g. http://www.qedoc.net/library/PLJUB_019.zip
            return $this;
        }
        return false;
    }

    function get_name() {
        return $this->filename;
    }

    function get_title() {
        return $this->filename;
    }
}
?>