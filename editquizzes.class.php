<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

// get the flexible table class
require_once($CFG->dirroot.'/mod/quizport/tablelib.php');

// get formatting functions
require_once($CFG->dirroot.'/mod/quizport/lib.forms.php');

class mod_quizport_editquizzes extends mod_quizport {
    // always show these columns
    var $showcolumns = array(
        'sortorder','editquiz','setasdefault','selectquiz'
    );

    // never show these columns
    var $hidecolumns = array(
        'id','unitid','studentfeedbackurl'
    );

    // if present, these columns should always appear on the left of the table
    var $leftcolumns = array(
        'sortorder','editquiz','name','sourcefile','sourcetype','setasdefault','selectquiz'
    );

    // if present, these columns should always appear on the right of the table
    var $rightcolumns = array(
        'preconditions','postconditions'
    );

    // these columns will have class="textcolumn" (usually left aligned)
    // other columns will have class="nontextcolumn" (usually centered)
    var $textcolumns = array(
        'name','selectquiz','sourcefile','sourcetype'
    );

    var $forcetab = 'edit';
    var $pagehascolumns = false;
    var $pagehasreporttab = true;
    var $columnlisttype = 'quiz';

    function print_heading() {
        // no heading required
    }

    function print_content() {
        print $this->format_columnlists('quiz');
        $this->print_form_start('editquizzes.php', array('id' => $this->modulerecord->id));
        if ($this->get_quizzes()) {
            $this->display_quiz_table();
        } else {
            print_box(get_string('noquizzesinunit', 'quizport'), 'generalbox', 'centeredboxtable');
        }
        $this->display_radio_actions();
        $this->print_form_end();
    }

    function display_quiz_table() {

        switch ($this->columnlistid) {

            case 'all':
                $columns = array(
                    'sortorder','editquiz','name','sourcefile','sourcetype','setasdefault','selectquiz',
                    'sourcelocation','configlocation','configfile',
                    'outputformat','navigation','title','stopbutton','stoptext',
                    'usefilters','useglossary','usemediafilter',
                    'studentfeedback', // 'studentfeedbackurl',
                    'timeopen','timeclose','timelimit',
                    'delay1','delay2','delay3',
                    'password','subnet',
                    'allowresume','reviewoptions','attemptlimit',
                    'scoremethod','scoreignore','scorelimit','scoreweighting',
                    'clickreporting','discarddetails',
                    'preconditions','postconditions'
                );
                break;

            case 'default':
                $columns = array(
                    'sortorder','editquiz','name',
                    'setasdefault','selectquiz',
                    'timeopen','timeclose',
                    'scoremethod','scorelimit',
                    'preconditions','postconditions'
                );
                break;

            case 'general':
                $columns = array(
                    'sortorder','editquiz','name','setasdefault','selectquiz',
                    'sourcelocation','sourcefile','sourcetype','configlocation','configfile'
                );
                break;

            case 'display':
                $columns = array(
                    'sortorder','editquiz','name','setasdefault','selectquiz',
                    'outputformat','navigation','title','stopbutton','stoptext',
                    'usefilters','useglossary','usemediafilter','studentfeedback'
                );
                break;

            case 'accesscontrol':
                $columns = array(
                    'sortorder','editquiz','name','setasdefault','selectquiz',
                    'timeopen','timeclose','timelimit',
                    'delay1','delay2','delay3',
                    'attemptlimit','allowresume', // 'reviewoptions',
                    'password','subnet'
                );
                break;

            case 'assessment':
                $columns = array(
                    'sortorder','editquiz','name','setasdefault','selectquiz',
                    'scoremethod','scoreignore','scorelimit','scoreweighting',
                    'clickreporting' // , 'discarddetails',
                );
                break;

            case 'conditions':
                $columns = array(
                    'sortorder','editquiz','name','setasdefault','selectquiz',
                    'preconditions','postconditions'
                );
                break;

            default:
                $columnlist = get_user_preferences(
                    'quizport_'.$this->columnlisttype.'_columnlist_'.$this->columnlistid
                );
                $columns = explode(',', substr($columnlist, strpos($columnlist, ':')+1));
        }

        // add $showcolumns to $columns
        if (count($this->showcolumns)) {
            $columns = array_merge($this->showcolumns, $columns);
        }

        // only use $leftcolumns and $rightcolumns that are in $columns
        $filter = '/^'.implode('|', $columns).'$/';
        $this->leftcolumns = preg_grep($filter, $this->leftcolumns);
        $this->rightcolumns = preg_grep($filter, $this->rightcolumns);

        // remove $hidecolumns from $columns
        if (count($this->hidecolumns)) {
            $columns = preg_grep('/'.implode('|', $this->hidecolumns).'/', $columns, PREG_GREP_INVERT);
        }

        // remove $leftcolumns from $columns
        if (count($this->leftcolumns)) {
            $columns = preg_grep('/'.implode('|', $this->leftcolumns).'/', $columns, PREG_GREP_INVERT);
        }

        // remove $rightcolumns from $columns
        if (count($this->rightcolumns)) {
            $columns = preg_grep('/'.implode('|', $this->rightcolumns).'/', $columns, PREG_GREP_INVERT);
        }

        // add $leftcolumns and $rightcolumns to $columns
        $columns = array_merge($this->leftcolumns, $columns, $this->rightcolumns);

        // get headers for all $columns
        $headers = $this->table_headers($columns);

        // initialize table object
        $table = new flexible_table(QUIZPORT_PAGEID); // mod-quizport-editquizzes

        $table->define_columns($columns);
        $table->define_headers($headers);

        $this->set_textcolumn_class($table, $columns);

        $table->collapsible(true);
        $table->define_baseurl($this->format_url('editquizzes.php', 'coursemoduleid', array()));

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'quizport-multi-item-edit-table');
        $table->set_attribute('class', 'generaltable generalbox');

