<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

// get the flexible table class
require_once($CFG->dirroot.'/mod/quizport/tablelib.php');

// get formatting functions
require_once($CFG->dirroot.'/mod/quizport/lib.forms.php');

class mod_quizport_editunits extends mod_quizport {
    var $pagehastabs = false;

    // always show these columns
    var $showcolumns = array(
        'section','editunit','setasdefault','selectunit'
    );

    // never show these columns
    var $hidecolumns = array(
        'id','parenttype','parentid'
    );

    // if present, these columns should always appear on the left of the table
    var $leftcolumns = array(
        'section','editunit','name','quizzes','setasdefault','selectunit'
    );

    // if present, these columns should always appear on the right of the table
    var $rightcolumns = array(
    );

    // these columns will have class="textcolumn" (usually left aligned)
    // other columns will have class="nontextcolumn" (usually centered)
    var $textcolumns = array(
        'name','selectunit'
    );

    var $pagehascolumns = false;
    var $columnlisttype = 'unit';

    function print_header() {
        global $CFG, $THEME;

        $buttons = '&nbsp;';
        $streditunts = get_string('editunits', 'quizport');
        $strmodulenameplural = get_string('modulenameplural', 'quizport');
        $title = format_string($this->courserecord->shortname).': '.$strmodulenameplural.': '.$streditunts;

        $navigation = build_navigation(
            array(
                array('name' => $strmodulenameplural, 'link' => $CFG->wwwroot.'/mod/quizport/index.php?id='.$this->courserecord->id, 'type' => 'activity'),
                array('name' => $streditunts, 'link' => '', 'type' => 'activity')
            )
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

        print_header($title, format_string($this->courserecord->fullname), $navigation, '', $meta, true, $buttons, navmenu($this->courserecord, $this->modulerecord), false, $bodytags);
    }

    function print_heading() {
        // no heading required
    }

    function print_content() {
        print $this->format_columnlists('unit');
        $this->print_form_start('editunits.php', array('id' => $this->courserecord->id));
        if ($this->get_units()) {
            $this->display_units_table();
        } else {
            print get_string('nounits', 'quizport');
        }
        $this->display_radio_actions();
        $this->print_form_end();
    }

    function display_units_table() {

        switch ($this->columnlistid) {

            case 'all':
                $columns = array(
                    'editunit','name','quizzes','setasdefault','selectunit',
                    'entrycm','entrygrade','entrypage','entrytext','entryoptions',
                    'exitpage','exittext','exitoptions','exitcm','exitgrade',
                    'showpopup','popupoptions',
                    'timeopen','timeclose','timelimit','delay1','delay2',
                    'password','subnet','allowresume','allowfreeaccess','attemptlimit',
                    'attemptgrademethod','grademethod','gradeignore','gradelimit','gradeweighting'
                );
                break;

            case 'default':
                $columns = array(
                    'editunit','name','quizzes','setasdefault','selectunit',
                    'entrypage','exitpage','timeopen','timeclose','grademethod','gradelimit'
                );
                break;

            case 'general':
                $columns = array(
                    'editunit','name','quizzes','setasdefault','selectunit',
                    'entrycm','entrygrade','entrypage','entrytext','entryoptions',
                    'exitpage','exittext','exitoptions','exitcm','exitgrade'
                );
                break;

            case 'display':
                $columns = array(
                    'editunit','name','quizzes','setasdefault','selectunit',
                    'entrycm','entrygrade','entrypage','entrytext','entryoptions',
                    'exitpage','exittext','exitoptions','exitcm','exitgrade',
                    'showpopup','popupoptions'
                );
                break;

            case 'accesscontrol':
                $columns = array(
                    'editunit','name','quizzes','setasdefault','selectunit',
                    'timeopen','timeclose','timelimit','delay1','delay2',
                    'password','subnet','allowresume','allowfreeaccess','attemptlimit'
                );
                break;

            case 'assessment':
                $columns = array(
                    'editunit','name','quizzes','setasdefault','selectunit',
                    'attemptgrademethod','grademethod','gradeignore','gradelimit','gradeweighting'
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
        $table = new flexible_table(QUIZPORT_PAGEID); // mod-quizport-editunits

        $table->define_columns($columns);
        $table->define_headers($headers);

        $this->set_textcolumn_class($table, $columns);

        $table->collapsible(true);

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'quizport-multi-item-edit-table');
        $table->set_attribute('class', 'generaltable generalbox');

        $table->setup();

        // add row showing current default values
        $table->add_data($this->table_row_default($columns));

        // add row of checkboxes for column selection
        $table->add_data($this->table_row_select($columns));

        // add reference from each quizport to its unit
        foreach (array_keys($this->units) as $id) {
            $quizportid = $this->units[$id]->parentid;
            $this->quizports[$quizportid]->unit = &$this->units[$id];
        }

        // loop through quizports (because they are ordered according to course section)
        // making sure to transfer printable information across to the unit objects
        foreach (array_keys($this->quizports) as $id) {
            $quizport = &$this->quizports[$id];
            $unit = &$this->quizports[$id]->unit;
            $unit->name = format_string($quizport->name);
            $unit->section = $quizport->section;
            $unit->coursemodule = $quizport->coursemodule;
            $table->add_data($this->table_row($unit, $columns));
        }

        print "\n";
        $table->print_html();
        print "\n";
    }
    function table_headers(&$columns) {
        $headers = array();
        foreach ($columns as $column) {
            switch ($column) {
                case 'section':
                    $header = get_string('section');
                    break;
                case 'name':
                    $header = get_string('name');
                    break;
                case 'editunit':
                    $header = get_string('edit');
                    break;
                case 'setasdefault':
                    $header = get_string('default');
                    break;
                case 'selectunit':
                    $header = get_string('select');
                    break;
                case 'showpopup':
                    $header = get_string('display', 'resource');
                    break;
                case 'password':
                    $header = get_string('requirepassword', 'quiz');
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
        $unit = false;
        foreach ($columns as $i=>$column) {
            if ($column=='setasdefault') {
                $row[] = '<input type="radio" name="setasdefault" id="id_setasdefault" value="0" checked />';
            } else if (in_array($column, $this->leftcolumns)) {
                $row[] = '&nbsp;';
            } else {
                $value = get_user_preferences('quizport_unit_'.$column, '');
                $row[] = '<span class="defaultvalue">'.$this->table_cell($unit, $column, $value).'</span>';
            }
        }
        return $row;
    }
    function table_row_select(&$columns) {
        $onclick = ""
            ."var obj=document.getElementsByTagName('input');"
            ."if(obj){"
                ."var i=this.name.indexOf('[');"
                ."if(i<0){"
                    ."i=this.name.length;"
                ."}"
                ."var target=new RegExp('^'+this.name.substring(0,i)+'\\\\[\\\\d+\\\\]$');"
                ."for(var i=0;i<obj.length;i++){"
                    ."if(obj[i].type && obj[i].type=='checkbox'){"
                        ."if(obj[i].name && obj[i].name.match(target)){"
                            ."obj[i].checked=this.checked;"
                        ."}"
                    ."}"
                ."}"
            ."}"
            ."return true;"
        ;
        $row = array();
        foreach ($columns as $i=>$column) {
            if ($column=='selectunit') {
                $row[] = ''
                    .$this->print_checkbox('selectunit[0]', 1, false, '', '', $onclick, true)
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

    function table_row(&$unit, &$columns) {
        $row = array();
        foreach ($columns as $column) {
            $row[] = $this->table_cell($unit, $column);
        }
        return $row;
    }

    function table_cell(&$unit, &$column, $value=null) {
        global $DB;

        if (is_null($value) && isset($unit->$column)) {
            $value = $unit->$column;
        }

        $cell = '';
        switch ($column) {

            case 'editunit':
                $cell = $this->format_commands_unit($unit);
                break;

            case 'name':
                $params = array('unitid'=>$unit->id);
                $cell = '<a href="'.$this->format_url('view.php', '', $params).'" title="'.get_string('startunitattempt', 'quizport').'">'.format_string($unit->name).'</a>';
                break;

            // quizzes
            case 'quizzes':
                if ($unit && ($count = $DB->count_records_select('quizport_quizzes', 'unitid='.$unit->id))) {
                    $cell = $count;
                }
                break;

            case 'setasdefault':
                $cell = '<input type="radio" name="setasdefault" id="id_setasdefault" value="'.$unit->id.'" />';
                break;

            case 'selectunit':
                $cell = '<input type="checkbox" name="selectunit['.$unit->id.']" value="1" />';
                break;

            case 'popupoptions':
                if ($value) {
                    $cell = strtr($value, array(','=>', ', 'MOODLE'=>''));
                }
                break;

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

            case 'allowresume':
                if (empty($value)) {
                    $cell = quizport_format_allowresume(QUIZPORT_ALLOWRESUME_NO);
                } else {
                    $cell = quizport_format_allowresume($value);
                }
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
                        $cell = "$i ".moodle_strtolower(get_string('attempts', 'quiz'));
                };
                break;

            case 'attemptgrademethod':
                $cell = quizport_format_grademethod('unitattempt', $value);
                break;

            case 'grademethod':
                $cell = quizport_format_grademethod('unit', $value);
                break;

            case 'gradelimit':
            case 'entrygrade':
            case 'exitgrade':
                if ($value) {
                    $cell = $value.'%';
                }
                break;

            case 'gradeweighting':
                if ($value==0) {
                    $cell = get_string('weightingnone', 'quizport');
                } else {
                    $cell = $value.'%';
                }
                break;

            case 'entrycm':
            case 'exitcm':
                if ($value<=0) {
                    $cell = quizport_format_cm($value, substr($column, 0, -2));
                } else {
                    $modinfo = unserialize($this->courserecord->modinfo);
                    if ($modinfo && isset($modinfo[$value])) {
                        $cell = format_string(urldecode($modinfo[$value]->name));
                    }
                }
                break;

            // yes/no columns
            case 'entrypage':
            case 'exitpage':
            case 'showpopup':
            case 'gradeignore':
                if (empty($value)) {
                    $cell = get_string('no');
                } else {
                    $cell = get_string('yes');
                }
                break;

            // page text
            case 'entrytext':
            case 'exittext':
                if ($value) {
                    $search = '/^((?:'.'[\xc0-\xdf][\x80-\xbf]'.'|'.'[\xe0-\xef][\x80-\xbf]{2}'.'|'.'[\xf0-\xff][\x80-\xbf]{3}'.'|'.'[\x00-\xff]'.'){0,15}).*$/s';
                    $cell = preg_replace($search, '\\1', strip_tags(filter_text($value)), 1).' ...';
                }
                break;

            // page options
            case 'entryoptions':
            case 'exitoptions':
                if ($value) {
                    static $page_options;
                    if (empty($page_options)) {
                        $page_options = get_page_options();
                    }
                    // extract page type ("exit" or "entry")
                    $type = substr($column, 0, -7);
                    $options = array();
                    foreach ($page_options[$type] as $name=>$mask) {
                        if ($value & $mask) {
                            switch ($name) {
                                case 'unitgrade':
                                    $options[] = get_string($name, 'quizport');
                                    break;
                                case 'unitattempt':
                                    $options[] = get_string($name.'grade', 'quizport');
                                    break;
                                case 'title':
                                    $options[] = get_string('entry_title', 'quizport');
                                    break;
                                default:
                                    $options[] = get_string($type.'_'.$name, 'quizport');
                            }
                        }
                    }
                    if ($unit) {
                        $id = ' id="'.$type.'pageoptions_'.$unit->id.'"';
                    } else {
                        $id = '';
                    }
                    $cell .= '<div class="pageoptions"'.$id.'>'.implode(', ', $options).'</div>';
                }
                break;

            case 'allowfreeaccess':
                if (empty($value)) {
                    $cell = get_string('no');
                } else {
                    $cell = get_string('yes').': ';
                    if ($value>0) {
                        $cell .= get_string('grade').' >= '.$value.'%';
                    } else {
                        $cell .= get_string('attempts', 'quiz').' >= '.abs($value);
                    }
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
            'applydefaults' => get_string('applydefaults', 'quizport').$this->quizport_radio_defaultvalues(),
        );
        $default = get_user_preferences('quizport_editunits_'.$name, 'applydefaults');
        $checked = optional_param($name, $default, PARAM_ALPHA);
        print ''
            .'<div id="quizportactions">'
                .'<p class="actions">'.get_string('actions', 'quizport').'</p>'
                .$this->choose_from_radio($options, $name, $checked, true)
                .'<input class="submitbutton" type="submit" name="go" value="'.get_string('go').'" />'
            .'</div>'
        ;
    }
    function quizport_radio_defaultvalues() {
        $name = 'applydefaults';
        $options = array(
            'selectedunits' => get_string('selectedunits','quizport'),
            'filteredunits' => get_string('filteredunits','quizport').$this->quizport_unitfilters(),
        );
        $default = get_user_preferences('quizport_editunits_'.$name, 'selectedunits');
        $checked = optional_param($name, $default, PARAM_ALPHA);
        return '<div class="'.$name.'">'.$this->choose_from_radio($options, $name, $checked, true).'</div>';
    }
    function quizport_unitfilters() {
        $str = '';
        $size = 16; // text field size
        $filters = array(
            'course', 'unitname'
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
            $str .= '<span class="quizportunitfilter">';
            $name = $filter.'filter';
            if ($filter=='course') {
                $str .= '<div class="quizportunitfilterlabel">'.get_string($filter).':</div> '.$this->quizport_menu_mycourses($name);
            } else {
                $default = get_user_preferences('quizport_editunits_'.$name.'type', 0);
                $type = optional_param($name.'type', $default, PARAM_INT);

                $default = get_user_preferences('quizport_editunits_'.$name.'value', '');
                $value = optional_param($name.'value', $default, PARAM_RAW);

                $alt = get_string($filter, 'quizport');
                $str .= ''
                    .'<div class="quizportunitfilterlabel">'.$alt.':</div> '
                    .choose_from_menu($filtertypes, $name.'type', $type, '', '', 0, true)
                    .print_textfield ($name.'value', $value, $alt, $size, '', true)
                ;
            }
            $str .= '</span>';
        }
        return '<div class="quizportunitfilters">'.$str.'</div>';
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
                $options[$mycourse->id] = format_string($mycourse->shortname);
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