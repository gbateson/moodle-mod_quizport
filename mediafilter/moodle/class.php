<?php
// get the standard Moodle mediaplugin filter
require_once($CFG->dirroot.'/filter/mediaplugin/filter.php');

// get the parent class (=quizport_mediafilter)
require_once($CFG->dirroot.'/mod/quizport/mediafilter/class.php');

class quizport_mediafilter_moodle extends quizport_mediafilter {
    // methods
    function mediaplugin_filter($courseid, $text, $options) {
        global $CFG, $QUIZPORT;
        static $eolas_fix_applied = 0;

        // insert media players using Moodle's standard mediaplugin filter
        $newtext = mediaplugin_filter($courseid, $text);

        if ($newtext==$text) {
            // do nothing
        } else if ($eolas_fix_applied==$QUIZPORT->quiz->id) {
            // eolas_fix.js and ufo.js have already been added for this quiz
        } else {
            if ($eolas_fix_applied==0) {
                // 1st quiz - eolas_fix.js was added by filter/mediaplugin/filter.php
            } else {
                // 2nd (or later) quiz - e.g. we are being called by quizport_cron()
                $newtext .= '<script defer="defer" src="'.$CFG->wwwroot.'/filter/mediaplugin/eolas_fix.js" type="text/javascript"></script>';
            }
            $newtext .= '<script type="text/javascript" src="'.$CFG->wwwroot.'/lib/ufo.js"></script>';
            $eolas_fix_applied = $QUIZPORT->quiz->id;
        }

        return $newtext;
    }
}
?>