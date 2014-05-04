<?php

//  Code fragment to define the version of quizport
//  called by moodle_needs_upgrading() and /admin/index.php

if (isset($plugin) && is_object($plugin)) {
    $saveplugin = null;
} else {
    if (isset($plugin)) {
        $saveplugin = $plugin;
    } else {
        $saveplugin = false;
    }
    $plugin = new stdClass();
}

$plugin->component = 'mod_quizport'; // for Moodle 2.x
$plugin->version   = 2008040160;     // release date of this version
$plugin->release   = 'v1.0.60';      // human-friendly version name (used in quizport/output/class.php)
$plugin->cron      = 3600;           // period for cron to check this module (in seconds)

if (defined('MATURITY_STABLE')) {
    $plugin->maturity  = MATURITY_STABLE;
}

// to get past the sentry to the Moodle Plugins repository
// we specify the minimum required Moodle version as Moodle 1.9
$plugin->requires = 2007101509;

// ... actually the QuizPort module can run on *any* version of Moodle
// although on Moodle 2.x it is only here to allow upgrade to TaskChain

if (isset($CFG->version) && $CFG->version > 2010000000) {
    if (file_exists($CFG->dirroot.'/mod/taskchain') && $DB->record_exists('modules', array('name' => 'taskchain'))) {
        // trigger upgrade to Moodle 2.x (TaskChain)
        $plugin->{'version'}  = 2014050160;
        $plugin->{'release'}  = '2014.05.01 (60)';
    }
    $plugin->{'requires'} = 2010000000;  // Moodle 2.0
} else {
    $plugin->{'requires'} = 2003052900;  // Moodle 1.0.9
}

if (isset($saveplugin)) {
    $module = $plugin;
    if ($saveplugin) {
        $plugin = $saveplugin;
    } else {
        unset($plugin);
    }
}
unset($saveplugin);
?>