<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

// we will need Moodle's flexible table class
require_once($CFG->dirroot.'/mod/quizport/tablelib.php');

class mod_quizport_index extends mod_quizport {
    var $pagehastabs = false;
    var $pagehascolumns = false;

    var $multiusercolumns = array(
        'section','showhide','name','gradehighest','gradeaverage','select','report'
    );

    var $singleusercolumns = array(
        'section','showhide_unitattempts','showhide_quizzes','showhide_quizattempts','qnumber','quizattempt'
    );

    // $deleted object contains details of attempt records, if any,
    // that were deleted by delete_selected_attempts()
    // details are printed by print_content()
    var $deleted = null;

    function print_header() {
        global $CFG, $THEME;

        if (has_capability('moodle/course:manageactivities', $this->coursecontext)) {
            $url = $CFG->wwwroot.'/mod/quizport/editunits.php';
            $text = get_string('editunits', 'quizport');
            $options = array('id'=>$this->courserecord->id);
            $buttons = $this->print_single_button($url, $options, $text, 'get', '_self', true);
        } else {
            $buttons = '&nbsp;';
        }

        $strmodulenameplural = get_string('modulenameplural', 'quizport');
        $title = format_string($this->courserecord->shortname).': '.$strmodulenameplural;

        $navigation = build_navigation(
            array(array('name' => $strmodulenameplural, 'link' => '', 'type' => 'activity'))
        );

        $meta = '';
        $bodytags = '';
        if ($CFG->majorrelease<=1.4) {
            $meta = '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/mod/quizport/styles.php" />';
            if ($CFG->majorrelease<=1.2) {
                $THEME->body .= '"'.' class="mod-quizport" id="'.QUIZPORT_PAGEID;
            } else {
                $bodytags .= ' class="mod-quizport" id="'.QUIZPORT_PAGEID.'"';
            }
        }

        // $title, $heading, $navigation, $focus, $meta, $cache, $buttons, $menu, $usexml, $bodytags, $return
        print_header($title, $this->courserecord->fullname, $navigation, '', $meta, true, $buttons, navmenu($this->courserecord, $this->modulerecord), false, $bodytags);
    }

    function print_heading() {
        // no heading required
    }

    function print_content() {
        $sections = array();
        if ($this->get_quizports()) {
            $userid = 0;
            $userfilter = false;
            if (has_capability('mod/quizport:deleteattempts', $this->coursecontext)) {
                if (isset($this->deleted->total) && $this->deleted->total>0) {
                    print_box(get_string('deletedquizattempts', 'quizport'), 'generalbox', 'centeredboxtable');
                }
                if ($userfilter = $this->get_userfilter('')) {
                    if (substr($userfilter, 0, 7)=='userid=') {
                        // teacher viewing a single student
                        $userid = intval(substr($userfilter, 7));
                        $this->singleusercolumns[] = 'select';
                        $this->print_reportselector('singleuser');
                    } else {
                        // teacher viewing all students
                        $this->print_reportselector('multiuser');
                    }
                }
            } else {
                // student can only see their own results
                $userid = $this->userid;
            }
            if ($userid==0) {
                $this->display_table_multiuser();
            } else {
                $this->display_table_singleuser($userid);
            }
            if ($userfilter) {
                // finish form for selecting users/attempts
                $this->print_form_end();
            }
        } else {
            // no quizports are visible to this user
            print get_string('noquizports', 'quizport');
        }
    }

