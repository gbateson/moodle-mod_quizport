<?php
class quizport_file_html extends quizport_file {
    // returns name of quiz that is displayed to user
    function get_name($textonly=true) {
        if (! isset($this->name)) {
            $this->name = '';
            $this->title = '';

            if (! $this->get_filecontents()) {
                // empty file - shouldn't happen !!
                return false;
            }
            if (preg_match('|<h(\d)[^>]>(.*?)</h\\1>|is', $this->filecontents, $matches)) {
                $this->name = trim(strip_tags($this->title));
                $this->title = trim($matches[1]);
            }
            if (! $this->name) {
                if (preg_match('|<title[^>]*>(.*?)</title>|is', $this->filecontents, $matches)) {
                    $this->name = trim(strip_tags($matches[1]));
                    if (! $this->title) {
                        $this->title = trim($matches[1]);
                    }
                }
            }
        }
        if ($textonly) {
            return $this->name;
        } else {
            return $this->title;
        }
    }

    function get_title() {
        return $this->get_name(false);
    }

    // returns the introduction text for a quiz
    function get_entrytext() {
        if (! isset($this->entrytext)) {
            $this->entrytext = '';

            if (! $this->get_filecontents()) {
                // empty file - shouldn't happen !!
                return false;
            }
            if (preg_match('/<(div|p)[^>]*>\s*(.*?)\s*<\/\\1>/is', $this->filecontents, $matches)) {
                $this->entrytext .= '<\\1>'.$matches[2].'</\\1>';
            }
        }
        return $this->entrytext;
    }
} // end class
?>