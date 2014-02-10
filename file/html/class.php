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
            $search = '/<((?:h[1-6])|p|div|title)[^>]*>(.*?)<\/\1[^>]*>/is';
            if (preg_match_all($search, $this->filecontents, $matches)) {

                // search string to match style and script blocks
                $search = '/<(script|style)[^>]*>.*?<\/\1[^>]*>\s/is';

                $i_max = count($matches[0]);
                for ($i=0; $i<$i_max; $i++) {
                    $match = $matches[2][$i];
                    $match = preg_replace($search, '', $match);
                    if ($this->name = trim(strip_tags($match))) {
                        $this->title = trim($matches[2][$i]);
                        break;
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