    function display_table_multiuser() {
        global $CFG, $USER;

        $userfilter = $this->get_userfilter();

        // initialize table object
        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->set_attribute('id', 'quizportindexmultiuser');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = $this->multiusercolumns;
        $headers = $this->table_headers($columns, $table->attributes['id']);

        $table->define_columns($columns);
        $table->define_headers($headers);

        $table->column_class('section', 'section');
        $table->column_class('gradehighest', 'gradehighest');
        $table->column_class('gradeaverage', 'gradeaverage');
        $table->column_class('select', 'select');

        $table->setup();

        // groups quizports by course section
        $sections = array(); // section number => quizport objects
        foreach (array_keys($this->quizports) as $quizportid) {
            $section = $this->quizports[$quizportid]->section;

            // if necessary, create a new object for this section
            if (empty($sections[$section])) {
                if ($section==0) {
                    $text = $this->notext;
                } else {
                    $text = $section;
                }
                $sections[$section] = (object)array(
                    'section'=>$section, 'text'=>$text, 'rowspan'=>0, 'quizports'=>array()
                );
            }

            // initialize details about unit and quizzes for this quizport
            $this->quizports[$quizportid]->unitid = 0;
            $this->quizports[$quizportid]->quizzes = array();

            // add reference to this quizport record
            $sections[$section]->quizports[$quizportid] = &$this->quizports[$quizportid];
        }

        if ($this->get_units()) {
            // map each quizport to its unit
            foreach (array_keys($this->units) as $unitid) {
                $quizportid = $this->units[$unitid]->parentid;
                $section = $this->quizports[$quizportid]->section;
                $sections[$section]->rowspan ++;
                $sections[$section]->quizports[$quizportid]->unitid = $unitid;
            }
            // get highest and average scores for these units
            $ids = implode(',', array_keys($this->quizports));
            if ($aggregates = $this->get_aggregates('quizport_unit_grades', 'parentid', 'grade', "parenttype=0 AND parentid IN ($ids)".$userfilter)) {
                foreach ($aggregates as $id => $aggregate) {
                    if ($this->units[$this->quizports[$id]->unitid]->gradelimit==0) {
                        $this->quizports[$id]->highest = '-';
                        $this->quizports[$id]->average = '-';
                    } else {
                        $this->quizports[$id]->highest = $aggregate->highest;
                        $this->quizports[$id]->average = $aggregate->average;
                    }
                    $this->quizports[$id]->usercount = $aggregate->usercount;
                }
                unset($aggregates);
            }
        }

        if ($this->get_quizzes()) {
            // map each unit to its quizzes
            foreach (array_keys($this->quizzes) as $quizid) {
                $unitid = $this->quizzes[$quizid]->unitid;
                $quizportid = $this->units[$unitid]->parentid;
                $section = $this->quizports[$quizportid]->section;
                $sections[$section]->rowspan ++;
                $sections[$section]->quizports[$quizportid]->quizzes[$quizid] = &$this->quizzes[$quizid];
            }
            // get highest and average scores for these quizzes
            $ids = implode(',', array_keys($this->quizzes));
            if ($aggregates = $this->get_aggregates('quizport_quiz_scores', 'quizid', 'score', "quizid IN ($ids)".$userfilter)) {
                foreach ($aggregates as $id => $aggregate) {
                    if ($this->quizzes[$id]->scorelimit==0) {
                        $this->quizzes[$id]->highest = '-';
                        $this->quizzes[$id]->average = '-';
                    } else{
                        $this->quizzes[$id]->highest = $aggregate->highest;
                        $this->quizzes[$id]->average = $aggregate->average;
                    }
                    $this->quizzes[$id]->usercount = $aggregate->usercount;
                }
                unset($aggregates);
            }
        }

        foreach ($sections as $section) {

            $printed_section_cell = false;
            foreach ($section->quizports as $quizportid => $quizport) {
                // start row for QuizPort Unit
                $row = array();

                // add section cell, if required
                if ($printed_section_cell) {
                    $row[] = $this->notext;
                } else {
                    $row[] = array('text'=>$section->text, 'rowspan'=>$section->rowspan);
                    $printed_section_cell = true;
                }

                // add showhide icon cell for this QuizPort Unit
                $onclick = "showhide_quizport(this,'".$table->attributes['id']."','quizport_".$quizportid."_quiz','none')";
                $text = '<img id="img_showhide_quizport_'.$quizportid.'" src="'.$this->pixpath.'/t/switch_minus.gif" onclick="'.$onclick.'" />';
                $row[] = array('text' => $text, 'rowspan' => 1 + count($quizport->quizzes), 'class' => 'showhide');

                // add QuizPort Unit name
                $params = array(
                    'qp' => $quizportid, 'unumber' => -1, 'tab' => 'info'
                );
                $href = $this->format_url('view.php', '', $params);
                $row[] = '<a href="'.$href.'">'.format_string($quizport->name).'</a>';

                // add highest grade, average grade and link to reports for this QuizPort Unit
                $this->print_aggregates(
                    $row, $quizport, array('unitid'=>$quizport->unitid)
                );

                // add QuizPort Unit row to table
                $table->add_data($row, array('id' => 'quizport_'.$quizportid, 'class'=>'r0'));

                $icons = array();
                foreach ($quizport->quizzes as $quizid=>$quiz) {
                    // start new row for this QuizPort Quiz
                    $row = array($this->notext, $this->notext);

                    // cache icon for this type of QuizPort Quiz
                    $type = $quiz->sourcetype;
                    if (! isset($icons[$type])) {
                        $class = 'quizport_file_'.$type;
                        quizport_load_quiz_class('file', $type);
                        $object = new $class('', 0);
                        $icons[$type] = $object->get_icon();
                    }

                    // add icon and name for this QuizPort Quiz
                    $params = array(
                        'qp' => $quizportid, 'unumber' => -1,
                        'quizid' => $quizid, 'qnumber' => -1,
                        'tab' => 'preview'
                    );
                    $href = $this->format_url('view.php', '', $params);
                    $row[] = $icons[$type].' <a href="'.$href.'">'.format_string($quiz->name).'</a>';

                    // add highest grade, average grade, checkbox and link to reports for this QuizPort Quiz
                    $this->print_aggregates(
                        $row, $quiz, array('unitid'=>$quiz->unitid, 'quizid'=>$quizid)
                    );

                    $table->add_data($row, array('id' => 'quizport_'.$quizportid.'_quiz_'.$quizid, 'class'=>'r1'));
                } // end foreach $quizzes
            } // end foreach $quizports
        } // end foreach $sections

        print "\n";
        $table->print_html();

        print "\n";
        $this->print_js();
    }

