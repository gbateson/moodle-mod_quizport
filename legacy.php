<?php // $Id$

// Moodle's major release number (2.0, 1.9, etc)
if (isset($GLOBALS['release'])) {
    // an admin user doing an update
    $CFG->majorrelease = floatval($GLOBALS['release']);
} else if (isset($CFG->release)) {
    $CFG->majorrelease = floatval($CFG->release);
} else {
    $CFG->majorrelease = 2.0;
}

// add missing functions, classes, constants and strings
$missingstrings = array();
switch ($CFG->majorrelease) {
    case 1.0: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_10.php');
    case 1.1: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_11.php');
        $CFG->pixpath = $CFG->wwwroot.'/pix';
        $CFG->modpixpath = $CFG->wwwroot.'/mod';
    case 1.2: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_12.php');
    case 1.3: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_13.php');
    case 1.4: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_14.php');
    case 1.5: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_15.php');
    case 1.6: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_16.php');
    case 1.7: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_17.php');
        $CFG->legacylibdir = $CFG->dirroot.'/mod/quizport/legacy/lib';
        $CFG->legacypixpath = $CFG->wwwroot.'/mod/quizport/legacy/pix';
    case 1.8: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_18.php');
    case 1.9: require_once($CFG->dirroot.'/mod/quizport/legacy/mdl_19.php');
}
if (count($missingstrings)) {
    require_once($CFG->dirroot.'/mod/quizport/legacy/missingstrings.php');
    quizport_add_missingstrings($missingstrings);
}
unset($missingstrings);
?>