<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

// we will need Moodle's flexible table class
require_once($CFG->dirroot.'/mod/quizport/tablelib.php');

class mod_quizport_report extends mod_quizport {
    var $forcetab = 'report';
    var $pagehasreporttab = true;
    var $pagehascolumns = false;

    // display table columns
    var $usercolumns = array('picture', 'fullname');
    var $unitcolumns = array('unitgrade', 'unitstatus', 'unitdate', 'unitduration','unitattempt');
    var $quizcolumns = array('quizscore', 'quizstatus', 'quizdate', 'quizduration','quizattempt');
    var $unitattemptcolumns = array('attemptgrade', 'attemptstatus', 'attemptdate', 'attemptduration');
    var $quizattemptcolumns = array('attemptscore', 'attemptstatus', 'attemptdate', 'attemptduration');

    // sometimes we need to show a select column too
    var $showselectcolumn = false;

    function print_heading() {
        if ($this->quizid && ! $this->quizscoreid) {
            print_heading(format_string($this->quiz->name));
        }
    }

    function print_content() {
        global $DB;

        if (has_capability('mod/quizport:viewreports', $this->coursecontext)) {
        //    $this->showselectcolumn = true;
        //    $this->display_report_selector('singleuser');
        }

        if ($this->unitgrade && ! $this->unitattempt) {
            $select = ''
                .'unitid='.$this->unit->id
                .' AND userid='.$this->unitgrade->userid
            ;
            if ($records = $DB->get_records_select('quizport_unit_attempts', $select)) {
                if (count($records)==1) {
                    list($this->unitattemptid, $this->unitattempt) = each($records);
                }
            }
        }
        if ($this->unitattempt && ! $this->quizscore) {
            $select = ''
                .'quizid IN (SELECT id FROM {quizport_quizzes} WHERE unitid='.$this->unitattempt->unitid.')'
                .' AND unumber='.$this->unitattempt->unumber
                .' AND userid='.$this->unitattempt->userid
            ;
            if ($records = $DB->get_records_select('quizport_quiz_scores', $select)) {
                if (count($records)==1) {
                    list($this->quizscoreid, $this->quizscore) = each($records);
                    $this->quizid = $this->quizscore->quizid;
                    $this->get_quiz();
                }
            }
        }
        if ($this->quizscore && ! $this->quizattempt) {
            $select = ''
                .'quizid='.$this->quizscore->quizid
                .' AND unumber='.$this->quizscore->unumber
                .' AND userid='.$this->quizscore->userid
            ;
            if ($records = $DB->get_records_select('quizport_quiz_attempts', $select)) {
                if (count($records)==1) {
                    list($this->quizattemptid, $this->quizattempt) = each($records);
                }
            }
        }

        // which report we show depends on what parameters were passed in
        switch (true) {

            case $this->quizattemptid > 0:
                // show details of a particular attempt at a certain quiz by a certain student
                $this->display_table_quizattempt();
                    break;

            case $this->quizscoreid > 0:
                // show details of all attempts at a certain quiz by a certain student
                // within a particular unit attempt
                $this->display_table_quizattempts();
                break;

            case $this->unitattemptid > 0:
                // show details of a particular attempt at a certain unit by a certain student
                $this->display_table_unitattempt();
                break;

            case $this->unitgradeid > 0:
                $this->display_table_unitattempts();
                break;

            case $this->quizid > 0:
                // show details of attempts at this quiz by all or selected users
                $report = 'report_'.$this->mode;
                if (method_exists($this->quiz->output, $report)) {
                    $this->quiz->output->$report();
                } else {
                    $this->display_table_quizattempts();
                }
                break;

            case $this->unitid > 0:
                // show details of attempts at this unit by all of selected users
                $this->display_table_unitattempts();
                break;

            default:
                // no unit id found - redirect to index.php
                redirect('index.php?id='.$this->courserecord->id);
        } // end switch

        if ($this->showselectcolumn) {
            $this->print_form_end();
        }
    }

    function get_modes() {
        if ($this->tab=='report' && $this->quizscoreid==0 && $this->quizid) {
            return $this->quiz->output->get_reports();
        } else {
            return false;
        }
    }

