<?php // $Id$

// this code snippet disables the QuizPort module on new installations of Moodle 2.0
// it is called from within the "upgrade_activity_modules()" function (in lib/adminlib.php)

// block direct access to this script
if (empty($CFG)) {
    die;
}
// standardize Moodle API to Moodle 2.0
require_once($CFG->dirroot.'/mod/quizport/legacy.php');

if (empty($DB)) {
    global $DB; // Moodle <= 1.9
}

if ($CFG->majorrelease>=2.0 && empty($CFG->quizport_initialdisable)) {
    // hide this module by default on Moodle 2.0 and later
    if (! $DB->count_records('quizport')) {
        $DB->set_field('modules', 'visible', 0, array('name'=>'quizport'));
        set_config('quizport_initialdisable', 1);
    }
}

$defaults = array(
    'quizport_enablemymoodle' => 1,
    'quizport_enablecache' => 1,
    'quizport_enablecron' => 0,
    'quizport_enableswf' => 1,
    'quizport_enableobfuscate' => 1,
    'quizport_frameheight' => 85,
    'quizport_lockframe' => 0,
    'quizport_storedetails' => 0,
    'quizport_maxeventlength' => 5
);
?>