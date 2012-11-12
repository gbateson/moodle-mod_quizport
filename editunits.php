<?php // $Id$
/**
 * Edit units in a QuizPort unit
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// set $QUIZPORT object
require_once('class.php');

// get rebuild_course_cache() from "course/lib.php"
// (needed if "showpopup" or "popupoptions" change)
require_once($CFG->dirroot.'/course/lib.php');

add_to_log($course->id, 'quizport', 'editunits', "editunits.php?id=$course->id");

// if cancel, do something here

// get all units (and quizports) in this course
$QUIZPORT->get_units();

if ($setasdefault = optional_param('setasdefault', 0, PARAM_INT)) {
    if ($QUIZPORT->get_units() && array_key_exists($setasdefault, $QUIZPORT->units)) {
        quizport_set_preferences('unit', $QUIZPORT->units[$setasdefault]);
    } else {
        $setasdefault = 0; // invalid $setasdefault - shouldn't happen !!
    }
}

// process action, if there is one
$count = 0;
switch ($QUIZPORT->action) {

    case 'applydefaults':
        confirm_sesskey();

        $units = array();

        $applydefaults = optional_param('applydefaults', '', PARAM_ALPHA);
        switch ($applydefaults) {

            case 'selectedunits':

                $selectunit = optional_param('selectunit', 0, PARAM_INT);
                if ($selectunit && is_array($selectunit) && $QUIZPORT->get_units()) {
                    foreach ($selectunit as $unitid => $selected) {
                        if (is_numeric($unitid) && array_key_exists($unitid, $QUIZPORT->units) && $selected) {
                            $units[$unitid] = &$QUIZPORT->units[$unitid];
                        }
                    }
                }
               break;

            case 'filteredunits':

                $ids = ''; // quizport ids
                if ($filter = optional_param('coursefilter', 0, PARAM_INT)) {
                    if ($courses = $DB->get_records('course', array('id'=>$filter))) {
                        if ($instances = get_all_instances_in_courses('quizport', $courses, $USER->id)) {
                            foreach ($instances as $instance) {
                                $ids .= ($ids ? ',' : '').$instance->id;
                            }
                        }
                    }
                }
                if ($ids) {
                    $tables = ''
                        ."{quizport} q,"
                        ."{quizport_units} qu"
                    ;
                    $select = ''
                        .'q.id IN ('.$ids.') '
                        .'AND q.id = qu.parentid '
                        .'AND qu.parenttype = '.QUIZPORT_PARENTTYPE_ACTIVITY
                    ;
                    if ($filter = $QUIZPORT->get_filter('unitnamefilter', 'q.name')) {
                        $select .= " AND $filter";
                    }
                    if (! $units = $DB->get_records_sql("SELECT qu.*, q.name FROM $tables WHERE $select")) {
                        $units = array();
                    }
                }
                break;

        } // end switch $applydefaults

        // these text fields will have to be escaped
        $textfields = array(
            'entrytext', 'exittext', 'popupoptions', 'password', 'subnet'
        );

        $defaults = array();
        $selectcolumn = optional_param('selectcolumn', 0, PARAM_ALPHANUM);
        if ($selectcolumn && is_array($selectcolumn)) {
            foreach ($selectcolumn as $column) {
                $value = get_user_preferences('quizport_unit_'.$column, null);
                if (! is_null($value)) {
                    $defaults[$column] = $value;
                }
            }
        }

        $d = new stdClass();
        $d_unitid = 0;
        $siblingquizids = array('conditionquizid', 'nextquizid');
        $rebuild_course_caches = array();

        foreach ($units as $unitid => $unit) {
            $updateunit = false;
            $updatecondition = true;
            $allow_groupid = null;
            foreach ($defaults as $name => $value) {
                // check to see if conditions need to be updated
                if ($name=='preconditions' || $name=='postconditions') {
                    if ($value==0 || $value==$unitid) {
                        // this is the current unit so don't delete its conditions
                        continue;
                    }

                    if ($name=='preconditions') {
                        $type = QUIZPORT_CONDITIONTYPE_PRE;
                    } else {
                        $type = QUIZPORT_CONDITIONTYPE_POST;
                    }

                    // get the default quizzes and conditions
                    // these conditions will be applied to the target quizzes
                    if (empty($d->$type)) {
                        $d->$type = new stdClass();
                        if (! $d->$type->quizzes = $DB->get_records('quizport_quizzes', array('unitid'=>$value), 'sortorder', 'id, id AS quizid')) {
                            $d->$type->quizzes = array();
                        }
                        $d->$type->max = count($d->$type->quizzes);
                        $d->$type->halfway = floor($d->$type->max / 2);
                        $d->$type->quizids = array_keys($d->$type->quizzes);
                        foreach ($d->$type->quizids as $id) {
                            $d->$type->quizzes[$id]->conditions = array();
                        }
                        if (! $d->$type->conditions = $DB->get_records_select('quizport_conditions', 'conditiontype='.$type.' AND quizid IN ('.implode(',', $d->$type->quizids).')')) {
                            $d->$type->conditions = array();
                        }
                        foreach ($d->$type->conditions as $id=>$condition) {
                            $d->$type->quizzes[$condition->quizid]->conditions[] = &$d->$type->conditions[$id];
                        }
                    }

                    // get the target quizzes and conditions
                    // if possible, the condition ids will be reused
                    if (! $t_quizzes = $DB->get_records('quizport_quizzes', array('unitid'=>$unitid), 'sortorder', 'id, id AS quizid')) {
                        $t_quizzes = array();
                    }
                    $t_max = count($t_quizzes);
                    $t_halfway = floor($t_max / 2);
                    $t_quizids = array_keys($t_quizzes);
                    if (! $t_conditions = $DB->get_records_select('quizport_conditions', 'conditiontype='.$type.' AND quizid IN ('.implode(',', $t_quizids).')', null, '', 'id, id AS conditionid')) {
                        $t_conditions = array();
                    }
                    $t_conditionids = array_keys($t_conditions);
                    unset($t_conditions);

                    // $i is an index on $t_conditionids
                    $i_max = count($t_conditionids);
                    $i = 0;

                    // $t_index is an index on $t_quizids
                    // $d_index is an index on $d->$type->quizids
                    for ($t_index=0; $t_index<$t_max; $t_index++) {

                        if ($t_index<$t_halfway) {
                            // we're in the first half of $t_quizzes
                            // map $t onto corresponding $d from the start of
                            // the array, but don't go above the halway point
                            $d_index = min($d->$type->halfway, $t_index);
                        } else {
                            // we're in the second half of $t_quizzes
                            // map $t onto corresponding $d from the end of
                            // the array, but don't go below the halway point
                            $d_index = max($d->$type->halfway, $d->$type->max - ($t_max - $t_index));
                        }

                        // current target quiz
                        $t_quizid = $t_quizids[$t_index];
                        $t_quiz = &$t_quizzes[$t_quizid];

                        // current default quiz
                        $d_quizid = $d->$type->quizids[$d_index];
                        $d_quiz = &$d->$type->quizzes[$d_quizid];

                        foreach (array_keys($d_quiz->conditions) as $id) {
                            $abort_condition = false;

                            $condition = clone($d_quiz->conditions[$id]);

                            // check and adjust $siblingquizids
                            // i.e. conditionquizid and nextquizid
                            foreach ($siblingquizids as $siblingquizid) {
                                if ($condition->$siblingquizid<=0) {
                                    // do nothing
                                } else if ($condition->$siblingquizid==$d_quizid) {
                                    // post-conditions usually do this
                                    $condition->$siblingquizid = $t_quizid;
                                } else {
                                    // $siblingquizid is a specific quiz in the default unit
                                    // so get the corresponding quiz from the target unit
                                    $d_search = array_search($condition->$siblingquizid, $d->$type->quizids);
                                    if ($d_search===false) {
                                        // something's wrong - this condition refers
                                        // to a deleted quiz or a quiz in another unit
                                        $abort_condition = true;
                                        break;
                                    }
                                    if ($d_search<$d->$type->halfway) {
                                        $t_search = min($t_halfway, $d_search);
                                    } else {
                                        $t_search = max($t_halfway, $t_max - ($d->$type->max - $d_search));
                                    }
                                    $condition->$siblingquizid = $t_quizids[$t_search];
                                }
                            }
                            if ($abort_condition) {
                                continue;
                            }

                            // check and adjust groupid
                            if ($condition->groupid) {
                                // $unitid and $value must be in the same course
                                if ($d_unitid != $value) {
                                    // unitid for pre and post conditions has changed
                                    // so force recalculation of $allow_groupid
                                    $allow_groupid = null;
                                }
                                if (is_null($allow_groupid)) {
                                    $select = ''
                                        .'id = ('
                                            // the quizport for the target unit
                                            .'SELECT parentid FROM {quizport_units} qu '
                                            .'WHERE id='.$unitid.' AND parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY
                                        .') AND course = ('
                                            // the course of the quizport for the default unit
                                            .'SELECT course FROM {quizport} WHERE id = ('
                                                // the quizport for the default unit
                                                .'SELECT parentid FROM {quizport_units} qu '
                                                .'WHERE id='.$value.' AND parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY
                                            .')'
                                        .')'
                                    ;
                                    $allow_groupid = $DB->record_exists_select('quizport', $select);
                                }
                                if (! $allow_groupid) {
                                    // condition refers to a group in different course :-(
                                    $abort_condition = true;
                                }
                            }
                            if ($abort_condition) {
                                continue;
                            }

                            // adjust quizid
                            $condition->quizid = $t_quizid;

                            if ($i<$i_max) {
                                // reuse target condition id
                                $condition->id = $t_conditionids[$i++];
                                if (! update_record('quizport_conditions', $condition)) {
                                    debugging(get_string('error_updaterecord', 'quizport', "quizport_conditions (id=$condition->id)"), DEBUG_DEVELOPER);
                                }
                            } else {
                                // add new condition
                                unset($condition->id);
                                if (! insert_record('quizport_conditions', $condition)) {
                                    debugging(get_string('error_insertrecord', 'quizport', 'quizport_conditions'), DEBUG_DEVELOPER);
                                }
                            }
                        } // end foreach $d_quiz->conditions

                        // finish off this $t_quiz
                        unset($t_quiz);
                        unset($d_quiz);

                    } // end for $t_quizzes[$t]

                    // remove surplus $t_conditionids
                    $ids = array();
                    while ($i<$i_max) {
                        $ids[] = $t_conditionids[$i++];
                    }
                    if ($ids = implode(',', $ids)) {
                        if (! $DB->delete_records_select('quizport_conditions', "id IN ($ids)")) {
                            debugging(get_string('error_deleterecords', 'quizport', "quizport_conditions (id IN $ids)"), DEBUG_DEVELOPER);
                        }
                    }

                    // finsh off applying conditions to this unit
                    $d_unitid = $value;
                    unset($t_quizzes);
                    unset($t_quizids);
                    unset($t_conditionids);
                    $updatecondition = true;

                // check to see if this unit record needs to be updated
                } else if (isset($unit->$name) && $unit->$name != $value) {
                    $updateunit = true;
                    $unit->$name = $value;
                    if ($name=='showpopup' || $name=='popupoptions') {
                        if (isset($unit->course)) {
                            $courseid = $unit->course;
                        } else if ($unit->parenttype==QUIZPORT_PARENTTYPE_ACTIVITY) {
                            $courseid = $QUIZPORT->quizports[$unit->parentid]->course;
                        } else {
                            $courseid = 0;
                        }
                        if ($courseid) {
                            $rebuild_course_caches[$courseid] = true;
                        }
                    }
                    // if this unit is in the current unit,
                    // then ensure the new value is displayed in browser
                    if (isset($QUIZPORT->units[$unit->id])) {
                        $QUIZPORT->units[$unit->id]->$name = $value;
                    }
                }
            } // end foreach $defaults

            if ($updateunit) {
                if ($CFG->majorrelease<=1.9) {
                    foreach ($textfields as $textfield) {
                        $unit->$textfield = addslashes($unit->$textfield);
                    }
                }
                // update unit record in database
                if (! $DB->update_record('quizport_units', $unit)) {
                    print_error('error_updaterecord', 'quizport', '', 'quizport_units');
                }
                if ($CFG->majorrelease<=1.9) {
                    foreach ($textfields as $textfield) {
                        $unit->$textfield = stripslashes($unit->$textfield);
                    }
                }
                $count++;
            } else if ($updatecondition) {
                $count++;
            }
        } // end foreach $units

        foreach (array_keys($rebuild_course_caches) as $courseid) {
            rebuild_course_cache($courseid);
        }

        // finish off "applydefaults" action
        unset($d_unit);
        unset($d);
        break;

} // end switch $action

// display the page
$QUIZPORT->print_page();
?>