    function display_user_selector($record, $tablealias) {
        global $CFG;

        // set up filters
        if ($record) {
            $this->display_report_selector();
            $userfilter = "$tablealias.userid=$record->userid";
            $attemptfilter = '';
            if (isset($record->unumber)) {
                $unumberfilter = " AND $tablealias.unumber=$record->unumber";
            } else {
                $unumberfilter = '';
            }
            $params = array();
        } else if (has_capability('mod/quizport:viewreports', $this->coursecontext)) {
            // get name order
            if (isset($CFG->fullnamedisplay)) {
                $fullnamedisplay = $CFG->fullnamedisplay;
            } else {
                $fullnamedisplay = 'firstname lastname';
            }
            if ($fullnamedisplay=='language') {
                $fullnamedisplay = get_string('fullnamedisplay', '', (object)array('firstname'=>'firstname', 'lastname'=>'lastname'));
            }
            $fields = array();
            foreach (explode(' ', $fullnamedisplay) as $name) {
                $fields[$name] = 1;
            }
            $fields = array('group' => 0, 'realname' => 0) + $fields + array('username' => 1, 'status' => 0);
            $params = $this->merge_params(
                array('id'=>$this->modulerecord->id)
            );

            require_once($CFG->dirroot.'/mod/quizport/user_filtering.php');
            $user_filtering = new quizport_user_filtering($fields, '', $params);

            $user_filtering->display_add();
            $user_filtering->display_active();

            $params = array();

            $attemptsparams = array();
            $attemptfilter = $user_filtering->get_sql_filter_attempts();
            if (is_array($attemptfilter)) {
                 // Moodle 2.0
                list($attemptfilter, $attemptsparams) = $attemptfilter;
            }
            if ($attemptfilter) {
                $attemptfilter = " AND $tablealias.$attemptfilter";
                $params = $params + $attemptsparams;
            }

            $extra = $this->get_all_groups_sql('', 'id');
            $filter = $user_filtering->get_sql_filter($extra);

            if (is_array($filter)) {
                // Moodle 2.0
                list($filter, $userparams) = $filter;
            } else {
                $userparams = array();
            }

            // id IN (5,13,8,14,6,9,10,7,12,11,18,16,15,17,58);
            if ($filter) {
                $userfilter = preg_replace('/(?<![a-z.])(?:user)?id\b/', "$tablealias.userid", $filter);
                $params = $params + $userparams;
            } else {
                $userfilter = $this->get_userfilter('', "$tablealias.userid");
            }
            $unumberfilter = '';
        } else {
            $userfilter = "$tablealias.userid=$this->userid";
            $attemptfilter = '';
            $unumberfilter = '';
            $params = array();
        }

        return array($userfilter, $attemptfilter, $unumberfilter, $params);
    }

