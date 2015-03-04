<?php

//  Code fragment to define the version of quizport
//  called by moodle_needs_upgrading() and /admin/index.php

if (floatval($GLOBALS['CFG']->release) <= 2.6) {
    $plugin = new stdClass();
}

$plugin->component = 'mod_quizport'; // for Moodle 2.x
$plugin->version   = 2008040183;     // release date of this version
$plugin->release   = 'v1.0.83';      // human-friendly version name (used in quizport/output/class.php)
$plugin->cron      = 3600;           // period for cron to check this module (in seconds)

if (defined('MATURITY_STABLE')) {
    $plugin->maturity = MATURITY_STABLE;
}

// to get past the sentry to the Moodle Plugins repository
// we specify the minimum required Moodle version as Moodle 1.9
$plugin->requires = 2007101509;

// ... actually the QuizPort module can run on *any* version of Moodle
// although on Moodle 2.x it is only here to allow upgrade to TaskChain

if (floatval($GLOBALS['CFG']->release) >= 2.0) {
    if (isset($this) && get_class($this)=='core_plugin_manager') {
        // Moodle >= 2.6 "lib/classes/plugin_manager.php"
        $has_taskchain = (isset($plugs) && isset($plugs['taskchain']));
    } else {
        // Moodle >= 2.0 "lib/upgradelib.php"
        $has_taskchain = file_exists($GLOBALS['CFG']->dirroot.'/mod/taskchain');
    }
    if ($has_taskchain) {
        // trigger upgrade to Moodle 2.x (TaskChain)
        $plugin->{'version'} = 2014052083;
        $plugin->{'release'} = '2014.05.20 (83)';
    }
    // Moodle >= 2.6 does not pass the "version" property
    // to the QuizPort upgrade script in "db/upgrade.php"
    // so we create out own property and use that instead
    $plugin->{'pluginversion'} = $plugin->{'version'};
    $plugin->{'requires'} = 2010000000;  // Moodle 2.0
} else {
    // Moodle <= 1.9
    $plugin->{'requires'} = 2003052900;  // Moodle 1.0.9
}

if (floatval($GLOBALS['CFG']->release) <= 2.6) {
    $module = clone($plugin);
}
?>