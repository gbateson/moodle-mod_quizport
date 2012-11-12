<?php // $Id$

function migrate2utf8_quizport_cache($fields, $crash, $debug, $maxrecords, $done, $tablestoconvert) {
    global $CFG, $globallang;
    if ($globallang) {
        $fromenc = $globallang;
    } else {
        $fromenc = get_original_encoding($CFG->lang, null, null);
    }
    if ($fromenc != 'utf-8' && $fromenc != 'UTF-8') {
        execute_sql("TRUNCATE TABLE {$CFG->prefix}quizport_cache");
    }
    return true;
}

function migrate2utf8_quizport_name($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport', 'name');
}

function migrate2utf8_quizport_questions_name($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport_questions', 'name', 0, true);
}

function migrate2utf8_quizport_quizzes_name($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport_quizzes', 'name');
}

function migrate2utf8_quizport_quizzes_stoptext($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport_quizzes', 'stoptext');
}

function migrate2utf8_quizport_strings_string($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport_strings', 'string', 0, true);
}

function migrate2utf8_quizport_units_entrytext($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport_units', 'entrytext');
}

function migrate2utf8_quizport_units_exittext($recordid) {
    return migrate2utf8_quizport_engine($recordid, 'quizport_units', 'exittext');
}

function migrate2utf8_quizport_engine($id, $table, $field, $courseid=0, $md5key=false) {
    global $CFG, $globallang;

    if (empty($id) || empty($table) || empty($field)) {
        log_the_problem_somewhere();
        return false;
    }

    if (! $record = get_record($table, 'id', $id)) {
        log_the_problem_somewhere();
        return false;
    }

    if ($globallang) {
        $fromenc = $globallang;
    } else {
        if ($courseid==0) {
            if (isset($record->course)) {
                $courseid = $record->course;
            } else if (isset($record->courseid)) {
                $courseid = $record->courseid;
            }
        }
        if ($courseid) {
            $courselang = get_course_lang($courseid);
            $userlang = get_main_teacher_lang($courseid);
        } else {
            $courselang = null;
            $userlang = null;
        }
        $fromenc = get_original_encoding($CFG->lang, $courselang, $userlang);
    }

    if ($fromenc != 'utf-8' && $fromenc != 'UTF-8') {
        $record->$field = utfconvert($record->$field, $fromenc);
        if ($md5key) {
            $record->$md5key = md5($record->$field);
        }
        migrate2utf8_update_record($table, $record);
    }
    return true;
}
?>