    function display_table_unitattempts() {
        global $CFG, $DB;

        list($userfilter, $attemptfilter, $unumberfilter, $params)
            = $this->display_user_selector($this->unitgrade, 'qug');

        $fields = ''
            .'qua.id, qua.unitid, qua.unumber, qua.userid, qua.grade, qua.status, qua.duration, qua.timemodified, '
            .'qug.id AS qug_id, qug.grade AS qug_grade, qug.status AS qug_status, qug.duration AS qug_duration, qug.timemodified AS qug_timemodified, '
            .'u.firstname, u.lastname, u.picture'
        ;
        $tables = ''
            .'{quizport_unit_attempts} qua JOIN ('
                .'SELECT id, parenttype, parentid '
                .'FROM {quizport_units}'
            .') qu ON qua.unitid=qu.id JOIN ('
                .'SELECT id, parenttype, parentid, userid, grade, status, duration, timemodified '
                .'FROM {quizport_unit_grades}'
            .') qug ON qu.parenttype=qug.parenttype AND qu.parentid=qug.parentid AND qua.userid=qug.userid JOIN ('
                .'SELECT id, firstname, lastname, picture '
                .'FROM {user}'
            .') u ON u.id=qua.userid'
        ;
        $select = 'qua.unitid='.$this->unitid.' AND '.$userfilter.$attemptfilter.$unumberfilter;

        if (! $userfilter || ! $unitattempts = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select ORDER BY u.lastname,u.firstname,unumber", $params)) {
            print_box(get_string('noresultsfound', 'quizport'), 'generalbox', 'centeredboxtable');
            return false;
        }

        $users = array();
        foreach (array_keys($unitattempts) as $id) {
            $unitattempt = &$unitattempts[$id];

            $userid = $unitattempt->userid;
            if (empty($users[$userid])) {
                $users[$userid] = (object)array(
                    'rowspan' => 0,
                    'unitgrades' => array()
                );
            }

            $unitgradeid = $unitattempt->qug_id;
            if (empty($users[$userid]->unitgrades[$unitgradeid])) {
                $users[$userid]->unitgrades[$unitgradeid] = (object)array(
                    'rowspan' => 0,
                    'unitattempts' => array()
                );
            }


            // increment rowspans
            $users[$userid]->rowspan ++;
            $users[$userid]->unitgrades[$unitgradeid]->rowspan ++;

            // add this unit attempt
            $unumber = $unitattempt->unumber;
            $users[$userid]->unitgrades[$unitgradeid]->unitattempts[$unumber] = &$unitattempt;
        }

        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->is_collapsible = true;
        $table->set_attribute('id', 'overview');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = array_merge($this->usercolumns, $this->unitcolumns, $this->unitattemptcolumns);

        if ($this->showselectcolumn) {
            $columns[] = 'select';
        }

        $table->define_columns($columns);
        $table->define_headers($this->table_headers($columns));

        $table->column_class('picture', 'userinfo');
        $table->column_class('fullname', 'userinfo');
        if ($this->showselectcolumn) {
            $table->column_class('select', 'select');
        }

        $table->setup();

        $dateformat = get_string('strftimerecent');

        $oddeven = 1;
        foreach (array_keys($users) as $userid) {
            $oddeven = $oddeven ? 0 : 1;
            $class = 'r'.$oddeven;

            $print_user = true;
            foreach (array_keys($users[$userid]->unitgrades) as $unitgradeid) {
                $unitgrade = &$users[$userid]->unitgrades[$unitgradeid];

                $print_unitgrade = true;
                foreach (array_keys($unitgrade->unitattempts) as $unumber) {
                    $unitattempt = &$unitgrade->unitattempts[$unumber];

                    $row = array();

                    // if necesary, add user details
                    if ($print_user) {
                        $print_user = false;
                        $rowspan = $users[$userid]->rowspan;
                        $picture = print_user_picture($userid, $this->courserecord->id, $unitattempt->picture, false, true);
                        $fullname = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.$this->courserecord->id.'">'.fullname($unitattempt).'</a>';
                        array_push($row,
                            array('text'=>$picture, 'rowspan'=>$rowspan, 'class'=>$class),
                            array('text'=>$fullname, 'rowspan'=>$rowspan, 'class'=>$class)
                        );
                    } else {
                        array_push($row, '',''); // these cells will be skipped
                    }

                    // if necesary, add unit grade details
                    if ($print_unitgrade) {
                        $print_unitgrade = false;
                        $rowspan = $unitgrade->rowspan;
                        $unitattempt->qug_grade = $this->format_score_or_grade(
                            $unitattempt->qug_grade, $this->unit->gradeweighting
                        );
                        $href = $this->format_url('report.php', '', array('unitgradeid'=>$unitgradeid));
                        array_push($row,
                            array('text'=>'<a href="'.$href.'">'.$unitattempt->qug_grade.'</a>', 'rowspan'=>$rowspan, 'class'=>$class),
                            array('text'=>quizport_format_status($unitattempt->qug_status), 'rowspan'=>$rowspan, 'class'=>$class),
                            array('text'=>userdate($unitattempt->qug_timemodified, $dateformat), 'rowspan'=>$rowspan, 'class'=>$class),
                            array('text'=>quizport_format_time($unitattempt->qug_duration), 'rowspan'=>$rowspan, 'class'=>$class)
                        );
                    } else {
                        array_push($row, '','','',''); // these cells will be skipped
                    }

                    // add unit attempt details
                    $unitattempt->grade = $this->format_score_or_grade(
                        $unitattempt->grade, $this->unit->gradelimit
                    );
                    $href = $this->format_url('report.php', '', array('unitattemptid'=>$unitattempt->id));
                    array_push($row,
                        array('text'=>'<a href="'.$href.'">'.$unitattempt->unumber.'</a>', 'class'=>$class),
                        array('text'=>'<a href="'.$href.'">'.$unitattempt->grade.'</a>', 'class'=>$class),
                        array('text'=>quizport_format_status($unitattempt->status), 'class'=>$class),
                        array('text'=>userdate($unitattempt->timemodified, $dateformat), 'class'=>$class),
                        array('text'=>quizport_format_time($unitattempt->duration), 'class'=>$class)
                    );
                    if ($this->showselectcolumn) {
                        array_push($row, array('class'=>$class));
                    }
                    $table->add_data($row, array('class'=>''));
                }
            }
        }
        if (! empty($table->data)) {
            $table->print_html();
        }
    }

