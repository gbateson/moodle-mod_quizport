<?php // $Id$
/**
 * Edit quizzes in a QuizPort unit
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// set $QUIZPORT object
require_once('class.php');

add_to_log($course->id, 'quizport', 'editquizzes', "editquizzes.php?id=$coursemodule->id", $quizport->id, $coursemodule->id);

// if cancel, do something here

if ($setasdefault = optional_param('setasdefault', 0, PARAM_INT)) {
    if ($QUIZPORT->get_quizzes() && array_key_exists($setasdefault, $QUIZPORT->quizzes)) {
        quizport_set_preferences('quiz', $QUIZPORT->quizzes[$setasdefault]);
    } else {
        $setasdefault = 0;
    }
}

// process action, if there is one
$count = 0;
switch ($QUIZPORT->action) {

    case 'renumberquizzes':
        confirm_sesskey();

        $sortorder = optional_param('sortorder', 0, PARAM_INT);
        if ($sortorder && is_array($sortorder) && $QUIZPORT->get_quizzes()) {
            asort($sortorder);
            $sortordervalue = 0;
            foreach (array_keys($sortorder) as $quizid) {
                if (is_numeric($quizid) && array_key_exists($quizid, $QUIZPORT->quizzes)) {
                    $sortordervalue += $QUIZPORT->sortorderincrement;
                    $QUIZPORT->quizzes[$quizid]->sortorder = $sortordervalue;
                    $DB->set_field('quizport_quizzes', 'sortorder', $sortordervalue, array('id'=>$quizid));
                }
            }
            // "uasort" maintains the id => record correlation (whereas "usort" does not)
            uasort($QUIZPORT->quizzes, 'quizport_usort_sortorder_asc');
        }
        break;

    case 'addquizzes':
        confirm_sesskey();

        $addquizzes = optional_param('addquizzes', '', PARAM_ALPHA);
        switch ($addquizzes) {
            case 'start':
                $afterquizid = -1;
                break;
            case 'end':
                $afterquizid = 0;
                break;
            case 'after':
                $afterquizid = optional_param('addquizzesafterquizid', 0, PARAM_INT);
                break;
        }
        $params = array('unitid' => $QUIZPORT->unit->id, 'afterquizid' => $afterquizid);
        redirect($QUIZPORT->format_url('editquiz.php', '', $params));
        break;

    case 'movequizzes':
        confirm_sesskey();

        $selectedquizids = array();
        $selectquiz = optional_param('selectquiz', 0, PARAM_INT);
        if ($selectquiz && is_array($selectquiz) && $QUIZPORT->get_quizzes()) {
            foreach ($selectquiz as $quizid => $selected) {
                if (is_numeric($quizid) && array_key_exists($quizid, $QUIZPORT->quizzes) && $selected) {
                    $selectedquizids[] = $quizid;
                }
            }
        }

        if (count($selectedquizids)) {
            $quizids = array_diff(array_keys($QUIZPORT->quizzes), $selectedquizids);

            $movequizzes = optional_param('movequizzes', '', PARAM_ALPHA);
            switch ($movequizzes) {

                case 'start':
                    $quizids = array_merge($selectedquizids, $quizids);
                    break;

                case 'end':
                    $quizids = array_merge($quizids, $selectedquizids);
                    break;

                case 'after':
                    $quizid = optional_param('movequizzesafterquizid', 0, PARAM_INT);
                    $i = array_search($quizid, $quizids);
                    if (is_numeric($i)) {
                        $quizids = array_merge(
                            array_slice($quizids, 0, ($i+1)), $selectedquizids, array_slice($quizids, ($i+1))
                        );
                    }
                    break;

                case 'myquizport':
                    $ok = false;
                    if ($quizportid = optional_param('movequizzesquizportid', 0, PARAM_INT)) {
                        if ($QUIZPORT->get_myquizports() && array_key_exists($quizportid, $QUIZPORT->myquizports)) {
                            $ok = true;
                        }
                    }
                    if (! $ok) {
                        $QUIZPORT->print_error(get_string('error_getrecord', 'quizport', "quizport (id=$quizportid)"));
                    }
                    if (! $unitid = $DB->get_field('quizport_units', 'id', array('parenttype'=>QUIZPORT_PARENTTYPE_ACTIVITY, 'parentid'=>$quizportid))) {
                        $QUIZPORT->print_error(get_string('error_getrecord', 'quizport', "quizport_units (parentid=$quizportid)"));
                    }
                    $tables = ''
                        ."{quizport_units} qu,"
                        ."{quizport_quizzes} qq"
                    ;
                    $select = ''
                        .'qu.parentid = '.$quizportid.' '
                        .'AND qu.parenttype = '.QUIZPORT_PARENTTYPE_ACTIVITY.' '
                        .'AND qu.id = qq.unitid'
                    ;
                    $sortordervalue = $DB->count_records_sql(
                        "SELECT MAX(qq.sortorder) FROM $tables WHERE $select"
                    );
                    foreach ($selectedquizids as $quizid) {
                        $sortordervalue += $QUIZPORT->sortorderincrement;
                        $QUIZPORT->quizzes[$quizid]->sortorder = $sortordervalue;
                        $QUIZPORT->quizzes[$quizid]->unitid = $unitid;
                        if (! $DB->update_record('quizport_quizzes', $QUIZPORT->quizzes[$quizid])) {
                            print_error('error_updaterecord', 'quizport', '', 'quizport_quizzes');
                        }
                        unset($QUIZPORT->quizzes[$quizid]);
                    }
                    break;

            } // end switch $movequizzes

            $sortordervalue = 0;
            foreach ($quizids as $quizid) {
                $sortordervalue += $QUIZPORT->sortorderincrement;
                $QUIZPORT->quizzes[$quizid]->sortorder = $sortordervalue;
                $DB->set_field('quizport_quizzes', 'sortorder', $sortordervalue, array('id'=>$quizid));
            }
            // "uasort" maintains the id => record correlation (where "usort" does not)
            uasort($QUIZPORT->quizzes, 'quizport_usort_sortorder_asc');
        }
        break;

    case 'applydefaults':
        confirm_sesskey();

        $quizzes = array();

        $applydefaults = optional_param('applydefaults', '', PARAM_ALPHA);
        switch ($applydefaults) {

            case 'selectedquizzes':

                $selectquiz = optional_param('selectquiz', 0, PARAM_INT);
                if ($selectquiz && is_array($selectquiz) && $QUIZPORT->get_quizzes()) {
                    foreach ($selectquiz as $quizid => $selected) {
                        if (is_numeric($quizid) && array_key_exists($quizid, $QUIZPORT->quizzes) && $selected) {
                            $quizzes[$quizid] = &$QUIZPORT->quizzes[$quizid];
                        }
                    }
                }
                break;

            case 'filteredquizzes':

                $ids = ''; // quizport ids
                if ($filter = optional_param('coursefilter', 0, PARAM_INT)) {
                    $courses = $DB->get_records('course', array('id'=>$filter));
                } else {
                    $courses = $DB->get_records('course');
                }
                if ($courses) {
                    if ($instances = get_all_instances_in_courses('quizport', $courses, $USER->id)) {
                        foreach ($instances as $instance) {
                            $ids .= ($ids ? ',' : '').$instance->id;
                        }
                        unset ($instances);
                    }
                    unset ($courses);
                }
                if ($ids) {
                    $tables = ''
                        ."{quizport} q,"
                        ."{quizport_units} qu,"
                        ."{quizport_quizzes} qq"
                    ;
                    $select = ''
                        .'q.id IN ('.$ids.') '
                        .'AND q.id = qu.parentid '
                        .'AND qu.parenttype = '.QUIZPORT_PARENTTYPE_ACTIVITY.' '
                        .'AND qu.id = qq.unitid'
                    ;
                    if ($filter = $QUIZPORT->get_filter('quiznamefilter', 'qq.name')) {
                        $select .= " AND $filter";
                    }
                    if ($filter = $QUIZPORT->get_filter('quiztypefilter', 'qq.sourcetype')) {
                        $select .= " AND $filter";
                    }
                    if ($filter = $QUIZPORT->get_filter('filenamefilter', 'qq.sourcefile')) {
                        $select .= " AND $filter";
                    }
                    if (! $quizzes = $DB->get_records_sql("SELECT qq.* FROM $tables WHERE $select")) {
                        $quizzes = array();
                    }
                }
                break;

        } // end switch $applydefaults

        // these text fields will have to be escaped
        $textfields = array(
            'name', 'sourcefile', 'sourcetype', 'configfile', 'stoptext',
            'usemediafilter', 'studentfeedbackurl', 'password', 'subnet'
        );

        $defaults = array();
        $selectcolumn = optional_param('selectcolumn', 0, PARAM_ALPHANUM);
        if ($selectcolumn && is_array($selectcolumn)) {
            foreach ($selectcolumn as $column) {
                $value = get_user_preferences('quizport_quiz_'.$column, null);
                if (! is_null($value)) {
                    $defaults[$column] = $value;
                }
            }
        }
        foreach ($quizzes as $quizid => $quiz) {
            $updatequiz = false;
            $updatecondition = false;
            foreach ($defaults as $name => $value) {
                // check to see if conditions need to be updated
                if ($name=='preconditions' || $name=='postconditions') {
                    if ($value==$quizid) {
                        // this is the current quiz so don't delete its conditions
                    } else {
                        if ($name=='preconditions') {
                            $conditiontype = QUIZPORT_CONDITIONTYPE_PRE;
                        } else {
                            $conditiontype = QUIZPORT_CONDITIONTYPE_POST;
                        }

                        // get old condition ids, if any, so that we can reuse them
                        if ($conditionids = $DB->get_records_select('quizport_conditions', "quizid=$quizid AND conditiontype=$conditiontype", null, 'id', 'id, id AS conditionid')) {
                            $conditionids = array_keys($conditionids);
                        } else {
                            $conditionids = array();
                        }

                        // $i is an index on $conditionids
                        $i_max = count($conditionids);
                        $i = 0;

                        $selectquizids = "SELECT id FROM {quizport_quizzes} WHERE unitid=$quiz->unitid";
                        $select = ''
                            ."quizid=$value AND conditiontype=$conditiontype AND ("
                                .'conditionquizid<=0'
                                .' OR conditionquizid=quizid'
                                ." OR conditionquizid IN ($selectquizids)"
                            .') AND ('
                                .'nextquizid<=0'
                                .' OR nextquizid=quizid'
                                ." OR nextquizid IN ($selectquizids)"
                            .')'
                        ;
                        if ($conditions = $DB->get_records_select('quizport_conditions', $select)) {
                            foreach ($conditions as $condition) {
                                if ($condition->conditionquizid==$condition->quizid) {
                                    $condition->conditionquizid = $quizid;
                                }
                                if ($condition->nextquizid==$condition->quizid) {
                                    $condition->nextquizid = $quizid;
                                }
                                $condition->quizid = $quizid;

                                if ($i<$i_max) {
                                    $condition->id = $conditionids[$i++];
                                    if (! $DB->update_record('quizport_conditions', $condition)) {
                                        print_error('error_updaterecord', 'quizport', '', "quizport_conditions (id=$condition->id)");
                                    }
                                } else {
                                    unset($condition->id);
                                    if (! $id = $DB->insert_record('quizport_conditions', $condition)) {
                                        print_error('error_insertrecord', 'quizport', '', 'quizport_conditions');
                                    }
                                }
                            }
                        }

                        // remove surplus $conditionids
                        $ids = array();
                        while ($i<$i_max) {
                            $ids[] = $conditionids[$i++];
                        }
                        if ($ids = implode(',', $ids)) {
                            if (! $DB->delete_records_select('quizport_conditions', "id IN ($ids)")) {
                                debugging(get_string('error_deleterecords', 'quizport', "quizport_conditions (id IN $ids)"), DEBUG_DEVELOPER);
                            }
                        }
                        $updatecondition = true;
                    }
                // check to see if this quiz record needs to be updated
                } else if (isset($quiz->$name) && $quiz->$name != $value) {
                    $updatequiz = true;
                    $quiz->$name = $value;
                    // if this quiz is in the current unit,
                    // then ensure the new value is displayed in browser
                    if (isset($QUIZPORT->quizzes[$quizid])) {
                        $QUIZPORT->quizzes[$quizid]->$name = $value;
                    }
                }
            }
            if ($updatequiz) {
                if ($CFG->majorrelease<=1.9) {
                    foreach ($textfields as $textfield) {
                        $quiz->$textfield = addslashes($quiz->$textfield);
                    }
                }
                // update quiz record in database
                if (! $DB->update_record('quizport_quizzes', $quiz)) {
                    print_error('error_updaterecord', 'quizport', '', 'quizport_quizzes');
                }
                if ($CFG->majorrelease<=1.9) {
                    foreach ($textfields as $textfield) {
                        $quiz->$textfield = stripslashes($quiz->$textfield);
                    }
                }
                $count++;
            } else if ($updatecondition) {
                $count++;
            }
        }
        unset($quizid, $quiz);
        break;

    case 'deletequizzes':
        confirm_sesskey();

        // $sortorder is an array( $quizid => $selected)
        $selectquiz = optional_param('selectquiz', 0, PARAM_INT);

        if ($selectquiz && is_array($selectquiz) && $QUIZPORT->get_quizzes()) {
            $quizids = '';
            foreach ($selectquiz as $quizid => $selected) {
                if (is_numeric($quizid) && array_key_exists($quizid, $QUIZPORT->quizzes) && $selected) {
                    $quizids .= ($quizids ? ',' : '').$quizid;
                    unset($QUIZPORT->quizzes[$quizid]);
                    $count++;
                }
            }
            if ($quizids) {
                quizport_delete_quizzes($quizids);
            }
        }
        break;

} // end switch $action

// display the page
$QUIZPORT->print_page();
?>