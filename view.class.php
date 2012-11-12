<?php // $Id$

class mod_quizport_view extends mod_quizport {
    var $pagehasreporttab = true;

    function print_page() {
        global $CFG;
        if ($this->quizid==QUIZPORT_CONDITIONQUIZID_ENDOFUNIT && empty($this->unit->exitpage)) {
            if ($this->require_exitgrade() && $this->unitattempt->grade < $this->unit->exitgrade) {
                // insufficient grade to proceed to next activity, so do automatic retry
                $href = $CFG->wwwroot.'/mod/quizport/view.php?id='.$this->modulerecord->id.'&amp;tab='.$this->tab;
            } else if ($cm = quizport_get_cm($this->courserecord, $this->modulerecord, $this->unit->exitcm, 'exit')) {
                // proceed to next activity
                $href = $CFG->wwwroot.'/mod/'.$cm->mod.'/view.php?id='.$cm->cm;
                if ($cm->mod=='quizport') {
                    $href .= '&amp;tab='.$this->tab;
                }
            } else {
                // no next activity - so proceed to course page
                $href = $CFG->wwwroot.'/course/view.php?id='.$this->courserecord->id;
            }
            redirect($href);
        } else {
            parent::print_page();
        }
    }

    function print_heading() {
        if ($this->quizid==0) {
            // entry page
            $print_heading = ($this->unit->entrypage && $this->unit->entryoptions & QUIZPORT_ENTRYOPTIONS_TITLE);
        } else if ($this->quizid==QUIZPORT_CONDITIONQUIZID_ENDOFUNIT) {
            // exit page
            $print_heading = ($this->unit->exitpage && $this->unit->exitoptions & QUIZPORT_EXITOPTIONS_TITLE);
        } else {
            // quiz or menu page
            $print_heading = true;
        }
        if ($print_heading) {
            print_heading(format_string($this->activityrecord->name));
        }
    }

    function print_content() {
        switch ($this->quizid) {
            case 0:
                // Print quizport entry text, if required
                $this->print_entrytext();

                // print warnings about access restrictions, if necessary
                $this->print_warnings('unit');

                // Print information about number of attempts, grading method and time limit
                if ($this->unit->entryoptions & QUIZPORT_ENTRYOPTIONS_GRADING) {
                    $this->print_grading_info('unit');
                }

                // print open / close dates, if necessary
                if ($this->unit->entryoptions & QUIZPORT_ENTRYOPTIONS_DATES) {
                    $this->print_dates('unit');
                }

                // print summary of attempts by this user at this unit
                if ($this->unit->entryoptions & QUIZPORT_ENTRYOPTIONS_ATTEMPTS) {
                    $this->print_attemptssummary('unit');
                }

                // print button to start new attempt (if allowed)
                $this->print_startattemptbutton('unit');
                // $this->print_unit_fancystartbutton('unit');

                break;

            case QUIZPORT_CONDITIONQUIZID_MENUNEXT:
            case QUIZPORT_CONDITIONQUIZID_MENUNEXTONE:
            case QUIZPORT_CONDITIONQUIZID_MENUALL:
            case QUIZPORT_CONDITIONQUIZID_MENUALLONE:
                $this->print_quizmenu($this->quizid);
                break;

            case QUIZPORT_CONDITIONQUIZID_ENDOFUNIT:
                $this->print_exitpage();
                break;

            default:
                // something's wrong !!
                print "Unrecognized quiz id : $this->quizid";

        } // end switch $this->quizid
    }

    function whatnext($str='') {
        switch ($str) {
            case '':
                $whatnext = get_string('exit_whatnext_default', 'quizport');
                break;

            case 'exit_whatnext':
                switch (mt_rand(0,1)) { // random 0 or 1. You can add more if you like
                    case 0: $whatnext = get_string('exit_whatnext_0', 'quizport'); break;
                    case 1: $whatnext = get_string('exit_whatnext_1', 'quizport'); break;
                }
                break;

            default:
                $whatnext = get_string($str, 'quizport');
        }
        return '<p class="quizportwhatnext">'.$whatnext.'</p>';
    }

