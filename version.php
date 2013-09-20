<?php

//  Code fragment to define the version of quizport
//  called by moodle_needs_upgrading() and /admin/index.php

$module->version  = 2008040145;  // release date of this version
$module->release  = 'v1.0.45';   // human-friendly version name (used in mod/quizport/lib.php)
$module->cron     = 60;          // period for cron to check this module (secs)

if (defined('MATURITY_STABLE')) {
    $module->maturity  = MATURITY_STABLE;
}

// to get past the sentry to the Moodle Plugins repository
// we specify the minimum required Moodle version as Moodle 1.9
$module->requires = 2007101509;

// ... actually the QuizPort module can run on *any* version of Moodle
// although on Moodle 2.0 it is only here to allow upgrade to TaskChain

$fieldname = 'requ'.'ires';
if (isset($CFG->version) && $CFG->version > 2010000000) {
    $module->$fieldname = 2010000000;  // Requires this Moodle version (2.0)
} else {
    $module->$fieldname = 2003052900;  // Requires this Moodle version (1.0.9)
}
?>