    function display_table_unitattempt() {
        global $DB;

        $this->display_report_selector();

        $quizids = "SELECT id FROM {quizport_quizzes} WHERE unitid={$this->unitattempt->unitid}";
        $select = "userid={$this->unitattempt->userid} AND unumber={$this->unitattempt->unumber} AND quizid in ($quizids)";

        $table = 'quizport_quiz_scores';
        if (! $quizscores = $DB->get_records_select($table, $select, null, 'quizid')) {
            debugging("no records in table $table for unitattempt $this->unitattemptid", DEBUG_DEVELOPER);
            return false;
        }

        $quizids = array();
        foreach (array_keys($quizscores) as $id) {
            $quizids[$quizscores[$id]->quizid] = true;
        }
        $quizids = implode(',', array_keys($quizids));


        $table = 'quizport_quizzes';
        if (! $quizzes = $DB->get_records_select($table, "id IN ($quizids)", null, 'sortorder', 'id,name,scorelimit,scoreweighting')) {
            debugging("no records in table $table for unitattempt $this->unitattemptid", DEBUG_DEVELOPER);
            return false;
        }

        foreach (array_keys($quizscores) as $id) {
            $quizid = $quizscores[$id]->quizid;
            if (isset($quizzes[$quizid])) {
                $quizzes[$quizid]->rowspan = 0;
                $quizzes[$quizid]->quizattempts = array();
                $quizzes[$quizid]->quizscore = $quizscores[$id];
            } else {
                // invalid quizid - shouldn't happen !!
                unset($quizscores[$id]);
            }
        }

        $table = 'quizport_quiz_attempts';
        if (! $quizattempts = $DB->get_records_select($table, $select, null, 'quizid,qnumber')) {
            debugging("no records in table $table for unitattempt $this->unitattemptid", DEBUG_DEVELOPER);
            return false;
        }

        foreach (array_keys($quizattempts) as $id) {
            // shortcuts
            $quizid = $quizattempts[$id]->quizid;
            $qnumber = $quizattempts[$id]->qnumber;

            // increment rowspan and add this attempt to its parent quiz
            $quizzes[$quizid]->rowspan ++;
            $quizzes[$quizid]->quizattempts[$qnumber] = &$quizattempts[$id];
        }

        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->is_collapsible = true;
        $table->set_attribute('id', 'overview');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = array_merge(array('quiz'), $this->quizcolumns, $this->quizattemptcolumns);

        if ($this->showselectcolumn) {
            $columns[] = 'select';
        }

        $table->define_columns($columns);
        $table->define_headers($this->table_headers($columns));

        if ($this->showselectcolumn) {
            $table->column_class('select', 'select');
        }

        $table->setup();
        $dateformat = get_string('strftimerecent');

        $oddeven = 1;
        foreach ($quizzes as $quizid=>$quiz) {
            $oddeven = $oddeven ? 0 : 1;
            $class = 'r'.$oddeven;

            $print_quiz = true;
            foreach ($quiz->quizattempts as $qnumber=>$quizattempt) {
                $row = array();

                if ($print_quiz) {
                    $print_quiz = false;
                    $quizscore = &$quiz->quizscore;
                    $quizscore->score = $this->format_score_or_grade(
                        $quizscore->score, $quiz->scoreweighting
                    );
                    $href = $this->format_url('report.php', '', array('quizscoreid'=>$quizscore->id));
                    array_push($row,
                        array('text'=>format_string($quiz->name), 'rowspan'=>$quiz->rowspan, 'class'=>$class),
                        array('text'=>'<a href="'.$href.'">'.$quizscore->score.'</a>', 'rowspan'=>$quiz->rowspan, 'class'=>$class),
                        array('text'=>quizport_format_status($quizscore->status), 'rowspan'=>$quiz->rowspan, 'class'=>$class),
                        array('text'=>userdate($quizscore->timemodified, $dateformat), 'rowspan'=>$quiz->rowspan, 'class'=>$class),
                        array('text'=>quizport_format_time($quizscore->duration), 'rowspan'=>$quiz->rowspan, 'class'=>$class)
                    ); // these cells will be skipped
                } else {
                    array_push($row, '', '', '', '', ''); // these cells will be skipped
                }

                $quizattempt->score = $this->format_score_or_grade(
                    $quizattempt->score, $quiz->scorelimit
                );
                $timemodified = max($quizattempt->starttime, $quizattempt->endtime, $quizattempt->timestart, $quizattempt->timefinish);
                $href = $this->format_url('report.php', '', array('quizattemptid'=>$quizattempt->id));
                array_push($row,
                    array('text'=>'<a href="'.$href.'">'.$quizattempt->qnumber.'</a>', 'class'=>$class),
                    array('text'=>'<a href="'.$href.'">'.$quizattempt->score.'</a>', 'class'=>$class),
                    array('text'=>quizport_format_status($quizattempt->status), 'class'=>$class),
                    array('text'=>userdate($timemodified, $dateformat), 'class'=>$class),
                    array('text'=>quizport_format_time($quizattempt->duration), 'class'=>$class)
                );

                if ($this->showselectcolumn) {
                    $row[] = array('class'=>$class);
                }

                $table->add_data($row, array('class'=>''));
            }
            if ($print_quiz) {
                // no attempts for this quiz - shouldn't happen
                $row = array(
                    array('text'=>format_string($quiz->name), 'class'=>$class),
                    array('text'=>get_string('notattemptedyet', 'quizport'), 'class'=>$class, 'colspan'=>4),
                    '', '', '', // these cells will be skipped
                    array('class'=>$class, 'colspan'=>5),
                    '', '', '', '' // these cells will be skipped
                );
                if ($this->showselectcolumn) {
                    $row[] = array('class'=>$class);
                }
                $table->add_data($row, array('class'=>''));
            }
        }
        if (! empty($table->data)) {
            $table->print_html();
        }
    }