    function print_reportselector($reporttype) {
        $params = array('id'=>$this->courserecord->id);
        $onsubmit = ''
            ."var a='';"
            ."if(this.elements['action']){"
                ."var a=this.elements['action'].options[this.elements['action'].selectedIndex].value;"
                ."if(a.substr(0,6)=='delete'){"
                    ."a='delete';"
                ."} else if(a=='0'){"
                    ."a='';"
                ."}"
            ."}"
            ."var x=false;"
            ."if(!a){"
                ."alert('".get_string('selectaction', 'quizport')."');"
            ."}else{"
                ."var obj=document.getElementsByTagName('input');"
                ."if(obj){"
                    ."for(var i in obj){"
                        ."if(obj[i].name && obj[i].name.substr(0,9)=='selected[' && obj[i].checked){"
                            ."x=true;"
                            ."break;"
                        ."}"
                    ."}"
                    ."if(!x){"
                        ."alert('".get_string('checksomeboxes', 'quizport')."');"
                    ."}"
                ."}"
            ."}"
            ."if(x){"
                ."switch(a){"
                    ."case 'delete':"
                        ."x=confirm('".get_string('confirmdeleteattempts', 'quizport')."');"
                        ."break;"
                    ."case 'regrade':"
                        ."x=confirm('".get_string('confirmregradeattempts', 'quizport')."');"
                        ."break;"
                    ."default:"
                        ."x=false;"
                ."}"
            ."}"
            ."if(this.elements['confirmed']){"
                ."this.elements['confirmed'].value=(x?1:0);"
            ."}"
            ."return x;"
        ;
        $this->print_form_start('index.php', $params, false, false, array('onsubmit' => $onsubmit));

        $this->print_userlist();
        $this->print_actions($reporttype);
        print '<input type="hidden" name="confirmed" value="0" />'."\n";
    }

    function print_actions($reporttype, $return=false) {
        $str = '';

        $options = array(
            get_string('regrade', 'quizport') => array(
                'regrade' => get_string('regradeselected', 'quizport')
            ),
        );

        $delete = get_string('delete');
        switch ($reporttype) {
            case 'multiuser':
                $temp = $delete.': '.get_string('quizattempts','quizport');
                $options[$delete] = array(
                    'deleteall' => $temp.' ('.get_string('all').')',
                    'deleteinprogress' => $temp.' ('.get_string('inprogress', 'quizport').')',
                    'deleteabandoned'  => $temp.' ('.get_string('abandoned', 'quizport').')',
                    'deletetimedout'   => $temp.' ('.get_string('timedout', 'quizport').')',
                    'deletecompleted'  => $temp.' ('.get_string('completed', 'quizport').')'
                );
                break;
            case 'singleuser':
                $options[$delete] = array(
                    'deleteselected' => get_string('deleteselected')
                );
                break;
        }

        $str .= '<b>'.get_string('action').':</b> ';
        $str .= choose_from_menu_nested($options, 'action', '', ' ', '', '0', true);
        $str .= '<input type="submit" value="'.get_string('go').'" />';
        $str = '<div id="quizport_actions">'.$str.'</div>'."\n";

        if ($return) {
            return $str;
        } else {
            print $str;
        }
    }

    function get_aggregates($table, $id, $grade, $select) {
        global $DB;
        $fields = "$id AS id, ROUND(MAX($grade),0) AS highest, ROUND(AVG($grade),0) AS average, COUNT(distinct userid) AS usercount";
        return $DB->get_records_sql("SELECT $fields FROM {".$table."} WHERE $select GROUP BY $id");
    }

    function print_aggregates(&$row, &$record, $params) {
        if (isset($record->highest)) {
            $highest = $record->highest;
            $average = $record->average;
            if (isset($params['unitid'])) {
                $name = 'selected['.$params['unitid'].'][0]'; // 0 = any unumber
                if (isset($params['quizid'])) {
                    $name .= '['.$params['quizid'].'][0]'; // 0 = any qnumber
                }
                $checkbox = trim($this->print_checkbox($name, 1, false, '', '', 'setCheckboxes(this)', true));
            } else {
                $checkbox = $this->notext;
            }
            $href = $this->format_url('report.php', '', $params, array('tab'=>'report'));
            $reports = '<a href="'.$href.'">'.get_string('viewreports', 'quizport', $record->usercount).'</a>';
        } else {
            $highest = $this->nonumber;
            $average = $this->nonumber;
            $checkbox = $this->notext;
            $reports = $this->notext;
        }
        array_push($row, $highest, $average, $checkbox, $reports);
    }

