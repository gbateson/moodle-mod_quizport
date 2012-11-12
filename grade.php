<?php
require_once('../../config.php');

$id   = required_param('id', PARAM_INT);

if (! $cm = get_coursemodule_from_id('quizport', $id)) {
    error('Course Module ID was incorrect');
}

require_login($cm->course, false, $cm);

if (has_capability('mod/quizport:viewreports', get_context_instance(CONTEXT_MODULE, $cm->id))) {
    redirect('report.php?id='.$cm->id);
} else {
    redirect('view.php?id='.$cm->id);
}
?>