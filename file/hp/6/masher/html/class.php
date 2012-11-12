<?php
class quizport_file_hp_6_masher_html extends quizport_file_hp_6_masher {
    function is_unitfile() {
        if (empty($this->fullpath)) {
            // no filepath given - shouldn't happen
            return false;
        }

        if (! preg_match('/\.(htm|html)$/', $this->filename)) {
            // this is not an html file
            return false;
        }
        if (! is_readable($this->fullpath)) {
            // file does not exist or is not readable
            return false;
        }

        if (! $this->get_filecontents()) {
            // empty file - shouldn't happen !!
            return false;
        }

        if (! preg_match('/<!\-\- Made with executable version HotPotatoes: Masher Version [^>]* \-\->/is', $this->filecontents)) {
            // not a masher index.htm
            return false;
        }

        if (! preg_match('/<ul class="Index"[^>]*>(.*?)<\/ul>/is', $this->filecontents, $list)) {
            // no list of links - shouldn't happen
            return false;
        }

        // isolate items from the list of links
        if (! preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $list[1], $items)) {
            // empty list - shouldn't happen
            return false;
        }

        $quizzes = array();

        // isolate the URL and title for each of the items from the list of links
        foreach ($items[1] as $item) {
            if (preg_match('/<a href="(.*?)">(.*?)<\/a>/is', $item, $matches)) {
                if (is_readable($this->dirname.'/'.$matches[1])) {
                    // N.B. $matches[2] holds the quiz name
                    $quizzes[] = dirname($this->filepath).'/'.$matches[1];
                }
            }
        }

        if (count($quizzes)) {
            return $quizzes;
        } else {
            return false;
        }
    }
}
?>