    function display_table_singleuser($userid=0) {
        global $CFG;

        // initialize table object
        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->set_attribute('id', 'quizportindexsingleuser');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = $this->singleusercolumns;
        $headers = $this->table_headers($columns, $table->attributes['id']);

        $table->define_headers($headers);
        $table->define_columns($columns);

        $table->column_class('section', 'section');
        if ($showselect = in_array('select', $columns)) {
            $table->column_class('select', 'select');
        }

        $table->setup();

        // add a reference from each quizport to its associated unit
        // and from each unit back to its associated quizport
        if ($this->get_units()) {
            foreach ($this->units as $unitid => $unit) {
                $quizportid = $unit->parentid;
                $this->quizports[$quizportid]->unitid = $unitid;
                $this->units[$unitid]->quizportid = $quizportid;
            }
        }

        // arrange the quizports into sections
        $sections = array();
        foreach (array_keys($this->quizports) as $quizportid) {
            $section = $this->quizports[$quizportid]->section;

            // initialize required data structures
            $this->quizports[$quizportid]->rowspan = 1;
            $this->quizports[$quizportid]->unitgrade = false;
            $this->quizports[$quizportid]->unitattempts = array();

            // if necessary, create a new object for this section
            if (empty($sections[$section])) {
                if ($section==0) {
                    $text = $this->notext;
                } else {
                    $text = $section;
                }
                $sections[$section] = (object)array(
                    'section'=>$section, 'text'=>$text, 'rowspan'=>0, 'quizports'=>array()
                );
            }

            // add quizport to this section and increment rowspan
            $sections[$section]->rowspan++;
            $sections[$section]->quizports[$quizportid] = &$this->quizports[$quizportid];
        }
        ksort($sections);

        // add a reference from each quizport to its associated unitgrade record
        if ($unitgrades = $this->get_unitgrades($userid)) {
            foreach (array_keys($unitgrades) as $id) {
                $quizportid = $unitgrades[$id]->parentid;
                $this->quizports[$quizportid]->unitgrade = &$unitgrades[$id];
            }
        }

        if ($unitattempts = $this->get_unitattempts($userid)) {
            foreach (array_keys($unitattempts) as $id) {

                // initialize data structures to store information about quizzes in this unit attempt
                $unitattempts[$id]->rowspan = 1;
                $unitattempts[$id]->quizzes = array();

                // create reference to the associaited quizport record
                $unitid = $unitattempts[$id]->unitid;
                if (empty($this->units[$unitid])) {
                    continue;
                }

                $quizportid = $this->units[$unitid]->parentid;
                if (empty($this->quizports[$quizportid])) {
                    continue;
                }
                $quizport = &$this->quizports[$quizportid];

                // add a reference to this unit attempt record in the array of unit attempts for this quizport
                $unumber = $unitattempts[$id]->unumber;
                $quizport->unitattempts[$unumber] = &$unitattempts[$id];

                // increment the rowspans for this section and quizport
                $quizport->rowspan++;
                $sections[$quizport->section]->rowspan++;
            }
        }

        if ($quizscores = $this->get_quizscores($userid)) {
            foreach (array_keys($quizscores) as $id) {
                $quizscore = &$quizscores[$id];

                // create shortcuts to ids
                $quizid = $quizscores[$id]->quizid;
                $unitid = $this->quizzes[$quizid]->unitid;
                $quizportid = $this->units[$unitid]->parentid;
                $unumber = $quizscores[$id]->unumber;

				if (! isset($this->quizports[$quizportid]->unitattempts[$unumber])) {
					// no unitattempt found for this quizscore - shouldn't happen !!
					continue;
				}

                // add a reference to this quiz and quiz attempt record in the array of quizzes for this unit attempt
				$this->quizports[$quizportid]->unitattempts[$unumber]->quizzes[$quizid] = (object)array(
					'quiz'=>&$this->quizzes[$quizid], 'quizscore'=>&$quizscores[$id], 'rowspan'=>1
				);
				// increment the rowspans for this section and quizport and unit attempt
				$this->quizports[$quizportid]->unitattempts[$unumber]->rowspan++;
				$this->quizports[$quizportid]->rowspan++;
				$sections[$quizport->section]->rowspan++;
            }
        }

        if ($quizattempts = $this->get_quizattempts($userid)) {
            foreach (array_keys($quizattempts) as $id) {

                // create shortcuts to ids
                $qnumber = $quizattempts[$id]->qnumber;
                $unumber = $quizattempts[$id]->unumber;
                $quizid = $quizattempts[$id]->quizid;
                $unitid = $this->quizzes[$quizid]->unitid;
                $quizportid = $this->units[$unitid]->parentid;

				if (! isset($this->quizports[$quizportid]->unitattempts[$unumber])) {
					// no unitattempt for this quizattempt - shouldn't happen
					continue;
				}

                // create shortcuts to quizport and unitattempt records
				$quizport = &$this->quizports[$quizportid];
				$unitattempt = &$quizport->unitattempts[$unumber];

				// add a reference to this quiz in the array of quizzes for this unit attempt
				if (empty($unitattempt->quizzes)) {
					$unitattempt->quizzes = array();
				}
				if (empty($unitattempt->quizzes[$quizid])) {
					$unitattempt->quizzes[$quizid] = (object)array(
						'quiz' => &$this->quizzes[$quizid], 'quizscore' => null, 'rowspan' => 1
					);
					$unitattempt->rowspan++;
				}
				$quiz = &$unitattempt->quizzes[$quizid];

				if (empty($quiz->quizattempts)) {
					$quiz->quizattempts = array();
				}
				$quiz->quizattempts[$qnumber] = &$quizattempts[$id];

				$quiz->rowspan++;
				$unitattempt->rowspan++;
				$quizport->rowspan++;
				$sections[$quizport->section]->rowspan++;
            }
        }

        foreach ($sections as $section) {

            $printed_section_cell = false;

            unset($quizportid, $quizport);
            foreach ($section->quizports as $quizportid => $quizport) {

                $row = array();
                $quizport_rowid = 'quizport_'.$quizport->id;
                $unitgradelimit = $this->units[$quizport->unitid]->gradelimit;

                // add cell showing section number
                if ($printed_section_cell) {
                    $row[] = $this->notext;
                } else {
                    $row[] = array('text'=>$section->text, 'rowspan'=>$section->rowspan);
                    $printed_section_cell = true;
                }

                // add cell for showhide icon
                if (empty($quizport->unitattempts)) {
                    $row[] = $this->notext;
                } else {
                    $onclick = "showhide_quizport(this,'".$table->attributes['id']."','".$quizport_rowid."_unumber','none')";
                    $text = '<img id="img_showhide_'.$quizport_rowid.'" src="'.$this->pixpath.'/t/switch_minus.gif" onclick="'.$onclick.'" />';
                    $row[] = array('text'=>$text, 'rowspan'=>$quizport->rowspan, 'class' => 'showhide');
                }

                // add cell for quizport name and grade
                $href = $this->format_url('view.php', '', array('unitid'=>$quizport->unitid, 'tab'=>'', 'sesskey'=>''));
                $text = get_string('unit', 'quizport').': <a href="'.$href.'">'.format_string($quizport->name).'</a><br />';
                if (empty($quizport->unitgrade)) {
                    $text .= get_string('notattemptedyet', 'quizport');
                } else {
                    if ($unitgradelimit==0) {
                        $text .= quizport_format_status($quizport->unitgrade->status).': ';
                    } else {
                        $href = $this->format_url('report.php', '', array('tab'=>'report', 'unitgradeid'=>$quizport->unitgrade->id));
                        $text .= ''
                            .get_string('grade', 'quizport').': '
                            .'<a href="'.$href.'">'.$quizport->unitgrade->grade.'%</a> '
                            .quizport_format_status($quizport->unitgrade->status, true).' '
                        ;
                    }
                    $text .= ''
                        .userdate($quizport->unitgrade->timemodified, get_string('strftimerecentfull'))
                        .' ('.quizport_format_time($quizport->unitgrade->duration).')'
                    ;
                }
                $cell = array('text'=>$text, 'colspan'=>4, 'styles'=>array('text-align'=>'left'));
                array_push($row, $cell, '', '', '');
                if ($showselect) {
                    if (count($quizport->unitattempts)) {
                        $name = 'selected['.$quizport->unitid.']';
                        $row[] = trim($this->print_checkbox($name, 1, false, '', '', 'setCheckboxes(this)', true));
                    } else {
                        $row[] = $this->notext;
                    }
                }
                $table->add_data($row, array('id' => $quizport_rowid, 'class'=>'r0'));

                // sort the unit attempts for this quizport
                ksort($quizport->unitattempts);

                // print details of unit attempts
                unset($unumber, $unitattempt);
                foreach ($quizport->unitattempts as $unumber => $unitattempt) {
					// start table row (first 2 columns wil be skipped)
                    $row = array('', '');
                    $unumber_rowid = $quizport_rowid.'_unumber_'.$unumber;

                    // add cell for showhide icon
                    if (empty($unitattempt->quizzes)) {
                        $row[] = $this->notext;
                    } else {
                        $onclick = "showhide_quizport(this,'".$table->attributes['id']."','".$unumber_rowid."_quiz','none')";
                        $text = '<img id="img_showhide_'.$unumber_rowid.'" src="'.$this->pixpath.'/t/switch_minus.gif" onclick="'.$onclick.'" />';
                        $row[] = array('text'=>$text, 'rowspan'=>$unitattempt->rowspan, 'class' => 'showhide');
                    }
                    if ($unitgradelimit==0) {
                        $text = quizport_format_status($unitattempt->status).': ';
                    } else {
                        $href = $this->format_url('report.php', '', array('tab'=>'report', 'unitattemptid'=>$unitattempt->id));
                        $text = ''
                            .get_string('attemptnumber', 'quizport', $unumber).': '
                            .'<a href="'.$href.'">'.$unitattempt->grade.'%</a> '
                            .quizport_format_status($unitattempt->status, true).' '
                        ;
                    }
                    $text .= ''
                        .userdate($unitattempt->timemodified, get_string('strftimerecentfull'))
                        .' ('.quizport_format_time($unitattempt->duration).')'
                    ;
                    $cell = array('text'=>$text, 'colspan'=>3, 'styles'=>array('text-align'=>'left'));
                    array_push($row, $cell, '', '');
                    if ($showselect) {
                        if (count($unitattempt->quizzes)) {
                            $name = 'selected['.$unitattempt->unitid.']['.$unitattempt->unumber.']';
                            $row[] = trim($this->print_checkbox($name, 1, false, '', '', 'setCheckboxes(this)', true));
                        } else {
                            $row[] = $this->notext;
                        }
                    }
                    $table->add_data($row, array('id' => $unumber_rowid, 'class'=>'r1'));

                    unset($quizid, $quiz);
                    foreach ($unitattempt->quizzes as $quizid => $quiz) {

						// start table row (first 3 columns wil be skipped)
                        $row = array('', '', '');
                        $quiz_rowid = $unumber_rowid.'_quiz_'.$quiz->quiz->id;
                        $scorelimit = $this->quizzes[$quizid]->scorelimit;

                        // add cell for showhide icon
                        if (empty($quiz->quizattempts)) {
                            $row[] = $this->notext;
                        } else {
                            $onclick = "showhide_quizport(this,'".$table->attributes['id']."','".$quiz_rowid."_qnumber','none')";
                            $text = '<img id="img_showhide_'.$quiz_rowid.'" src="'.$this->pixpath.'/t/switch_minus.gif" onclick="'.$onclick.'" />';
                            $row[] = array('text'=>$text, 'rowspan'=>$quiz->rowspan, 'class' => 'showhide');
                        }

                        // add cell for quiz name and score
                        $href = $this->format_url('report.php', '', array('tab'=>'report', 'quizid'=>$quizid));
                        $text = get_string('quiz', 'quizport').': <a href="'.$href.'">'.format_string($quiz->quiz->name).'</a><br />';
                        if (empty($quiz->quizscore)) {
                            $text .= get_string('notattemptedyet', 'quizport');
                        } else {
                            if ($scorelimit==0) {
                                $text .= quizport_format_status($quiz->quizscore->status).': ';
                            } else {
                                $href = $this->format_url('report.php', '', array('tab'=>'report', 'quizscoreid'=>$quiz->quizscore->id));
                                $text .= ''
                                    .get_string('score', 'quizport').': '
                                    .'<a href="'.$href.'">'.$quiz->quizscore->score.'%</a> '
                                    .quizport_format_status($quiz->quizscore->status, true).' '
                                ;
                            }
                            $text .= ''
                                .userdate($quiz->quizscore->timemodified, get_string('strftimerecentfull'))
                                .' ('.quizport_format_time($quiz->quizscore->duration).')'
                            ;
                        }
                        $cell = array('text'=>$text, 'colspan'=>2, 'styles'=>array('text-align'=>'left'));
                        array_push($row, $cell, '');
                        if ($showselect) {
                            if (empty($quiz->quizattempts)) {
                                $row[] = $this->notext;
                            } else {
                                $name = 'selected['.$unitattempt->unitid.']['.$unitattempt->unumber.']['.$quizid.']';
                                $row[] = trim($this->print_checkbox($name, 1, false, '', '', 'setCheckboxes(this)', true));
                            }
                        }
                        $table->add_data($row, array('id' => $quiz_rowid, 'class'=>'r0'));

                        if (empty($quiz->quizattempts)) {
                            continue;
                        }

                        // print details of quiz attempts
                        ksort($quiz->quizattempts);
                        foreach ($quiz->quizattempts as $qnumber => $quizattempt) {

							// start table row (first 4 columns wil be skipped)
                            if ($quizattempt->status==QUIZPORT_STATUS_INPROGRESS) {
                                $userdate = $quizattempt->timestart;
                            } else {
                                $userdate = $quizattempt->timefinish;
                            }
                            $row = array('', '', '', '', array('text'=>$qnumber, 'class'=>'qnumber'));

                            if ($scorelimit==0) {
                                $text = quizport_format_status($quizattempt->status).': ';
                            } else {
                                $href = $this->format_url('report.php', '', array('tab'=>'report', 'quizattemptid'=>$quizattempt->id));
                                $text =
                                    '<a href="'.$href.'">'.$quizattempt->score.'%</a> '
                                    .quizport_format_status($quizattempt->status, true).' '
                                ;
                            }
                            $text .= ''
                                .userdate($userdate, get_string('strftimerecentfull'))
                                .' ('.quizport_format_time($quizattempt->duration).')'
                            ;
                            $row[] = $text;
                            if ($showselect) {
                                $name = 'selected['.$unitattempt->unitid.']['.$unitattempt->unumber.']['.$quizattempt->quizid.']['.$quizattempt->qnumber.']';
                                $row[] = trim($this->print_checkbox($name, 1, false, '', '', 'setCheckboxes(this)', true));
                            }
                            $table->add_data($row, array('id' => $quiz_rowid.'_qnumber_'.$qnumber, 'class'=>'r1'));
                        }
                    }
                }
            }
        }
        print "\n";
        $table->print_html();
        print "\n";
        $this->print_js();
    }

