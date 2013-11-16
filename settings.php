<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

/** Include required files */
require_once($CFG->dirroot.'/mod/quizport/lib.local.php');

// admin_setting_xxx classes are defined in "lib/adminlib.php"
// new admin_setting_configcheckbox($name, $visiblename, $description, $defaultsetting);

// show Quizports on MyMoodle page (default=1)
$settings->add(
    new admin_setting_configcheckbox('quizport_enablemymoodle', get_string('enablemymoodle', 'quizport'), get_string('configenablemymoodle', 'quizport'), 1)
);

// enable caching of browser content for each quiz (default=1)
$str = get_string('clearcache', 'quizport');
$url = $CFG->wwwroot.'/mod/quizport/db/clearcache.php';
if (function_exists('element_to_popup_window')) {
    $link = '<span class="smalltext" style="white-space: nowrap;">'.element_to_popup_window('link', $url, '', $str, 300, 600, $str, '', true).'</span>';
} else {
    $link = '<span class="smalltext" style="white-space: nowrap;"><a href="'.$url.'" onclick="'."this.target='_blank'".'">'.$str.'</a></span>';
}
$settings->add(
    new admin_setting_configcheckbox('quizport_enablecache', get_string('enablecache', 'quizport'), get_string('configenablecache', 'quizport').' '.$link, 1)
);
unset($str, $url, $link);

// restrict cron job to certain hours of the day (default=never)
$timezone = get_user_timezone_offset();
if (abs($timezone) > 13) {
    $timezone = 0;
} else if ($timezone>0) {
    $timezone = $timezone - 24;
}
$options = array();
for ($i=0; $i<=23; $i++) {
    $options[($i - $timezone) % 24] = gmdate('H:i', $i * HOURSECS);
}
$settings->add(
    new admin_setting_configmultiselect('quizport_enablecron', get_string('enablecron', 'quizport'), get_string('configenablecron', 'quizport'), array(), $options)
);
unset($timezone, $options);

// enable embedding of swf media objects inquizport quizzes (default=1)
$settings->add(
    new admin_setting_configcheckbox('quizport_enableswf', get_string('enableswf', 'quizport'), get_string('configenableswf', 'quizport'), 1)
);

// bodystyles
$options = array(
    QUIZPORT_BODYSTYLES_BACKGROUND => get_string('bodystylesbackground', 'quizport'),
    QUIZPORT_BODYSTYLES_COLOR      => get_string('bodystylescolor',      'quizport'),
    QUIZPORT_BODYSTYLES_FONT       => get_string('bodystylesfont',       'quizport'),
    QUIZPORT_BODYSTYLES_MARGIN     => get_string('bodystylesmargin',     'quizport')
);
$settings->add(
    new admin_setting_configmultiselect('quizport_bodystyles', get_string('bodystyles', 'quizport'), get_string('configbodystyles', 'quizport'), array(), $options)
);

// enable obfuscation of javascript in html files (default=1)
$settings->add(
    new admin_setting_configcheckbox('quizport_enableobfuscate', get_string('enableobfuscate', 'quizport'), get_string('configenableobfuscate', 'quizport'), 1)
);

// quizport navigation frame height (default=85)
$settings->add(
    new admin_setting_configtext('quizport_frameheight', get_string('frameheight', 'quizport'), get_string('configframeheight', 'quizport'), 85, PARAM_INT, 4)
);

// lock quizport navigation frame so it is not scrollable (default=0)
$settings->add(
    new admin_setting_configcheckbox('quizport_lockframe', get_string('lockframe', 'quizport'), get_string('configlockframe', 'quizport'), 0)
);

// store raw xml details of QuizPort quiz attempts (default=1)
$settings->add(
    new admin_setting_configcheckbox('quizport_storedetails', get_string('storedetails', 'quizport'), get_string('configstoredetails', 'quizport'), 0)
);

// maximum duration of a single calendar event (default=5 mins)
$settings->add(
    new admin_setting_configtext('quizport_maxeventlength', get_string('maxeventlength', 'quizport'), get_string('configmaxeventlength', 'quizport'), 5, PARAM_INT, 4)
);
?>