    function print_exitpage() {
        global $CFG;

        if ($CFG->majorrelease<=1.4) {
            $percentsign = '%%';
        } else {
            $percentsign = '%';
        }

        $message = '';
        $feedback = array();

        if ($this->unit->gradelimit==0 || $this->unit->gradeweighting==0) {
            if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_UNITATTEMPT || $this->unit->exitoptions & QUIZPORT_EXITOPTIONS_UNITATTEMPT) {
                $feedback[] = '<big>'.get_string('exit_noscore', 'quizport').'</big>';
            }
        } else if ($this->get_unitgrade() && $this->get_unitattempt()) {
            if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_ENCOURAGEMENT) {
                switch (true) {
                    case $this->unitattempt->grade >= 90:
                        $encouragement = get_string('exit_excellent', 'quizport');
                        break;
                    case $this->unitattempt->grade >= 60:
                        $encouragement = get_string('exit_welldone', 'quizport');
                        break;
                    case $this->unitattempt->grade > 0:
                        $encouragement = get_string('exit_goodtry', 'quizport');
                        break;
                    default:
                        $encouragement = get_string('exit_areyouok', 'quizport');
                }
                $feedback[] = '<big>'.$encouragement.'</big>';
            }
            if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_UNITATTEMPT) {
                $feedback[] = get_string('exit_unitattempt', 'quizport', $this->unitattempt->grade.$percentsign);
            }
            if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_UNITGRADE) {
                switch ($this->unit->grademethod) {
                    case QUIZPORT_GRADEMETHOD_HIGHEST:
                        if ($this->unitattempt->grade < $this->unitgrade->grade) {
                            // current attempt is less than the highest so far
                            $feedback[] = get_string('exit_unitgrade_highest', 'quizport', $this->unitgrade->grade.$percentsign);
                        } else if ($this->unitattempt->grade==0) {
                            // zero score is best so far
                            $feedback[] = get_string('exit_unitgrade_highest_zero', 'quizport', $this->unitgrade->grade.$percentsign);
                        } else if ($this->get_unitattempts()) {
                            // current attempt is highest so far
                            $maxgrade = null;
                            foreach ($this->unitattempts as $unitattempt) {
                                if ($unitattempt->id==$this->unitattempt->id) {
                                    continue; // skip current unit attempt
                                }
                                if (is_null($maxgrade) || $maxgrade<$unitattempt->grade) {
                                    $maxgrade = $unitattempt->grade;
                                }
                            }
                            if (is_null($maxgrade)) {
                                // do nothing (no previous unit attempt)
                            } else if ($maxgrade==$this->unitattempt->grade) {
                                // unit attempt grade equals previous best
                                $feedback[] = get_string('exit_unitgrade_highest_equal', 'quizport');
                            } else {
                                $feedback[] = get_string('exit_unitgrade_highest_previous', 'quizport', $maxgrade.$percentsign);
                            }
                        }
                        break;
                    case QUIZPORT_GRADEMETHOD_AVERAGE:
                        $feedback[] = get_string('exit_unitgrade_average', 'quizport', $this->unitgrade->grade.$percentsign);
                        break;
                    // case QUIZPORT_GRADEMETHOD_TOTAL:
                    // case QUIZPORT_GRADEMETHOD_FIRST:
                    // case QUIZPORT_GRADEMETHOD_LAST:
                    default:
                        $feedback[] = get_string('exit_unitgrade', 'quizport', $this->unitgrade->grade.$percentsign);
                        break;
                }
            }
        }
        if (count($feedback)) {
            $message .= '<p>'.implode('<br />', $feedback)."</p>\n";
        }

        if ($this->unit->exittext) {
            $message .= '<div class="exittext">'.filter_text($this->unit->exittext).'</div>';
        }

        // if we are in a popup, we want to close it
        if ($this->inpopup) {
            $onclick = ' onclick="if(self.opener&&!self.opener.closed)self.opener.location=this.href;self.close();"';
        } else {
            $onclick = '';
        }

        $rows = array();
        if ($this->unitattempt->status==QUIZPORT_STATUS_COMPLETED) {
            if ($this->require_exitgrade() && $this->unitattempt->grade < $this->unit->exitgrade) {
                $cm = false; // unsufficient grade to show link to next activity
            } else {
                $cm = quizport_get_cm($this->courserecord, $this->modulerecord, $this->unit->exitcm, 'exit');
            }
            if ($cm) {
                // next activity, if there is one
                $href = $CFG->wwwroot.'/mod/'.$cm->mod.'/view.php?id='.$cm->cm;
                if ($cm->mod=='quizport' && $this->inpopup) {
                    $href .= '&amp;inpopup='.$this->inpopup;
                }
                if (isset($cm->extra) && strpos($cm->extra, 'openpopup') && $this->inpopup) {
                    $extra = '';
                } else {
                    $extra = $onclick;
                }
                $rows[] = '<tr><th><a href="'.$href.'"'.$extra.'>'.get_string('exit_next', 'quizport').'</a></th><td>'.get_string('exit_next_text', 'quizport').': <a href="'.$href.'"'.$extra.'>'.format_string(urldecode($cm->name)).'</a></td></tr>';
            }
        }
        if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_RETRY) {
            // retry this quizport, if allowed
            if ($this->unit->attemptlimit==0 || empty($this->unitattempts) || $this->unit->attemptlimit<count($this->unitattempts)) {
                $href = $CFG->wwwroot.'/mod/quizport/view.php?id='.$this->modulerecord->id.'&amp;unumber=-1&amp;tab='.$this->tab.'&amp;inpopup='.$this->inpopup;
                $rows[] = '<tr><th><a href="'.$href.'">'.get_string('exit_retry', 'quizport').'</a></th><td>'.get_string('exit_retry_text', 'quizport').': <a href="'.$href.'">'.format_string($this->quizport->name).'</a></td></tr>';
            }
        }
        if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_INDEX) {
            $href = $CFG->wwwroot.'/mod/quizport/index.php?id='.$this->courserecord->id;
            $rows[] = '<tr><th><a href="'.$href.'"'.$onclick.'>'.get_string('exit_index', 'quizport').'</a></th><td>'.get_string('exit_index_text', 'quizport').'</td></tr>';
        }
        if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_COURSE) {
            $href = $CFG->wwwroot.'/course/view.php?id='.$this->courserecord->id;
            $rows[] = '<tr><th><a href="'.$href.'"'.$onclick.'>'.get_string('exit_course', 'quizport').'</a></th><td>'.get_string('exit_course_text', 'quizport').'</td></tr>';
        }
        if ($this->unit->exitoptions & QUIZPORT_EXITOPTIONS_GRADES) {
            if (isset($this->courserecord->showgrades)) {
                $showgrades = $this->courserecord->showgrades;
            } else {
                $showgrades = 1; // Moodle 1.0
            }
            if ($showgrades && $this->unit->gradelimit && $this->unit->gradeweighting) {
                if ($CFG->majorrelease<=1.4) {
                    $href = $CFG->wwwroot.'/course/grade.php?id='.$this->courserecord->id;
                } else {
                    $href = $CFG->wwwroot.'/grade/index.php?id='.$this->courserecord->id;
                }
                $rows[] = '<tr><th><a href="'.$href.'"'.$onclick.'>'.get_string('exit_grades', 'quizport').'</a></th><td>'.get_string('exit_grades_text', 'quizport').'</td></tr>';
            }
        }

        if ($count = count($rows)) {
            if ($count>1) {
                $message .= $this->whatnext('exit_whatnext');
            }
            $message .= '<table><tbody>'.implode("\n", $rows).'</tbody></table>';
        }
        if ($message) {
            print_box($message, 'generalbox', 'centeredboxtable');
            $this->print_js_reloadcoursepage();
        } else {
            redirect($CFG->wwwroot.'/course/view.php?id='.$this->courserecord->id);
        }
    }

    function require_exitgrade() {
        if ($this->unit->exitcm==0 && $this->unit->exitgrade==0) {
            return false; // no exit grade required
        }
        // return true if there is a unit grade and unit attempt, otherwise return false
        return ($this->get_unitgrade() && $this->get_unitattempt());
    }

    function print_js_reloadcoursepage($return=false) {
        $str = ''
            .'<script type="text/javascript">'."\n"
            .'//<![CDATA['."\n"
            ."function quizport_addEventListener(obj, str, fn, bool) {\n"
            ."	if (obj.addEventListener) {\n"
            ."		obj.addEventListener(str, fn, bool);\n"
            ."	} else if (obj.attachEvent) {\n"
            ."		obj.attachEvent('on'+str, fn);\n"
            ."	} else {\n"
            ."		obj['on'+str] = fn;\n"
            ."	}\n"
            ."}\n"
            ."function quizport_removeEventListener(obj, str, fn, bool) {\n"
            ."	if (obj.removeEventListener) {\n"
            ."		obj.removeEventListener(str, fn, bool);\n"
            ."	} else if (obj.detachEvent) {\n"
            ."		obj.detachEvent('on'+str, fn);\n"
            ."	} else {\n"
            ."		obj['on'+str] = null;\n"
            ."	}\n"
            ."}\n"
            ."function quizport_onload() {\n"
            ."	// fancy code to allow IE to detect onclose.\n"
            ."	// if any links are clicked they will unset this flag\n"
            ."	window.quizport_onclose = true;\n"
            ."	var links = document.getElementsByTagName('a');\n"
            ."	if (links) {\n"
            ."		var i_max = links.length;\n"
            ."		for (var i=0; i<i_max; i++) {\n"
            ."			if (links[i].href && links[i].onclick==null) {\n"
            ."				links[i].onclick = function() {\n"
            ."					window.quizport_onclose = false;\n"
            ."					return true;\n"
            ."				};\n"
            ."			}\n"
            ."		}\n"
            ."		links = null;\n"
            ."	}\n"
            ."	// fancy code to allow IE to detect onblur properly.\n"
            ."	// thanks to Vladimir Kelman for ideas found at:\n"
            ."	// http://www.codingforums.com/showthread.php?t=76312\n"
            ."	if (navigator.appName=='Microsoft Internet Explorer') {\n"
            ."		window.quizport_activeElement = document.activeElement;\n"
            ."	}\n"
            ."	return true;\n"
            ."}\n"
            ."function quizport_onunload() {\n"
            ."	if (window.quizport_onclose) {\n"
            ."		quizport_refreshcoursepage();\n"
            ."	}\n"
            ."	return true;\n"
            ."}\n"
            ."function quizport_onblur() {\n"
            ."	if (navigator.appName=='Microsoft Internet Explorer' && (window.quizport_activeElement != document.activeElement)) {\n"
            ."		window.quizport_activeElement = document.activeElement;\n"
            ."	} else {\n"
            ."		quizport_refreshcoursepage();\n"
            ."	}\n"
            ."	return true;\n"
            ."}\n"
            ."function quizport_refreshcoursepage() {\n"
            ."	var refreshcoursepage = false;\n"
            ."	if (window.opener && ! opener.closed) {\n"
            ."		if (opener.location.href.match('/course/view.php')) {\n"
            ."			refreshcoursepage = true;\n"
            ."		}\n"
            ."	}\n"
            ."	if (refreshcoursepage) {\n"
            ."		var target_src = new RegExp('^(.*)(quizport/courselinks\\\\.js\\\\.php\\\\?)(.*)(rnd=[0-9]+)(.*)$');\n"
            ."		var obj = opener.document.getElementsByTagName('script');\n"
            ."		if (obj) {\n"
            ."			var i_max = obj.length;\n"
            ."			for (var i=0; i<i_max; i++) {\n"
            ."				if (! obj[i].src) {\n"
            ."					continue;\n"
            ."				}\n"
            ."				var m = obj[i].src.match(target_src);\n"
            ."				if (! m) {\n"
            ."					continue;\n"
            ."				}\n"
            ."				opener.location.reload();\n"
            ."				break;\n"
            ."			}\n"
            ."		}\n"
            ."		obj = null;\n"
            ."	}\n"
            ."	quizport_removeEventListener(self, 'blur', quizport_onblur, false);\n"
            ."	quizport_removeEventListener(self, 'unload', quizport_onunload, false);\n"
            ."}\n"
            ."quizport_addEventListener(self, 'load', quizport_onload, false);\n"
            ."quizport_addEventListener(self, 'blur', quizport_onblur, false);\n"
            ."quizport_addEventListener(self, 'unload', quizport_onunload, false);\n"
            .'//]]>'."\n"
            ."</script>\n"
        ;

        if ($return) {
            return $str;
        } else {
            print $str;
        }
    }

    function print_entrytext() {
        if ($this->unit->entrypage) {
            if ($text = filter_text($this->unit->entrytext)) {
                print_box($text, 'generalbox', 'quizportentrytext');
            }
        }
    }

    function print_grading_info($type) {
        if ($type=='unit') {
            $grade = 'grade';
        } else {
            $grade = 'score';
        }
        $gradelimit = $grade.'limit';
        $grademethod = $grade.'method';
        $gradeweighting = $grade.'weighting';

        $info = '';
        if ($this->$type->attemptlimit > 1) {
            $info .= '<tr><th class="c0">'.get_string($type.'attemptsallowed', 'quizport').':</th><td class="c1">'.$this->$type->attemptlimit.'</td></tr>';
        }
        if ($this->$type->$gradeweighting && $this->$type->$gradelimit && $this->$type->attemptlimit != 1) {
            $info .= '<tr><th class="c0">'.get_string($grademethod, 'quizport').':</th><td class="c1">'.quizport_format_grademethod($type, $this->$type->$grademethod).'</td></tr>';
        }
        if ($this->$type->timelimit > 0) {
            $info .= '<tr><th class="c0">'.get_string($type.'timelimit', 'quizport').':</th><td class="c1">'.format_time($this->$type->timelimit).'</td></tr>';
        }
        if ($info) {
            print_box('<table>'.$info.'</table>', 'generalbox', 'quizportgradinginfo');
        }
    }

    function print_dates($type) {
        $dates = '';
        $dateformat = get_string('strftimerecentfull'); // strftimedaydatetime, strftimedatetime
        if ($this->$type->timeopen) {
            $dates .= '<tr><th class="c0">'.get_string($type.'open', 'quizport').':</th><td class="c1">'.userdate($this->$type->timeopen, $dateformat).'</td></tr>';
        }
        if ($this->$type->timeclose) {
            $dates .= '<tr><th class="c0">'.get_string($type.'close', 'quizport').':</th><td class="c1">'.userdate($this->$type->timeclose, $dateformat).'</td></tr>';
        }
        if ($dates) {
            print_box('<table>'.$dates.'</table>', 'generalbox', 'quizportdates');
        }
    }

    function print_warnings($type) {
        global $CFG, $USER;
        $warnings = '';

        $allowstart = true;
        $allowresume = $this->$type->allowresume;

        if (! has_capability('mod/quizport:preview', $this->modulecontext)) {
            if ($type=='unit') {
                if ($error = $this->require_unit_quizzes()) {
                    // there are no quizzes in this unit
                    $warnings .= '<li>'.$error.'</li>';
                    $allowstart = false;
                    $allowresume = false;
                }
            }
            if ($error = $this->require_isopen('unit')) {
                // unit/quiz is not (yet) open
                $warnings .= '<li>'.$error.'</li>';
                $allowstart = false;
                $allowresume = false;
            }
            if ($error = $this->require_notclosed('unit')) {
                // unit/quiz is (already) closed
                $warnings .= '<li>'.$error.'</li>';
                $allowstart = false;
                $allowresume = false;
            }
            if ($error = $this->require_unit_entrycm()) {
                // minimum grade for previous activity not satisfied
                $warnings .= '<li>'.$error.'</li>';
                $allowstart = false;
                $allowresume = false;
            }
            if ($error = $this->require_delay('unit', 'delay1')) {
                // delay1 has not expired yet
                $warnings .= '<li>'.$error.'</li>';
                $allowstart = false;
            }
            if ($error = $this->require_delay('unit', 'delay2')) {
                // delay2 has not expired yet
                $warnings .= '<li>'.$error.'</li>';
                $allowstart = false;
            }
            if ($error = $this->require_moreattempts('unit', true)) {
                // maximum number of attempts reached
                $warnings .= '<li>'.$error.'</li>';
                $allowstart = false;
            }
        }
        if ($warnings) {
            print_box('<ul>'.$warnings.'</ul>', 'generalbox', 'quizportwarnings');
        }

        $startattempts = "start{$type}attempts";
        $resumeattempts = "resume{$type}attempts";

        $this->$startattempts = $allowstart;
        $this->$resumeattempts = $allowresume;
    }

    function print_attemptssummary($type) {
        global $CFG;

        $this->get_attempts($type);

        $attempts = "{$type}attempts";
        $countattempts = "count{$type}attempts";
        $resumeattempts = "resume{$type}attempts";

        $attemptids = array(
            'all' => array(),
            'inprogress' => array(),
            'timedout'   => array(),
            'abandoned'  => array(),
            'completed'  => array(),
            'zeroduration' => array(),
            'zeroscore' => array()
        );

        if ($type=='unit') {
            $number = 'unumber';
            $grade = 'grade';
        } else {
            $number = 'qnumber';
            $grade = 'score';
        }
        $gradelimit = $grade.'limit';
        $grademethod = $grade.'method';
        $gradeweighting = $grade.'weighting';

        if ($this->$attempts) {
            // show summary of attempts so far

            $dateformat = get_string('strftimerecentfull');
            $strresume = get_string('resume', 'quizport');

            // cache showselectcolumn
            if (has_capability('mod/quizport:deleteattempts', $this->modulecontext)) {
                $showselectcolumn = true; // false;
            } else {
                $showselectcolumn = false;
            }

            if (has_capability('mod/quizport:viewreports', $this->modulecontext)) {
                // teacher
                $has_capability_review = true;
                $resumetab = 'preview';
            } else if (has_capability('mod/quizport:reviewmyattempts', $this->modulecontext)) {
                // student
                $startattempts = "start{$type}attempts";
                $has_capability_review = $this->$startattempts;
                $resumetab = 'info';
            } else {
                // somebody else - guest?
                $has_capability_review = false;
                $resumetab = '';
            }

            // start attempts table (info + resume buttons)
            $table = new stdClass();
            $table->class = 'generaltable';
            $table->id = 'quizportattemptssummary';
            $table->head = array(
                get_string($number, 'quizport'),
                get_string('status', 'quizport'),
                get_string('duration', 'quizport'),
                get_string('lastaccess', 'quizport')
            );
            $table->align = array('center', 'center', 'left', 'left');
            $table->size = array('', '', '', '');
            if ($this->$type->$gradelimit && $this->$type->$gradeweighting) {
                // insert grade column
                array_splice($table->head, 1, 0, array(get_string($grade, 'quizport')));
                array_splice($table->align, 1, 0, array('center'));
                array_splice($table->size, 1, 0, array(''));
            }
            if ($showselectcolumn) {
                // prepend select column
                array_splice($table->head, 0, 0, '&nbsp;');
                array_splice($table->align, 0, 0, array('center'));
                array_splice($table->size, 0, 0, array(''));
            }
            if ($this->$resumeattempts) {
                // append resume column
                $table->head[] = '&nbsp;';
                $table->align[] = 'center';
                $table->size[] = '';
            }

            // print rows of attempt info
            foreach ($this->$attempts as $attempt) {
                $row = array();

                if ($showselectcolumn) {
                    $id = '['.$attempt->unitid.']['.$attempt->unumber.']';
                    $row[] = $this->print_checkbox('selected'.$id, 1, false, '', '', '', true);

                    switch ($attempt->status) {
                        case QUIZPORT_STATUS_INPROGRESS: $attemptids['inprogress'][] = $id; break;
                        case QUIZPORT_STATUS_TIMEDOUT: $attemptids['timedout'][] = $id; break;
                        case QUIZPORT_STATUS_ABANDONED: $attemptids['abandoned'][] = $id; break;
                        case QUIZPORT_STATUS_COMPLETED: $attemptids['completed'][] = $id; break;
                    }
                    if ($attempt->$grade==0) {
                        $attemptids['zero'.$grade][] = $id;
                    }
                    if ($attempt->duration==0) {
                        $attemptids['zeroduration'][] = $id;
                    }
                    $attemptids['all'][] = $id;
                }

                $row[] = $attempt->$number;
                if ($this->$type->$gradelimit && $this->$type->$gradeweighting) {
                    if ($has_capability_review) {
                        $href = $this->format_url('report.php', '', array('tab'=>'report', $type.'attemptid'=>$attempt->id));
                        $row[] = '<a href="'.$href.'">'.$attempt->$grade.'%</a>';
                    } else {
                        $row[] = $attempt->$grade.'%';
                    }
                }
                $row[] = quizport_format_status($attempt->status);
                $row[] = quizport_format_time($attempt->duration);
                $row[] = userdate($attempt->timemodified, $dateformat);

                if ($this->$resumeattempts) {
                    $cell = '&nbsp;';
                    if ($attempt->status==QUIZPORT_STATUS_INPROGRESS) {
                        if ($this->$type->timelimit && $attempt->duration > $this->$type->timelimit) {
                            // do nothing, this attempt has timed out
                        } else {
                            $params = array('tab'=>$resumetab, $type.'attemptid'=>$attempt->id);
                            $cell = ''
                                .'<a class="resumeattempt" href="'.$this->format_url('view.php', 'coursemoduleid', $params).'">'
                                .$strresume
                                //.'<img src="'.$CFG->pixpath.'/t/preview.gif" class="iconsmall" alt="'.$strresume.'" />'
                                .'</a>'
                            ;
                        }
                    }
                    $row[] = $cell;
                }

                $table->data[] = $row;
            }

            // start form if necessary
            if ($showselectcolumn) {
                $onsubmit = ''
                    ."var x=false;"
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
                    ."if(x){"
                        ."x=confirm('".get_string('confirmdeleteattempts', 'quizport')."');"
                    ."}"
                    ."if(this.elements['confirmed']){"
                        ."this.elements['confirmed'].value=(x?1:0);"
                    ."}"
                    ."return x;"
                ;
                $this->print_form_start('view.php', array(), false, false, array('onsubmit' => $onsubmit));
                print '<input type="hidden" name="confirmed" value="0" />'."\n";
                print '<input type="hidden" name="action" value="deleteselected" />'."\n";
                print '<input type="hidden" name="userlist" value="'.$this->userid.'" />'."\n";
           }

            // print the summary of attempts
            $this->print_table($table);

            // end form if necessary
            if ($showselectcolumn) {
                print_box_start('generalbox', 'quizportdeleteattempts');
                print ''
                    .'<script type="text/javascript">'."\n"
                    .'//<!CDATA['."\n"
                    ."function quizport_set_checked(nameFilter, indexFilter, checkedValue) {\n"
                    ."	var partMatchName = new RegExp(nameFilter);\n"
                    ."	var fullMatchName = new RegExp(nameFilter+indexFilter);\n"
                    ."	var inputs = document.getElementsByTagName('input');\n"
                    ."	if (inputs) {\n"
                    ."		var i_max = inputs.length;\n"
                    ."	} else {\n"
                    ."		var i_max = 0;\n"
                    ."	}\n"
                    ."	for (var i=0; i<i_max; i++) {\n"
                    ."		if (inputs[i].type=='checkbox' && inputs[i].name.match(partMatchName)) {\n"
                    ."			if (inputs[i].name.match(fullMatchName)) {\n"
                    ."				inputs[i].checked = checkedValue;\n"
                    ."			} else {\n"
                    ."				inputs[i].checked = false;\n"
                    ."			}\n"
                    ."		}\n"
                    ."	}\n"
                    ."	return true;\n"
                    ."}\n"
                    ."function quizport_set_checked_attempts(obj) {\n"
                    ."	var indexFilter = obj.options[obj.selectedIndex].value;\n"
                    ."	if (indexFilter=='none') {\n"
                    ."		checkedValue = 0;\n"
                    ."	} else {\n"
                    ."		checkedValue = 1;\n"
                    ."	}\n"
                    ."	if (indexFilter=='none' || indexFilter=='all') {\n"
                    ."		indexFilter = '\\\\[\\\\d+\\\\]\\\\[\\\\d+\\\\]';\n"
                    ."	} else {\n"
                    ."		indexFilter = indexFilter.replace(new RegExp('^[^:]*:'), '');\n"
                    ."		indexFilter = indexFilter.replace(new RegExp(',', 'g'), '|');\n"
                    ."		indexFilter = indexFilter.replace(new RegExp('\\\\[', 'g'), '\\\\[');\n"
                    ."		indexFilter = indexFilter.replace(new RegExp('\\\\]', 'g'), '\\\\]');\n"
                    ."	}\n"
                    ."	quizport_set_checked('selected', indexFilter, checkedValue);"
                    ."}\n"
                    .'//]]>'."\n"
                    .'</script>'."\n"
                ;

                // set up attempt status drop down menu
                $options = array(
                    'none' => get_string('none')
                );
                foreach($attemptids as $type=>$ids) {
                    if ($total = count($ids)) {
                        if ($type=='all') {
                            $options['all'] = get_string('all');
                            if ($total > 1) {
                                $options['all'] .= " ($total)";
                            }
                        } else {
                            $options[$type.':'.implode(',', $ids)] = get_string($type, 'quizport')." ($total)";
                        }
                    }
                }

                // print attempt selection and deletion form
                print '<table><tr><th>';
                print get_string('selectattempts', 'quizport').':';
                print '</th><td>';
                choose_from_menu($options, 'selectattempts', '', '', 'return quizport_set_checked_attempts(this)');
                print '</td></tr><tr><th>&nbsp;</th><td>';
                print '<input type="submit" value="'.get_string('deleteattempts', 'quizport').'"/>';
                print '</td></tr></table>'."\n";

                print_box_end();
                $this->print_form_end();
            }
        }
    }

    function print_startattemptbutton($type) {
        global $CFG, $USER;


        if (has_capability('mod/quizport:preview', $this->modulecontext)) {
            // teacher
            $has_capability_attempt = true;
            $button_string = "preview{$type}now";
            $tab = 'preview';
        } else if (has_capability('mod/quizport:attempt', $this->modulecontext)) {
            // student
            $startattempts = "start{$type}attempts";
            $has_capability_attempt = $this->$startattempts;
            $button_string = "start{$type}attempt";
            $tab = 'info';
        } else {
            // somebody else - guest?
            $has_capability_attempt = false;
            $button_string = '';
            $tab = '';
        }
        if ($has_capability_attempt) {
            if ($type=='unit') {
                $unumber = -1; // new unit attempt
                $qnumber = 0; // undefined
            } else {
                $unumber = $this->unumber;
                $qnumber = -1; // new quiz attempt
            }
            if ($CFG->majorrelease>=1.5) {
                print '<div class="continuebutton">';
            } else {
                print '<div style="text-align:center">';
            }
            $this->print_single_button(
                "$CFG->wwwroot/mod/quizport/view.php",
                array(
                    'id' => $this->modulerecord->id,
                    'unumber' => $unumber,
                    'qnumber' => $qnumber,
                    'tab' => $tab,
                    'inpopup' => $this->inpopup
                ),
                get_string($button_string, 'quizport')
            );
            print '</div>';
        } else {
            //print_heading(get_string('nomoreattempts', 'quiz'));
            print_continue($CFG->wwwroot . '/course/view.php?id=' . $this->courserecord->id);
        }
    }

    function print_unit_fancystartbutton() {
        if (has_capability('mod/quizport:manage', $this->modulecontext)) {
            // teacher
            $has_capability_attempt = true;
            $tab = 'preview';
        } else if (has_capability('mod/quizport:attempt', $this->modulecontext)) {
            // student
            $startattempts = "start{$type}attempts";
            $has_capability_attempt = $this->$startattempts;
            $tab = 'info';
        } else {
            // somebody else - guest?
            $has_capability_attempt = false;
            $tab = '';
        }
        if ($has_capability_attempt) {
            $buttontext = get_string('attemptquiznow', 'quiz');
            $buttontext = htmlspecialchars($buttontext, ENT_QUOTES);
            $window = '_self';
            $windowoptions = '';
            $strconfirmstartattempt =  '';

            // Determine the URL to use.
            $attempturl = "view.php?id={$this->modulerecord->id}";
            if (!empty($CFG->usesid) && !isset($_COOKIE[session_name()])) {
                $attempturl = sid_process_url($attempturl);
            }

            print '<input type="button" value="'.$buttontext.'" onclick="javascript:';
            if ($strconfirmstartattempt) {
                print "if (confirm('".addslashes_js($strconfirmstartattempt)."')) ";
            }
            print "window.open('$attempturl','$window','$windowoptions');".'" />';

            print "\n<noscript>\n<div>\n";
            print_heading(get_string('noscript', 'quiz'));
            print "\n</div>\n</noscript>\n";
        }
    }

    function print_quizmenu($type) {
        global $CFG;

        $this->get_quizzes();
        $this->get_available_quizzes();

        switch ($type) {
            case QUIZPORT_CONDITIONQUIZID_MENUNEXT:
                // show menu of links to available quizzes and their scores
                // (unavailable quizzes are not shown)
                $showquizids = &$this->availablequizids;
                $linkquizids = &$this->availablequizids;
                break;
            case QUIZPORT_CONDITIONQUIZID_MENUNEXTONE:
                // show menu of available quizzes and their scores
                // with a link to the next unattempted quiz
                // (unavailable quizzes are not shown)
                $showquizids = &$this->availablequizids;
                $linkquizids = array($this->availablequizid);
                break;
            case QUIZPORT_CONDITIONQUIZID_MENUALL:
                // show menu of links to all quizzes and their scores
                // (unavailable quizzes are listed too, but with no link)
                $showquizids = array_keys($this->quizzes);
                $linkquizids = &$this->availablequizids;
                break;
            case QUIZPORT_CONDITIONQUIZID_MENUALLONE:
                // show menu of all quizzes and their scores
                // with a link to the next unattempted available quiz
                $showquizids = array_keys($this->quizzes);
                $linkquizids = array($this->availablequizid);
                break;
            default:
                return false;
        }

        $countquizscores = 0;
        $score_column = false;
        $resume_column = false;

        // get quiz scores
        if ($this->get_quizscores()) {
            foreach ($this->quizscores as $id=>$quizscore) {
                $quizid = $quizscore->quizid;
                if (in_array($quizid, $showquizids)) {
                    $this->quizzes[$quizid]->quizscore = &$this->quizscores[$id];
                    if ($this->quizzes[$quizid]->scorelimit && $this->quizzes[$quizid]->scoreweighting) {
                        $score_column = true;
                    }
                    if ($this->quizzes[$quizid]->quizscore->status==QUIZPORT_STATUS_INPROGRESS && $this->quizzes[$quizid]->allowresume) {
                    //    $resume_column = true;
                    }
                    $countquizscores++;
                }
            }
        }

        // cache the QuizPort "manage" capability
        $has_capability_manage = has_capability('mod/quizport:manage', $this->modulecontext);

        // cache the date format (strftimedaydatetime, strftimedatetimeshort, strftimerecentfull)
        $dateformat = get_string('strftimerecent');

        // start attempts table
        $table = new stdClass();
        $table->class = 'generaltable';
        $table->id = 'quizportquizzessummary';

        // add column headings, if required
        if ($countquizscores) {
            $table->head = array(
                get_string('quiz', 'quizport'),
                get_string('status', 'quizport'),
                get_string('duration', 'quizport'),
                get_string('lastaccess', 'quizport')
            );
        }
        $table->align = array('left', 'center', 'center', 'left');
        $table->size = array('', '', '', '');

        if ($score_column) {
            // insert score column
            array_splice($table->head, 1, 0, get_string('score', 'quizport'));
            array_splice($table->align, 1, 0, 'center');
            array_splice($table->size, 1, 0, '');
        }
        if ($has_capability_manage) {
            // prepend edit column
            if ($countquizscores) {
                array_unshift($table->head, $this->notext);
            }
            array_unshift($table->align, 'center');
            array_unshift($table->size, '');
        }
        if ($resume_column) {
            // append resume column
            $table->head[] = $this->notext;
            $table->align[] = 'center';
            $table->size[] = '';
        }

        // print rows of quizzes and their scores
        $quizlinktitle = get_string('startquizattempt', 'quizport');
        foreach ($showquizids as $quizid) {

            // shortcuts to quiz and quizscore
            $quiz = &$this->quizzes[$quizid];
            if (isset($quiz->quizscore)) {
                $quizscore = &$quiz->quizscore;
            } else {
                $quizscore = false;
            }

            // start the table row for this quiz
            $row = array();

            // edit icons
            if ($has_capability_manage) {
                $row[] = $this->print_commands(
                    // $types, $quizportscriptname, $id, $params, $popup, $return
                    array('update', 'delete'), 'editquiz.php', 'quizid',
                    array('quizid'=>$quizid, 'unumber'=>0),
                    false, true
                );
            }

            // quiz name
            $params = array(
                'quizid'=>$quizid, 'qnumber'=>-1, 'quizattemptid'=>0, 'quizscoreid'=>0
            );
            if (in_array($quizid, $linkquizids)) {
                $row[] = '<a href="'.$this->format_url('view.php', '', $params).'" title="'.$quizlinktitle.'">'.format_string($quiz->name).'</a>';
            } else {
                $row[] = format_string($quiz->name);
            }

            // add quiz score columns, if required
            if ($countquizscores) {

                // score
                if ($score_column) {
                    if ($this->quizzes[$quizid]->scorelimit && $quizscore) {
                        $row[] = $quizscore->score.'%';
                    } else {
                        $row[] = $this->notext;
                        //$row[] = $this->nonumber;
                    }
                }

                // status, duration, timemodified of quiz score
                if ($quizscore) {
                    array_push($row, quizport_format_status($quizscore->status), quizport_format_time($quizscore->duration), userdate($quizscore->timemodified, $dateformat));
                } else {
                    array_push($row, $this->notext, $this->notext, $this->notext);
                }

                //resume button
                if ($resume_column) {
                    if ($quiz->allowresume && $quizscore && $quizscore->status==QUIZPORT_STATUS_INPROGRESS) {
                        $row[] = 'resume';
                    } else {
                        $row[] = $this->notext;
                    }
                }
            }

            // append this row to the table
            $table->data[] = $row;
        }

        if (empty($table->data)) {
            $message = get_string('noquizzesforyou', 'quizport');
        } else {
            if (count($linkquizids)==1) {
                $message = $this->whatnext('clicklinktocontinue');
            } else {
                $message = $this->whatnext('');
            }
            $message .= $this->print_table($table, true);
        }

        print_box($message, 'generalbox', 'centeredboxtable');
        $this->print_js_reloadcoursepage();
    }
}
?>