    function display_table_quizattempts() {
        global $CFG, $DB;

        list($userfilter, $attemptfilter, $unumberfilter, $params)
            = $this->display_user_selector($this->quizscore, 'qqa');

        $fields = ''
            .'qqa.id id, qqa.quizid, qqa.unumber, qqa.qnumber, qqa.userid, qqa.penalties, '
            .'qqa.score, qqa.status, qqa.duration, qqa.starttime, qqa.endtime, qqa.timestart, qqa.timefinish, '
            .'qqs.id qqs_id, qqs.score qqs_score, qqs.status qqs_status, qqs.duration qqs_duration, qqs.timemodified qqs_timemodified, '
            .'qua.id qua_id, '
            .'u.firstname, u.lastname, u.picture'
        ;
        $tables = ''
            .'{quizport_quiz_attempts} qqa JOIN ('
                .'SELECT id, quizid, unumber, userid, score, status, duration, timemodified '
                .'FROM {quizport_quiz_scores}'
            .') qqs ON qqs.quizid=qqa.quizid AND qqs.unumber=qqa.unumber AND qqs.userid=qqa.userid JOIN ('
                .'SELECT id, unitid, unumber, userid '
                .'FROM {quizport_unit_attempts} '
                .'WHERE unitid='.$this->unitid
            .') qua ON qua.unumber=qqs.unumber AND qua.userid=qqs.userid JOIN ('
                .'SELECT id, firstname, lastname, picture '
                .'FROM {user}'
            .') u ON u.id=qua.userid'
        ;
        $select = 'qqa.quizid='.$this->quizid.' AND '.$userfilter.$attemptfilter.$unumberfilter;

        if (! $userfilter || ! $quizattempts = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select ORDER BY u.lastname,u.firstname,unumber,qnumber", $params)) {
            print_box(get_string('noresultsfound', 'quizport'), 'generalbox', 'centeredboxtable');
            return false;
        }

        $users = array();
        foreach (array_keys($quizattempts) as $id) {
            $quizattempt = &$quizattempts[$id];

            $userid = $quizattempt->userid;
            if (empty($users[$userid])) {
                $users[$userid] = (object)array(
                    'rowspan' => 0,
                    'unitattempts' => array()
                );
            }

            $unumber = $quizattempt->unumber;
            if (empty($users[$userid]->unitattempts[$unumber])) {
                $users[$userid]->unitattempts[$unumber] = (object)array(
                    'rowspan' => 0,
                    'quizscores' => array()
                );
            }

            $quizid = $quizattempt->quizid;
            if (empty($users[$userid]->unitattempts[$unumber]->quizscores[$quizid])) {
                $users[$userid]->unitattempts[$unumber]->quizscores[$quizid] = (object)array(
                    'rowspan' => 0,
                    'quizattempts' => array()
                );
            }

            $qnumber = $quizattempt->qnumber;
            if (empty($users[$userid]->unitattempts[$unumber]->quizscores[$quizid]->quizattempts[$qnumber])) {
                $users[$userid]->unitattempts[$unumber]->quizscores[$quizid]->quizattempts[$qnumber] = (object)array(
                    'rowspan' => 0,
                    'quizattempts' => array()
                );
            }

            // increment rowspans
            $users[$userid]->rowspan ++;
            $users[$userid]->unitattempts[$unumber]->rowspan ++;
            $users[$userid]->unitattempts[$unumber]->quizscores[$quizid]->rowspan ++;

            // add this quiz attempt
            $users[$userid]->unitattempts[$unumber]->quizscores[$quizid]->quizattempts[$qnumber] = &$quizattempt;
        }

        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->is_collapsible = true;
        $table->set_attribute('id', 'overview');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        if ($unumberfilter) {
            $columns = array_merge($this->usercolumns, $this->quizcolumns, $this->quizattemptcolumns);
        } else {
            $columns = array_merge($this->usercolumns, array('unitattempt'), $this->quizcolumns, $this->quizattemptcolumns);
        }

        if ($this->showselectcolumn) {
            $columns[] = 'select';
        }

        $table->define_columns($columns);
        $table->define_headers($this->table_headers($columns));

        $table->column_class('picture', 'userinfo');
        $table->column_class('fullname', 'userinfo');
        if ($this->showselectcolumn) {
            $table->column_class('select', 'select');
        }

        $table->setup();
        $dateformat = get_string('strftimerecent');

        $oddeven = 1;
        foreach (array_keys($users) as $userid) {
            $oddeven = $oddeven ? 0 : 1;
            $class = 'r'.$oddeven;

            $print_user = true;
            foreach (array_keys($users[$userid]->unitattempts) as $unumber) {
                $unitattempt = &$users[$userid]->unitattempts[$unumber];

                $print_unitattempt = true;
                foreach (array_keys($unitattempt->quizscores) as $quizid) {
                    $quizscore = &$unitattempt->quizscores[$quizid];

                    $print_quizscore = true;
                    foreach (array_keys($quizscore->quizattempts) as $qnumber) {
                        $quizattempt = &$quizscore->quizattempts[$qnumber];
                        $row = array();

                        // if necesary, add user details and unit grade details
                        if ($print_user) {
                            $print_user = false;
                            $picture = print_user_picture($userid, $this->courserecord->id, $quizattempt->picture, false, true);
                            $fullname = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.$this->courserecord->id.'">'.fullname($quizattempt).'</a>';
                            array_push($row,
                                array('text'=>$picture, 'rowspan'=>$users[$userid]->rowspan, 'class'=>$class),
                                array('text'=>$fullname, 'rowspan'=>$users[$userid]->rowspan, 'class'=>$class)
                            );
                        } else {
                            array_push($row, '',''); // these cells will be skipped
                        }

                        // add unit attempt details
                        if ($unumberfilter=='') {
                            if ($print_unitattempt) {
                                $print_unitattempt = false;
                                $href = $this->format_url('report.php', '', array('unitattemptid'=>$quizattempt->qua_id, 'quizscoreid'=>0, 'quizid'=>0));
                                $row[] = array('text'=>'<a href="'.$href.'">'.$quizattempt->unumber.'</a>', 'rowspan'=>$unitattempt->rowspan, 'class'=>$class);
                            } else {
                                $row[] = '';
                            }
                        }

                        // add quiz score details
                        if ($print_quizscore) {
                            $print_quizscore = false;
                            $quizattempt->qqs_score = $this->format_score_or_grade(
                                $quizattempt->qqs_score, $this->quiz->scoreweighting
                            );
                            $href = $this->format_url('report.php', '', array('quizscoreid'=>$quizattempt->qqs_id));
                            array_push($row,
                                array('text'=>'<a href="'.$href.'">'.$quizattempt->qqs_score.'</a>', 'rowspan'=>$quizscore->rowspan, 'class'=>$class),
                                array('text'=>quizport_format_status($quizattempt->qqs_status), 'rowspan'=>$quizscore->rowspan, 'class'=>$class),
                                array('text'=>userdate($quizattempt->qqs_timemodified, $dateformat), 'rowspan'=>$quizscore->rowspan, 'class'=>$class),
                                array('text'=>quizport_format_time($quizattempt->qqs_duration), 'rowspan'=>$quizscore->rowspan, 'class'=>$class)
                            );
                        } else {
                            array_push($row, '','','',''); // these cells will be skipped
                        }

                        if ($this->showselectcolumn) {
                            $row[] = array('class'=>$class);
                        }

                        $timemodified = max($quizattempt->starttime, $quizattempt->endtime, $quizattempt->timestart, $quizattempt->timefinish);
                        $href = $this->format_url('report.php', '', array('quizattemptid'=>$quizattempt->id));
                        $quizattempt->score = $this->format_score_or_grade(
                            $quizattempt->score, $this->quiz->scorelimit
                        );
                        array_push($row,
                            array('text'=>'<a href="'.$href.'">'.$quizattempt->qnumber.'</a>', 'class'=>$class),
                            array('text'=>'<a href="'.$href.'">'.$quizattempt->score.'</a>', 'class'=>$class),
                            array('text'=>quizport_format_status($quizattempt->status), 'class'=>$class),
                            array('text'=>userdate($timemodified, $dateformat), 'class'=>$class),
                            array('text'=>quizport_format_time($quizattempt->duration), 'class'=>$class)
                        );
                        $table->add_data($row, array('class'=>''));
                    }
                }
            }
        }
        if (! empty($table->data)) {
            $table->print_html();
        }
    }

