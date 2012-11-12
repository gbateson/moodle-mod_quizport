<?php
// get the standard Moodle mediaplugin filter
require_once($CFG->dirroot.'/filter/mediaplugin/filter.php');

// get the parent class (=quizport_mediafilter)
require_once($CFG->dirroot.'/mod/quizport/mediafilter/class.php');

class quizport_mediafilter_quizport extends quizport_mediafilter {

    function mediaplugin_filter($courseid, $text, $options=array()) {
        global $CFG, $QUIZPORT;

        // Keep track of the id of the current quiz
        // so that eolas_fix.js is only included once in each quiz
        // Note: the cron script calls this method for multiple quizzes
        static $eolas_fix_applied = 0;

        if (! is_string($text)) {
            // non string data can not be filtered anyway
            return $text;
        }
        $newtext = $text; // fullclone is slow and not needed here

        foreach ($this->media_filetypes as $filetype) {

            // set $adminsetting, the name of the $CFG setting, if any, which enables/disables filtering of this file type
            $adminsetting = '';
            if (preg_match('/^[a-z]+$/', $filetype)) {
                $quizport_enable = 'quizport_enable'.$filetype;
                $filter_mediaplugin_enable = 'filter_mediaplugin_enable_'.$filetype;

                if (isset($CFG->$quizport_enable)) {
                    $adminsetting = $quizport_enable;
                } else if (isset($CFG->$filter_mediaplugin_enable)) {
                    $adminsetting = $filter_mediaplugin_enable;
                }
            }

            // set $search and $replace strings
            $search = '/<a.*?href="([^"?>]*\.'.$filetype.'[^">]*)"[^>]*>.*?<\/a>/ise';
            if ($adminsetting=='' || $CFG->$adminsetting) {
                // filtering of this file type is allowed
                $replace = '$this->quizport_mediaplugin_filter($filetype, "\\0", "\\1", $options)';
            } else {
                // filtering of this file type is disabled
                $replace = '"\\1<br />".get_string("error_disabledfilter", "quizport", "'.$adminsetting.'")';
            }

            // replace $search text with $replace text
            $newtext = preg_replace($search, $replace, $newtext, -1, $count);

            if ($count>0) {
                break;
            }
        }

        if (is_null($newtext) || $newtext==$text) {
            // error or not filtered
            return $text;
        }

        if ($eolas_fix_applied==$QUIZPORT->quiz->id) {
            // do nothing - the external javascripts have already been included for this quiz
        } else {
            $newtext .= "\n".'<script type="text/javascript" src="'.$CFG->wwwroot.'/lib/ufo.js"></script>';
            $newtext .= "\n".'<script type="text/javascript" src="'.$CFG->wwwroot.'/filter/mediaplugin/eolas_fix.js" defer="defer"></script>';
            $eolas_fix_applied = $QUIZPORT->quiz->id;
        }

        return $newtext;
    }

    function quizport_mediaplugin_filter($filetype, $link, $mediaurl, $options, $quote="'") {
        if ($quote) {
            // fix quotes that were escaped by preg_replace
            $link = str_replace('\\'.$quote, $quote, $link);
            $mediaurl = str_replace('\\'.$quote, $quote, $mediaurl);
        }

        // get a valid $player name
        if (isset($options['player'])) {
            $player = $options['player'];
        } else {
            $player = '';
        }
        if ($player=='') {
            $player = $this->defaultplayer;
        } else if (! array_key_exists($player, $this->players)) {
            debugging('Invalid media player requested: '.$player);
            $player = $this->defaultplayer;
        }

        // merge player options
        if ($player==$this->defaultplayer) {
            $options = array_merge($this->players[$player]->options, $options);
        } else {
            $options = array_merge($this->players[$this->defaultplayer]->options, $this->players[$player]->options, $options);
        }

        // generate content for required player
        $content = $this->players[$player]->generate($filetype, $link, $mediaurl, $options);

        return $content;
    }
}
?>