        $table->setup();

        // add row showing current default values
        $table->add_data($this->table_row_default($columns));

        // add row of checkboxes for column selection
        $table->add_data($this->table_row_select($columns));

        foreach ($this->quizzes as $quiz) {
            $table->add_data($this->table_row($quiz, $columns));
        }

        print "\n";
        $table->print_html();
        print "\n";
    }
    function table_headers(&$columns) {
        $headers = array();
        foreach ($columns as $column) {
            switch ($column) {
                case 'name':
                    $header = get_string('name');
                    break;
                case 'editquiz':
                    $header = get_string('edit');
                    break;
                case 'setasdefault':
                    $header = get_string('default');
                    break;
                case 'selectquiz':
                    $header = get_string('select');
                    break;
                case 'password':
                    $header = get_string('requirepassword', 'quiz');
                    break;
                case 'reviewoptions':
                    $header = get_string('reviewoptionsheading', 'quiz');
                    break;
                default:
                    $header = get_string($column, 'quizport');
            }
            $headers[] = $header;
        }
        return $headers;
    }

    function set_textcolumn_class(&$table, &$columns) {
        foreach ($columns as $column) {
            if (in_array($column, $this->textcolumns)) {
                $table->column_class($column, 'textcolumn');
            } else {
                $table->column_class($column, 'nontextcolumn');
            }
        }
    }

    function table_row_default(&$columns) {
        $quiz = false;
        foreach ($columns as $i=>$column) {
            if ($column=='setasdefault') {
                $row[] = '<input type="radio" name="setasdefault" value="0" checked="checked" />';
            } else if (in_array($column, $this->leftcolumns)) {
                $row[] = '&nbsp;';
            } else {
                $value = get_user_preferences('quizport_quiz_'.$column, '');
                $row[] = '<span class="defaultvalue">'.$this->table_cell($quiz, $column, $value).'</span>';
            }
        }
        return $row;
    }

    function table_row_select(&$columns) {
        static $include_js = true;
        $row = array();
        foreach ($columns as $i=>$column) {
            if ($column=='selectquiz') {
                $js = '';
                if ($include_js) {
                    $include_js = false;
                    $js = "\n"
                        .'<script type="text/javascript">'."\n"
                        .'//<![CDATA['."\n"
                        .'function quizport_select_row(checkbox) {'."\n"
                        ."    var obj = document.getElementsByTagName('input');\n"
                        .'    if (obj) {'."\n"
                        ."        var i = checkbox.name.indexOf('[');\n"
                        .'        if (i<0) {'."\n"
                        .'            i = checkbox.name.length;'."\n"
                        .'        }'."\n"
                        ."        var target = new RegExp('^'+checkbox.name.substring(0,i)+'\\\\[\\\\d+\\\\]$');\n"
                        .'        for(var i=0; i<obj.length; i++) {'."\n"
                        ."            if(obj[i].type && obj[i].type=='checkbox') {\n"
                        .'                if(obj[i].name && obj[i].name.match(target)) {'."\n"
                        .'                    obj[i].checked=checkbox.checked;'."\n"
                        .'                }'."\n"
                        .'            }'."\n"
                        .'        }'."\n"
                        .'    }'."\n"
                        .'    return true;'."\n"
                        .'}'."\n"
                        .'//]]>'."\n"
                        .'</script>'."\n"
                    ;
                }
                $onclick = 'return quizport_select_row(this)';
                $row[] = ''
                    .$js
                    .$this->print_checkbox('selectquiz[0]', 1, false, '', '', $onclick, true)
                    .$this->print_checkbox('selectcolumn[0]', 1, false, '', '', $onclick, true)
                ;
            } else if (in_array($column, $this->leftcolumns)) {
                $row[] = '&nbsp;';
            } else {
                $row[] = $this->print_checkbox('selectcolumn['.$i.']', $column, false, '', '', '', true);
            }
        }
        return $row;
    }

    function table_row(&$quiz, &$columns) {
        $row = array();
        foreach ($columns as $column) {
            $row[] = $this->table_cell($quiz, $column);
        }
        return $row;
    }

    function table_cell(&$quiz, &$column, $value=null) {

        if (is_null($value) && isset($quiz->$column)) {
            $value = $quiz->$column;
        }

        $cell = '';
        switch ($column) {

            case 'sortorder':
                $cell = print_textfield('sortorder['.$quiz->id.']', $value, get_string($column, 'quizport'), 2, '', true);
                break;

            case 'editquiz':
                $cell = $this->format_commands_quiz($quiz);
                break;

            case 'name':
                $params = array(
                    'coursemoduleid'=>$this->modulerecord->id, 'quizid'=>$quiz->id, 'unumber'=>-1, 'qnumber'=>-1, 'tab'=>'preview', 'mode'=>''
                );
                $cell = '<a href="'.$this->format_url('view.php', 'coursemoduleid', $params).'" title="'.get_string('previewquiznow', 'quizport').'">'.format_string($quiz->name).'</a>';
                break;

            case 'sourcelocation':
            case 'configlocation':
                if (empty($value)) {
                    $cell = quizport_format_location(QUIZPORT_LOCATION_COURSEFILES);
                } else {
                    $cell = quizport_format_location($value);
                }
                break;

            case 'setasdefault':
                $cell = '<input type="radio" name="setasdefault" value="'.$quiz->id.'" />';
                break;

            case 'selectquiz':
                $cell = '<input type="checkbox" name="selectquiz['.$quiz->id.']" value="1" />';
                break;

            case 'outputformat':
                if (empty($value)) {
                    $cell = get_string('outputformat_best', 'quizport');
                } else {
                    $cell = get_string('outputformat_'.$value, 'quizport');
                }
                break;

            case 'navigation':
                $cell = quizport_format_navigation($value);
                break;

            case 'title':
                $cell = quizport_format_title($value & QUIZPORT_TITLE_SOURCE);
                if ($value & QUIZPORT_TITLE_UNITNAME) {
                    $cell = get_string('unitname', 'quizport').': '.$cell;
                }
                if ($value & QUIZPORT_TITLE_SORTORDER) {
                    $cell .= ' ('.get_string('sortorder', 'quizport').')';
                }
                break;

            case 'stopbutton':
                $cell = quizport_format_stopbutton($value);
                break;

            case 'studentfeedback':
                $cell = quizport_format_studentfeedback($value);
                if ($value==QUIZPORT_FEEDBACK_WEBPAGE || $value==QUIZPORT_FEEDBACK_FORMMAIL) {
                    $cell .= ': '.$quiz->studentfeedbackurl;
                }
                break;

            // times
            case 'timeopen':
            case 'timeclose':
                if ($value) {
                    $cell = userdate($value, get_string('strftimedatetime'));
                }
                break;

            // timelimits and delays
            case 'timelimit':
            case 'delay1':
            case 'delay2':
                switch ($value) {
                    case QUIZPORT_TIMELIMIT_TEMPLATE:
                        $cell = get_string('timelimittemplate', 'quizport');
                        break;
                    default:
                        $cell = quizport_format_time($value);
                }
                break;

            case 'delay3':
                switch ($value) {
                    case QUIZPORT_DELAY3_TEMPLATE:
                        $cell = get_string('delay3template', 'quizport');
                        break;
                    case QUIZPORT_DELAY3_AFTEROK:
                        $cell = get_string('delay3afterok', 'quizport');
                        break;
                    case QUIZPORT_DELAY3_DISABLE:
                        $cell = get_string('delay3disable', 'quizport');
                        break;
                    default:
                        $cell = quizport_format_time($value);
                }
                break;

            case 'reviewoptions':
                $cell = quizport_format_reviewoptions($value);
                break;

            case 'attemptlimit':
                switch ($value) {
                    case 0:
                        $cell = get_string('attemptsunlimited', 'quiz');
                        break;
                    case 1:
                        $cell = '1 '.moodle_strtolower(get_string('attempt', 'quiz'));
                        break;
                    default:
                        $cell = "$value ".moodle_strtolower(get_string('attempts', 'quiz'));
                };
                break;

            case 'scoremethod':
                $cell = quizport_format_grademethod('quiz', $value);
                break;

            case 'scorelimit':
                if ($value) {
                    $cell = $value.'%';
                }
                break;

            case 'scoreweighting':
                if ($value < 0) {
                    $cell = get_string('weightingequal', 'quizport').' ('.abs($value).')';
                } else if ($value==0) {
                    $cell = get_string('weightingnone', 'quizport');
                } else {
                    $cell = $value.'%';
                }
                break;

            // yes/no columns
            case 'usefilters':
            case 'useglossary':
            case 'scoreignore':
            case 'clickreporting':
            case 'discarddetails':
                if (empty($value)) {
                    $cell = get_string('no');
                } else {
                    $cell = get_string('yes');
                }
                break;

            case 'allowresume':
                if (empty($value)) {
                    $cell = quizport_format_allowresume(QUIZPORT_ALLOWRESUME_NO);
                } else {
                    $cell = quizport_format_allowresume($value);
                }
                break;

            // conditions
            case 'preconditions':
            case 'postconditions':
                if ($column=='preconditions') {
                    $conditiontype = QUIZPORT_CONDITIONTYPE_PRE;
                } else {
                    $conditiontype = QUIZPORT_CONDITIONTYPE_POST;
                }
                if ($quiz) {
                    $cell = $this->format_conditions($quiz->id, $conditiontype, false, false, true, '&nbsp;');
                } else if ($value) {
                    $cell = $this->format_conditions($value, $conditiontype, false, false, false, '&nbsp;');
                }
                break;

            default:
                if (isset($value)) {
                    $cell = $value;
                }
        }
        if ($cell=='') {
            return '&nbsp;';
        } else {
            return $cell;
        }
    }

    function display_radio_actions() {
        $name = 'action';
        $options = array(
            'renumberquizzes' => get_string('reorderquizzes', 'quizport').$this->quizport_textfield_sortorderincrement(),
            'addquizzes' => get_string('addmorequizzes', 'quizport').$this->quizport_radio_addquizzes(),
            'movequizzes' => get_string('movequizzes', 'quizport').$this->quizport_radio_movequizzes(),
            'applydefaults' => get_string('applydefaults', 'quizport').$this->quizport_radio_defaultvalues(),
            'deletequizzes' => get_string('deletequizzes', 'quizport')
        );
        if (empty($this->quizzes)) {
            $default = 'addquizzes';
        } else {
            $default = get_user_preferences('quizport_editquizzes_'.$name, 'applydefaults');
        }
        $checked = optional_param($name, $default, PARAM_ALPHA);
        print ''
            .'<div id="quizportactions">'
                .'<p class="actions">'.get_string('actions', 'quizport').'</p>'
                .$this->choose_from_radio($options, $name, $checked, true)
                .'<input class="submitbutton" type="submit" name="go" value="'.get_string('go').'" />'
            .'</div>'
            .'<script type="text/javascript">'."\n"
            ."//<![CDATA[\n"
            ."function quizport_toggle_actions_display() {\n"
            ."    var div = document.getElementById('quizportactions');\n"
            ."    if (! div) {\n"
            ."        return true;\n"
            ."    }\n"
            ."    var inputs = div.getElementsByTagName('input');\n"
            ."    var labels = div.getElementsByTagName('label');\n"
            ."    if (! inputs || ! labels) {\n"
            ."        return true;\n"
            ."    }\n"
            ."    var i_max = inputs.length;\n"
            ."    for (var i=0; i<i_max; i++) {\n"
            ."        if (! inputs[i].type || inputs[i].type != 'radio') {\n"
            ."            continue;\n"
            ."        }\n"
            ."        if (! inputs[i].name || inputs[i].name != 'action') {\n"
            ."            continue;\n"
            ."        }\n"
            ."        if (! inputs[i].onclick) {\n"
            ."            inputs[i].onclick = quizport_toggle_actions_display;\n"
            ."        }\n"
            ."        var l_max = labels.length;\n"
            ."        for (var l=0; l<l_max; l++) {\n"
            ."            if (! labels[l].htmlFor || labels[l].htmlFor != inputs[i].id) {\n"
            ."                continue;\n"
            ."            }\n"
            ."            if (labels[l].childNodes.length==1 && labels[l].childNodes[0].nodeType==3) {\n"
            ."                var obj = labels[l].parentNode;\n" // FF
            ."            } else {\n"
            ."                var obj = labels[l];\n" // IE, Safari, Chrome
            ."            }\n"
            ."            var c_max = obj.childNodes.length;\n"
            ."            for (var c=0; c<c_max; c++) {\n"
            ."                var t = obj.childNodes[c].tagName;\n"
            ."                if (t && t.toUpperCase()=='DIV') {\n"
            ."                    if (inputs[i].checked) {\n"
            ."                        obj.childNodes[c].style.display = '';\n"
            ."                    } else {\n"
            ."                        obj.childNodes[c].style.display = 'none';\n"
            ."                    }\n"
            ."                }\n"
            ."            }\n"
            ."        }\n"
            ."    }\n"
            ."}\n"
            ."quizport_toggle_actions_display();\n"
            ."//]]>\n"
            ."</script>\n"
        ;
    }

    function quizport_textfield_sortorderincrement() {
        $name = 'sortorderincrement';
        $value = optional_param($name, get_user_preferences('quizport_'.$name, 1), PARAM_INT);
        return ''
            .'<div class="'.$name.'">'
            .get_string($name, 'quizport').': '
            .print_textfield($name, $value, get_string($name, 'quizport'), 2, '', true)
            .'</div>'
        ;
    }

    function quizport_radio_addquizzes() {
        return $this->quizport_radio_quizzes('addquizzes');
    }

    function quizport_radio_movequizzes() {
        return $this->quizport_radio_quizzes('movequizzes', true);
    }

    function quizport_menu_myquizports($name) {
        $options = array();
        if ($mycourses = $this->get_mycourses()) {
            if ($myquizports = $this->get_myquizports(0, $mycourses)) {
                $courseid = 0;
                $coursename = '';
                foreach ($myquizports as $myquizport) {
                    if ($myquizport->id==$this->quizport->id) {
                        continue; // skip current quizport
                    }
                    if ($courseid==$myquizport->course) {
                        // do nothing - same course as previous quizport
                    } else {
                        $courseid = $myquizport->course;
                        $coursename = format_string($mycourses[$courseid]->shortname);
                        $options[$coursename] = array();
                    }
                    $options[$coursename][$myquizport->id] = format_string($myquizport->name);
                }
            }
        }
        if (count($options)) {
            $default = get_user_preferences('quizport_editquizzes_'.$name, 0);
            $selected = optional_param($name, $default, PARAM_INT);
            return choose_from_menu_nested($options, $name, $selected, '', '', 0, true);
        } else {
            return '';
        }
    }

    function quizport_radio_quizzes($name, $show_myquizports=false) {
        $options = array ();
        if ($this->get_quizzes()) {
            $options['start'] = get_string('startofunit','quizport');
        }
        $options['end'] = get_string('endofunit','quizport');
        if ($this->get_quizzes()) {
            $options['after'] = get_string('afterquiz','quizport').': '.$this->quizport_menu_quizzes($name.'afterquizid');
        }
        if ($show_myquizports) {
            if ($str = $this->quizport_menu_myquizports($name.'quizportid')) {
                $options['myquizport'] = get_string('modulename', 'quizport').': '.$str;
            }
        }
        $default = get_user_preferences('quizport_editquizzes_'.$name, 'end');
        $checked = optional_param($name, $default, PARAM_ALPHA);
        return '<div class="'.$name.'">'.$this->choose_from_radio($options, $name, $checked, true).'</div>';
    }

    function quizport_menu_quizzes($name) {
        $options = array();
        foreach ($this->quizzes as $quiz) {
            $options[$quiz->id] = format_string($quiz->name);
        }
        $default = get_user_preferences('quizport_editquizzes_'.$name, 0);
        $selected = optional_param($name, $default, PARAM_INT);
        return choose_from_menu($options, $name, $selected, '', '', 0, true);
    }

    function quizport_radio_defaultvalues() {
        $name = 'applydefaults';
        $options = array(
            'selectedquizzes' => get_string('selectedquizzes','quizport'),
            'filteredquizzes' => get_string('filteredquizzes','quizport').$this->quizport_quizfilters(),
        );
        $default = get_user_preferences('quizport_editquizzes_'.$name, 'selectedquizzes');
        $checked = optional_param($name, $default, PARAM_ALPHA);
        return '<div class="'.$name.'">'.$this->choose_from_radio($options, $name, $checked, true).'</div>';
    }

    function quizport_quizfilters() {
        $str = '';
        $size = 16; // text field size
        $filters = array(
            'course', 'quizname', 'quiztype', 'filename'
        );
        $filtertypes = array(
            0 => get_string('contains', 'filters'),
            1 => get_string('doesnotcontain','filters'),
            2 => get_string('isequalto','filters'),
            3 => get_string('startswith','filters'),
            4 => get_string('endswith','filters'),
            // 5 => get_string('isempty','filters')
        );
        foreach ($filters as $filter) {
            $str .= '<span class="quizportquizfilter">';
            $name = $filter.'filter';
            if ($filter=='course') {
                $str .= '<div class="quizportquizfilterlabel">'.get_string($filter).':</div> '.$this->quizport_menu_mycourses($name);
            } else {
                $default = get_user_preferences('quizport_editquizzes_'.$name.'type', 0);
                $type = optional_param($name.'type', $default, PARAM_INT);

                $default = get_user_preferences('quizport_editquizzes_'.$name.'value', '');
                $value = optional_param($name.'value', $default, PARAM_RAW);

                $alt = get_string($filter, 'quizport');
                $str .= ''
                    .'<div class="quizportquizfilterlabel">'.$alt.':</div> '
                    .choose_from_menu($filtertypes, $name.'type', $type, '', '', 0, true)
                    .print_textfield ($name.'value', $value, $alt, $size, '', true)
                ;
            }
            $str .= '</span>';
        }
        return '<div class="quizportquizfilters">'.$str.'</div>';
    }

    function get_filter($formfield, $dbfield) {
        global $DB;
        static $addslashes = array('\\'=>'\\\\',"'"=>"\\'");

        $type = strtr(optional_param($formfield.'type', '', PARAM_INT), $addslashes);
        $value = strtr(optional_param($formfield.'value', '', PARAM_RAW), $addslashes);

        if ($type != 5 && $value==='') {
            return '';
        }

        $ilike = $DB->sql_ilike();

        switch($type) {
            case 0: return "$dbfield $ilike '%$value%'"; // contains
            case 1: return "$dbfield NOT $ilike '%$value%'"; // does not contain
            case 2: return "$dbfield $ilike '$value'"; // equal to
            case 3: return "$dbfield $ilike '$value%'"; // starts with
            case 4: return "$dbfield $ilike '%$value'"; // ends with
            case 5: return "$dbfield = ''";  // is empty
            default: return ''; // invalid filter type !!
        }
    }

    function quizport_menu_mycourses($name) {
        if ($mycourses = $this->get_mycourses()) {
            $options = array();
            if (count($mycourses)>1) {
                $options[0] = get_string('all');
            }
            foreach ($mycourses as $mycourse) {
                $shortname = format_string($mycourse->shortname);
                if ($mycourse->id==SITEID) {
                    $shortname = get_string('frontpage', 'admin').': '.$shortname;
                }
                $options[$mycourse->id] = $shortname;
            }
        } else {
            $options = array(
                $this->courserecord->id => format_string($this->courserecord->shortname)
            );
        }
        $default = get_user_preferences('quizport_editquizzes_'.$name, $this->courserecord->id);
        $selected = optional_param($name, $default, PARAM_INT);
        return choose_from_menu($options, $name, $selected, '', '', 0, true);
    }
}
?>