    function display_table_quizattempt() {
        $this->display_report_selector();
        $this->quiz->output->review();
    }

    function table_headers($columns) {
        $headers = array();
        foreach ($columns as $column) {
            if (preg_match('/^q(\d+)$/', $column, $matches)) {
                $headers[] = get_string('questionshort', 'quizport', $matches[1] + 1);
            } else {
                switch ($column) {
                    case 'field':
                    case 'picture':
                        $headers[] = '&nbsp;';
                        break;
                    case 'fullname':
                        $headers[] = get_string('name');
                        break;
                    case 'select':
                        $headers[] = get_string('select');
                        break;
                    default:
                        $headers[] = get_string($column, 'quizport');
                }
            }
        }
        return $headers;
    }

    function display_report_selector() {
        global $CFG, $DB;

        $mincount = 1;

        static $rowfields = array(
            // these fields will be displayed one per row at the selector table
            'fullname', 'unitgrade', 'unitattempt', 'quizscore', 'quizattempt'
        );

        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->set_attribute('id', 'reportselector');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');
        $table->define_columns(array('field','value'));

        $table->setup();

        // shortcuts to result records
        $unitgrade = &$this->unitgrade;
        $unitattempt = &$this->unitattempt;
        $quizscore = &$this->quizscore;
        $quizattempt = &$this->quizattempt;

        // set $userid and $params
        if ($quizattempt) {
            $userid = $quizattempt->userid;
        } else if ($unitattempt) {
            $userid = $unitattempt->userid;
        } else {
            $userid = 0;
        }
        $params = array(
            'userid'=>$userid, 'coursemoduleid'=>0,
            'unitid'=>0, 'quizid'=>0, 'conditionid'=>0,
            'unitgradeid'=>0, 'unitattemptid'=>0, 'unumber'=>0,
            'quizscoreid'=>0, 'quizattemptid'=>0, 'qnumber'=>0
        );

        // the format for the result times and dates
        $dateformat = get_string('strftimerecentfull');

        // add quiz attempt details
        foreach ($rowfields as $field) {
            $str = '';
            $href = '';
            $text = '';
            $grade = '';
            $datetime = 0;

            if ($field=='fullname') {
                // nothing
            } else if (empty($$field)) {
                continue;
            }

            switch ($field) {
                case 'fullname':
                    if ($userid==0 || $userid==$this->userid) {
                        // do nothing - this is the current user
                    } else {
                        $str = get_string('user');
                        $text = fullname($DB->get_record('user', array('id'=>$userid), 'id,firstname,lastname'));
                    }
                    break;

                case 'unitgrade':
                    $options = array();
                    if ($quizports = $this->get_quizports($$field->userid)) {
                        if ($units = $this->get_units($$field->userid, $quizports)) {
                            foreach ($units as $id=>$unit) {
                                $quizports[$unit->parentid]->unit =&$units[$id];
                            }
                            $parentids = implode(',', array_keys($quizports));
                            $select = 'parenttype='.QUIZPORT_PARENTTYPE_ACTIVITY." AND parentid IN ($parentids) AND userid=".$$field->userid;
                            if ($unitgrades = $DB->get_records_select('quizport_unit_grades', $select)) {
                                foreach ($unitgrades as $id=>$unitgraderecord) {
                                    $quizports[$unitgraderecord->parentid]->unitgrade =&$unitgrades[$id];
                                }
                            }
                        }
                        switch ($this->courserecord->format) {
                            case 'weeks': $strsection = get_string('strftimedateshort'); break;
                            case 'topics': $strsection = get_string('topic'); break;
                            default: $strsection = get_string('section');
                        }
                        foreach ($quizports as $quizport) {
                            if (empty($quizport->unit) || empty($quizport->unitgrade)) {
                                continue; // no grade
                            }
                            if ($quizport->section==0) {
                                $section = get_string('activities');
                            } else if ($this->courserecord->format=='weeks') {
                                $date = $this->courserecord->startdate + 7200 + ($quizport->section * 604800);
                                $section = ''
                                    .userdate($date, $strsection).' - '.userdate($date + 518400, $strsection)
                                ;
                            } else {
                                $section = $strsection.': '.$quizport->section;
                            }
                            $name = format_string($quizport->name);
                            if ($quizport->unit->gradelimit) {
                                $name .= ': '.$quizport->unitgrade->grade.'%';
                            }
                            $options[$section][$quizport->unitgrade->id] = $name;
                        }
                    }
                    $count = count($options);
                    if ($count >= $mincount) {
                        $str = get_string($field, 'quizport');
                        if ($unitattempt) {
                            $href = $this->format_url('report.php', '', array($field.'id'=>$$field->id, 'unumber'=>0, 'unitattemptid'=>0, 'quizscoreid'=>0, 'quizid'=>0, 'qnumber'=>0, 'quizattemptid'=>0));
                        }
                        if ($count==1) {
                            $grade = 'grade';
                        } else {
                            $iconhref = $this->format_url('report.php', '', array('unitid'=>$this->unitid, 'unitgradeid'=>0, 'unumber'=>0, 'unitattemptid'=>0, 'quizscoreid'=>0, 'quizid'=>0, 'qnumber'=>0, 'quizattemptid'=>0));
                            $icon = '<a href="'.$iconhref.'"><img src="'.$CFG->pixpath.'/i/stats.gif" class="icon" alt="" /></a>';
                            $text = $this->print_menu_submit($options, $field.'id', 'report.php', $params, true, 'choose'.$field).$icon.'<br />';
                        }
                        $datetime = $$field->timemodified;
                    }
                    break;

                case 'unitattempt':
                    if ($this->unit->attemptlimit==1) {
                        // only one attempt allowed, so don't bother showing the attempt info
                    } else {
                        $select = 'userid='.$$field->userid.' AND unitid='.$$field->unitid;
                        $fields = 'id,'.$DB->sql_concat('unumber', "': '", 'grade', "'%'")." AS unumbergrade";
                        $options = $DB->get_records_select_menu('quizport_unit_attempts', $select, null, 'unumber', $fields);
                        $count = count($options);
                        if ($count >= $mincount) {
                            $str = get_string($field, 'quizport');
                            if ($quizscore) {
                                $href = $this->format_url('report.php', '', array($field.'id'=>$$field->id, 'quizscoreid'=>0, 'quizid'=>0, 'qnumber'=>0, 'quizattemptid'=>0));
                            }
                            if ($count==1) {
                                $grade = 'grade';
                            } else {
                                $text = $this->print_menu_submit($options, $field.'id', 'report.php', $params, true, 'choose'.$field).'<br />';
                            }
                            $datetime = $$field->timemodified;
                        }
                    }
                    break;

                case 'quizscore':
                    $tables = '{quizport_quiz_scores} qqs INNER JOIN {quizport_quizzes} qq ON qqs.quizid=qq.id';
                    $fields = 'qqs.id,'.$DB->sql_concat('qq.name',"': '",'qqs.score',"'%'").' AS quiznamescore';
                    $select = 'userid='.$$field->userid.' AND qq.unitid='.$this->unitid.' AND qqs.unumber='.$$field->unumber;
                    $orderby = 'qq.sortorder';
                    $options = $DB->get_records_sql_menu("SELECT $fields FROM $tables WHERE $select ORDER BY $orderby");
                    $count = count($options);
                    if ($count >= $mincount) {
                        $str = get_string($field, 'quizport');
                        if ($quizattempt) {
                            $href = $this->format_url('report.php', '', array($field.'id'=>$$field->id, 'qnumber'=>0, 'quizattemptid'=>0));
                        }
                        if ($count==1) {
                            $grade = 'score';
                        } else {
                            $iconhref = $this->format_url('report.php', '', array('quizid'=>$this->quizid, 'unumber'=>0, 'unitgradeid'=>0, 'unitattemptid'=>0, 'quizscoreid'=>0, 'qnumber'=>0, 'quizattemptid'=>0));
                            $icon = '<a href="'.$iconhref.'"><img src="'.$CFG->pixpath.'/i/stats.gif" class="icon" alt="" /></a>';
                            $text = $this->print_menu_submit($options, $field.'id', 'report.php', $params, true, 'choose'.$field).$icon.'<br />';
                        }
                        $datetime = $$field->timemodified;
                    }
                    break;

                case 'quizattempt':
                    if ($this->quiz->attemptlimit==1) {
                        // only one attempt allowed, so don't bother showing the attempt info
                    } else {
                        $select = 'userid='.$$field->userid.' AND quizid='.$$field->quizid.' AND unumber='.$$field->unumber;
                        $fields = 'id,'.$DB->sql_concat('qnumber', "': '", 'score', "'%'")." AS qnumberscore";
                        $options = $DB->get_records_select_menu('quizport_quiz_attempts', $select, null, 'qnumber', $fields);
                        $count = count($options);
                        if ($count >= $mincount) {
                            $str = get_string($field, 'quizport');
                            //$href = $this->format_url('report.php', '', array($field.'id'=>$$field->id));
                            if ($count==1) {
                                $grade = 'score';
                            } else {
                                $text = $this->print_menu_submit($options, $field.'id', 'report.php', $params, true, 'choose'.$field).'<br />';
                            }
                            $datetime = max($$field->starttime, $$field->endtime, $$field->timestart, $$field->timefinish);
                        }
                    }
                    break;
            }
            if ($str) {
                if ($datetime) {
                    if (substr($field, 0, 4)=='unit') {
                        $grade = 'grade';
                    } else {
                        $grade = 'score';
                    }
                    $text .= ' ';
                    if ($href) {
                        $text .= '<a href="'.$href.'">';
                    }
                    $text .= ''
                        .$$field->$grade.'% '.quizport_format_status($$field->status, true).' '
                        .userdate($datetime, $dateformat).' '.'('.quizport_format_time($$field->duration).')'
                    ;
                    if ($href) {
                        $text .= '</a>';
                    }
                }
               $table->add_data(array($str, $text));
            }
        }
        if (! empty($table->data)) {
            $table->print_html();
        }
    }

    function format_score_or_grade($num, $limit_or_weighting) {
        if ($limit_or_weighting==0) {
            return '-';
        }
        if ($limit_or_weighting==100) {
            return $num.'%';
        }
        return $num;
    }
}
?>