    function table_headers(&$columns, $tableid='') {
        global $CFG;

        $headers = array();
        foreach ($columns as $column) {

            $header = '&nbsp;';
            switch ($column) {

                case 'section':
                    switch ($this->courserecord->format) {
                        case 'weeks': $header = get_string('week'); break;
                        case 'topics':  $header = get_string('topic'); break;
                    }
                    break;

                case 'showhide':
                    $onclick = "showhide_quizports(this,'".$tableid."','none')";
                    $header = '<img id="img_showhide_all" src="'.$this->pixpath.'/t/switch_minus.gif" onclick="'.$onclick.'" />';
                    break;

                case 'showhide_unitattempts':
                case 'showhide_quizzes':
                case 'showhide_quizattempts':
                case 'qnumber':
                case 'quizattempt':
                    $header = $this->notext;
                    break;

                case 'name':
                case 'report':
                    $header = get_string($column);
                    break;

                case 'gradehighest':
                case 'gradeaverage':
                    $header = get_string($column, 'quiz');
                    break;

                case 'select':
                    $header = get_string($column).'<br />'.trim($this->print_checkbox('selected[0]', 1, false, '', '', 'setCheckboxes(this)', true));
                    break;

                default:
                    $header = get_string($column, 'quizport');
            }
            $headers[] = $header;
        }
        return $headers;
    }

