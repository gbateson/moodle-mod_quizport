<?php
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/quizport/legacy.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM, SITEID), $USER->id);

$title = get_string('clearcache', 'quizport');
print_header($title);
print_heading($title);

if ($confirm = optional_param('confirm', 0, PARAM_INT)) {
    $DB->delete_records('quizport_cache');
    $count_cache = 0;
} else {
    $count_cache = $DB->count_records('quizport_cache');
}
$count_quizzes = $DB->count_records('quizport_quizzes');

print_box_start('generalbox', 'notice');
print '<table style="margin:auto"><tbody>'."\n";
print '<tr><th style="text-align:right;">'.get_string('quizzes', 'quizport').':</th><td>'.$count_quizzes.'</td></tr>'."\n";
print '<tr><th style="text-align:right;">'.get_string('cacherecords', 'quizport').':</th><td>'.$count_cache.'</td></tr>'."\n";
if ($count_cache) {
    print '<tr><td colspan="2" style="text-align:center;">';
    print '<form action="'.$CFG->wwwroot.'/mod/quizport/db/clearcache.php" method="post">';
    print '<fieldset>';
    print '<input type="hidden" value="1" name="confirm" />';
    print '<input type="submit" value="'.get_string('confirm').'" />';
    print '</fieldset>';
    print '</td></tr>'."\n";
} else {
    print '<tr><td colspan="2" style="text-align:center;">'.get_string('clearedcache', 'quizport').'</td></tr>'."\n";
}
print '</tbody></table>'."\n";
print_box_end();

close_window_button();

if ($CFG->majorrelease<=1.8) {
    print "\n</div>\n</body>\n</html>";
} else {
    print_footer('empty');
}
?>