    function print_js() {
?>
<script type="text/javascript">
//<![CDATA[

    function setCheckboxes(checkbox) {
        var obj = document.getElementsByTagName('input');
        if (! obj) {
            return true;
        }
        if (! checkbox) {
            return true;
        }
        if (! checkbox.name) {
            return true;
        }
        // expected name format is selected[unitid][unumber][quizid][qnumber]
        var targetname = new RegExp('^selected'+'(((((\\[(\\d+)\\])?'+'\\[(\\d+)\\])?'+'\\[(\\d+)\\])?'+'\\[(\\d+)\\])?)'+'$');

        var matches = checkbox.name.match(targetname);
        if (! matches) {
            return true;
        }
        // matches contains one of the following:
        //     if (matches[6]) : 6=unitid, 7=unumber, 8=quizid, 9=qnumber
        //     if (matches[7]) : 7=unitid, 8=unumber, 9=quizid
        //     if (matches[8]) : 8=unitid, 9=unumber
        //     if (matches[9]) : 9=unitid

        var ancestors = new Array();
        var descendant = new_regexp('selected' + matches[1]);

        var i_max = obj.length;
        for (var i=0; i<i_max; i++) {
            if (obj[i].type && obj[i].type=='checkbox' && obj[i].name) {
                if (obj[i].name.match(descendant)) {
                    obj[i].checked = checkbox.checked;
                } else if (checkbox.name.match(new_regexp(obj[i].name))) {
                    ancestors.push(i);
                }
            }
        }

        while (i = ancestors.pop()) {

            // create a RegExp to find child (=a direct descendant) of this ancestor
            if (obj[i].name=='selected[0]') {
                var name = 'selected';
            } else {
                var name = obj[i].name;
            }
            var child = new_regexp(name, '\\[\\d+\\]\\[0\\]$');

            var checked = 0;
            var unchecked = 0;

            for (var ii=0; ii<i_max; ii++) {
                if (i==ii) {
                    continue; // skip current ancestor
                }
                if (obj[ii].type && obj[ii].type=='checkbox' && obj[ii].name) {
                    if (obj[ii].name.match(child)) {
                        if (obj[ii].checked) {
                            checked++;
                        } else {
                            unchecked++;
                        }
                    }
                }
            }

            if (checked && ! unchecked) {
                // all child checkboxes are checked
                obj[i].checked = true;
            } else {
                // at least one child checkbox is not checked
                obj[i].checked = false;
            }
        } // end while

        return true;
    }

    function reverseSortNumber(a,b) {
        return b - a;
    }

    function new_regexp(str, suffix) {
        if (typeof(suffix)=='undefined') {
            suffix = '(\\[\\d+\\])+$';

            // remove trailing [0] and change other [0] to [\d+]
            str = str.replace(new RegExp('(\\[0\\])+$', 'g'), '').replace(new RegExp('\\[0\\]', 'g'), '[\\d+]');
        }

        // escape opening and closing square brackets
        str = str.replace(new RegExp('\\[', 'g'), '\\[').replace(new RegExp('\\]', 'g'), '\\]');

        // return a regular expression object
        return new RegExp('^' + str + suffix);
    }

    function showhide_quizports(img, tableid, display) {

        // locate the table
        var table = document.getElementById(tableid);
        if (! table) {
            return false;
        }

        // locate the images in the table
        var images = table.getElementsByTagName('img');
        if (! images) {
            return false;
        }

        // set the target src and id for the images we are interested in
        if (display) {
            var targetsrc = new RegExp('minus.gif');
        } else {
            var targetsrc = new RegExp('plus.gif');
        }
        var targetid = new RegExp('^img_showhide_quizport_\\d+');

        // temporarily disable the fixing of rowspans
        table.setAttribute('fixrowspans', '');

        // collapse/expand all quizports
        var i_max = images.length;
        for (var i=0; i<i_max; i++) {
            if (images[i].src && images[i].src.match(targetsrc)) {
                if (images[i].id && images[i].id.match(targetid)) {
                    if (typeof(images[i].onclick)=='function') {
                        images[i].onclick();
                    }
                }
            }
        }

        // fix rowspans
        table.setAttribute('fixrowspans', true);
        fix_rowspans(tableid);

        // toggle the showhide icon
        if (display=='none') {
            img.src = img.src.replace('minus', 'plus');
            img.onclick = new Function("showhide_quizports(this,'"+tableid+"','')");
        } else {
            img.src = img.src.replace('plus', 'minus');
            img.onclick = new Function("showhide_quizports(this,'"+tableid+"','none')");
        }
    }

    function showhide_quizport(img, tableid, rowmask, display) {

        // locate the table
        var table = document.getElementById(tableid);
        if (! table) {
            return false;
        }

        // locate the rows in the table
        var rows = table.getElementsByTagName('tr');
        if (! rows) {
            return false;
        }

        // set the target id mask for the rows we are interested in
        var targetrowid = new RegExp('^'+rowmask);
        var targetrows = new Array();

        // locate the rows we are interested in
        var r_max = rows.length;
        for (var r=0; r<r_max; r++) {
            if (rows[r].id && rows[r].id.match(targetrowid)) {
                targetrows[r] = true;
            }
        }

        var m = navigator.userAgent.match(new RegExp('MSIE (\\d+)'));
        if (m && m[1]<=7) {
            // IE7, IE6, IE5 ...
            isNonStandardIE = true;
        } else {
            // Firefox, Safari, Opera, IE8+
            isNonStandardIE = false;
        }

        // collapse/expand the target rows
        for (var targetrow in targetrows) {

            if (isNonStandardIE) {
                rows[targetrow].style.display = display;
            } else {
                // Safari 1, Firefox 2, and Opera mess up the display when hiding the whole row
                // that is spanned by a multispan cell. Workaround is to hide each cell individually
                var cells = rows[targetrow].getElementsByTagName('td');
                if (! cells) {
                    continue;
                }
                var c_max = cells.length;
                for (var c=0; c<c_max; c++) {
                    cells[c].style.display = display;
                }
            }

            if (display=='none') {
                rows[targetrow].setAttribute('isCollapsed', true);
            } else {
                rows[targetrow].setAttribute('isCollapsed', '');
            }
        }

        // fix rowspans
        fix_rowspans(tableid);

        // toggle the showhide icon
        if (display=='none') {
            img.src = img.src.replace('minus', 'plus');
            img.onclick = new Function("showhide_quizport(this,'"+tableid+"','"+rowmask+"','')");
        } else {
            img.src = img.src.replace('plus', 'minus');
            img.onclick = new Function("showhide_quizport(this,'"+tableid+"','"+rowmask+"','none')");
        }
    } // end function showhide_quizport()

    function fix_rowspans(tableid) {
        // Internet Explorer
        //     if the last row spanned by a multi-row cell is hidden, then the height of the cell is reduced to that of a single row
        //     the workaround is to reset the rowspan such that the last row of the span is the last visible row of the spanned rows
        // Firefox
        //     also seems to benefit from the row span fix. Without it, the multi-row cell is slightly too tall

        // locate the table
        var table = document.getElementById(tableid);
        if (! table) {
            return;
        }

        // make sure fixrowspans is currently enabled for this table
        // it may have been disabled by showhide_quizports()
        if (! table.getAttribute('fixrowspans')) {
            return;
        }

        // locate the table rows
        var rows = table.getElementsByTagName('tr');
        if (! rows) {
            return;
        }

        // go backwards through the rows to minimize the flicker on slow browsers/computers
        var r_max = rows.length;
        for (var r=r_max-1; r>=0; r--) {

            // skip collapsed rows
            if (row_is_collapsed(rows[r])) {
                continue;
            }

            // locate the cells in this row
            var cells = rows[r].getElementsByTagName('td');
            if (! cells) {
                continue;
            }

            // go forwards through through the cells in this row, othersise Firefox 2 gets confused with
            // borders near the bottom of the table  - specifically column 0 of the last Unit row
            var c_max = cells.length;
            for (var c=0; c<c_max; c++) {

                // get the original row span for this cell
                var originalRowSpan = cells[c].getAttribute('originalRowSpan');
                if (originalRowSpan) {
                    originalRowSpan = parseInt(originalRowSpan);
                } else {
                    var rowSpan = cells[c].getAttribute('rowSpan');
                    if (rowSpan) {
                        rowSpan = parseInt(rowSpan);
                    } else {
                        rowSpan = 1;
                        cells[c].setAttribute('rowSpan', rowSpan);
                    }
                    originalRowSpan = rowSpan;
                    cells[c].setAttribute('originalRowSpan', originalRowSpan);
                }

                // skip single row cells
                if (originalRowSpan==1) {
                    continue;
                }

                // get the last visible row that this cell spans
                var lastrow = r + originalRowSpan;
                while (lastrow>r && row_is_collapsed(rows[lastrow-1])) {
                    lastrow--;
                }

                // set the rowspan, so that it only goes up to the last visible row
                cells[c].setAttribute('rowSpan', lastrow - r);
            }
        }
    }

    function row_is_collapsed(row) {
        return (row==null || row.getAttribute('isCollapsed'));
    }

    function collapse_tables() {
        // search through the quizport tables, and trigger the onclick event for any "img_showhide_all" images

        // locate tables
        var tables = document.getElementsByTagName('table');
        if (! tables) {
            return false;
        }

        // set id mask for tables and images we are interested in
        var targettableid = new RegExp('^(quizport|unit)');
        var targetimgid = new RegExp('^img_showhide_(all|quizport)');

        var t_max = tables.length;
        for (var t=0; t<t_max; t++) {

            // check table id
            if (! tables[t].id || ! tables[t].id.match(targettableid)) {
                continue;
            }

            // locate images in this table
            var images = tables[t].getElementsByTagName('img');
            if (! images) {
                continue;
            }

            // temporarily disable the fixing of rowspans
            tables[t].setAttribute('fixrowspans', '');

            // go backwards through the images to prevent problems for IE
            // e.g. displaying teacher's quizportindextable
            var i_max = images.length;
            for (var i=i_max-1; i>=0; i--) {

                // check image id
                if (! images[i].id || ! images[i].id.match(targetimgid)) {
                    continue;
                }

                // trigger the onlick function if there is one
                // (this is what actually collapses the rows)
                if (typeof(images[i].onclick)=='function') {
                    images[i].onclick();
                }
            }

            // fix the rowspans now, if it has not already been done
            // e,g, by img_showhide_all on teacher's quizportindextable
            if (! tables[t].getAttribute('fixrowspans')) {
                tables[t].setAttribute('fixrowspans', true);
                fix_rowspans(tables[t].id);
            }
        }
    } // end function collapse_tables()

    window.onload = collapse_tables;
//]]>
</script>
<?php
    } // end function print_js
} // end class
?>