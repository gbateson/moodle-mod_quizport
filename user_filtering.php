<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

if ($CFG->majorrelease>=1.9) {
    require_once($CFG->dirroot.'/user/filters/lib.php');
} else {
    require_once($CFG->dirroot.'/mod/quizport/legacy/user/filters/lib.php');
}

class quizport_user_filtering extends user_filtering {

    // constructor function
    function quizport_user_filtering($fieldnames=null, $baseurl=null, $extraparams=null) {
        parent::user_filtering($fieldnames, $baseurl, $extraparams);
    }

    // quizport version of standard function
    function get_field($fieldname, $advanced) {
        global $QUIZPORT, $USER;

        $default = get_user_preferences('quizport_'.$fieldname, 'users');
        $selected = optional_param($fieldname, $default, PARAM_ALPHANUM);

        switch ($fieldname) {
            // group / grouping
            case 'group':
                return new user_filter_group($fieldname, $advanced, $selected);
            // grade / score
            case 'unitgrade':
            case 'unitattemptgrade':
            case 'quizscore':
            case 'quizattemptscore':
                return new user_filter_grade($fieldname, $advanced, $selected);
            // status
            case 'status':
            case 'unitgradestatus':
            case 'unitattemptstatus':
            case 'quizscorestatus':
            case 'quizattemptstatus':
                return new user_filter_status($fieldname, $advanced, $selected);
            // duration
            case 'unitgradeduration':
            case 'unitattemptduration':
            case 'quizscoreduration':
            case 'quizattemptduration':
                return new user_filter_duration($fieldname, $advanced, $selected);
            // time modified
            case 'unitgradetimemodified':
            case 'unitattempttimemodified':
            case 'quizscoretimemodified':
            case 'quizattempttimemodified':
                return new user_filter_datetime($fieldname, $advanced, $selected);
            // other fields (e.g. from user record)
            default:
                return parent::get_field($fieldname, $advanced);
        }
    }

    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array sql string and $params
     */
    function get_sql_filter_attempts($extra='', $params=null) {
        global $CFG, $SESSION;

        $sqls = array();
        if ($extra) {
            $sqls[] = $extra;
        }
        if (is_null($params)) {
            $params = array();
        } else if (! is_array($params)) {
            $params = (array)$params;        }

        if (!empty($SESSION->user_filtering)) {
            foreach ($SESSION->user_filtering as $fname=>$datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fname];
                if (! method_exists($field, 'get_sql_filter_attempts')) {
                    continue; // field does not generate sql for attempts
                }
                foreach($datas as $i=>$data) {
                    $filter = $field->get_sql_filter_attempts($data);
                    if (is_array($filter)) {
                        list($s, $p) = $filter;
                        if ($s) {
                            $sqls[] = $s;
                            $params = $params + $p;
                        }
                    } else if ($filter) {
                        $sqls[] = $filter;
                    }
                }
            }
        }
        $filter = implode(' AND ', $sqls);

        if ($CFG->majorrelease<=1.9) {
            return $filter;
        } else {
            return array($filter, $params);
        }
    }
}

class user_filter_group extends user_filter_select {
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default option
     */
    function user_filter_group($filtername, $advanced, $default=null) {
        global $QUIZPORT;

        $label = '';
        $options = array();

        $strgroup = get_string('group', 'group');
        $strgrouping = get_string('grouping', 'group');

        if ($groupings = $QUIZPORT->get_all_groupings()) {
            $label = $strgrouping;
            $has_groupings = true;
        } else {
            $has_groupings = false;
            $groupings = array();
        }

        if ($groups = $QUIZPORT->get_all_groups()) {
            if ($label) {
                $label .= ' / ';
            }
            $label .= $strgroup;
            $has_groups = true;
        } else {
            $has_groups = false;
            $groups = array();
        }

        foreach ($groupings as $gid => $grouping) {
            if ($has_groups) {
                $prefix = $strgrouping.': ';
            } else {
                $prefix = '';
            }
            if ($members = groups_get_grouping_members($gid)) {
                $options["grouping$gid"] = $prefix.format_string($grouping->name).' ('.count($members).')';
            }
        }

        foreach ($groups as $gid => $group) {
            if ($members = groups_get_members($gid)) {
                if ($has_groupings) {
                    $prefix = $strgroup.': ';
                } else {
                    $prefix = '';
                }
                if (isset($group->name)) {
                    $groupname = $group->name;
                } else { // Moodle 1.8
                    $groupname = groups_get_group_name($gid);
                }
                $options["group$gid"] = $prefix.format_string($groupname).' ('.count($members).')';
            }
        }

        parent::user_filter_select($filtername, $label, $advanced, '', $options, $default);
    }

    function setupForm(&$mform) {
        global $QUIZPORT;

        if ($count = count($this->_options)) {
            if ($count>1 || $QUIZPORT->can_accessallgroups()) {
                parent::setupForm($mform);
            } else {
                reset($this->_options);
                list($value, $text) = each($this->_options);
                $mform->addElement('static', '', get_string('group').' :', $text);
                $mform->addElement('hidden', $this->_name, $value);
            }
        }
    }

    function get_sql_filter($data) {
        global $CFG, $QUIZPORT;
        $filter = '';

        if (($value = $data['value']) && ($operator = $data['operator'])) {

            $userids = '';
            if (substr($value, 0, 5)=='group') {
                if (substr($value, 5, 3)=='ing') {
                    $g = groups_get_all_groupings($QUIZPORT->courserecord->id);
                    $gid = intval(substr($value, 8));
                    if ($g && array_key_exists($gid, $g) && ($members = groups_get_grouping_members($gid))) {
                        $userids = implode(',', array_keys($members));
                    }
                } else {
                    $g = $QUIZPORT->get_all_groups();
                    $gid = intval(substr($value, 5));
                    if ($g && array_key_exists($gid, $g) && ($members = groups_get_members($gid))) {
                        $userids = implode(',', array_keys($members));
                    }
                }
            }
            if ($userids) {
                switch($operator) {
                    case 1: $filter = "id IN ($userids)"; break;
                    case 2: $filter = "id NOT IN ($userids)"; break;
                }
            }
        }
        if ($CFG->majorrelease<=1.9) {
            return $filter;
        } else {
            return array($filter, array());
        }
    }
}

class user_filter_status extends user_filter_select {
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default option
     */
    function user_filter_status($name, $advanced, $default=null) {
        parent::user_filter_select($name, get_string('attemptstatus', 'quizport'), $advanced, '', quizport_format_status(), $default);
    }

    function get_sql_filter($data) {
        // this field type doesn't affect the selection of users
        global $CFG;
        $filter = '';
        if ($CFG->majorrelease<=1.9) {
            return $filter;
        } else {
            return array($filter, array());
        }
    }

    function get_sql_filter_attempts($data, $extrasql='') {
        global $CFG;
        $filter = '';
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            switch($operator) {
                case 1: $filter = "status=$value"; break;
                case 2: $filter = "status<>$value"; break;
            }
        }
        if ($CFG->majorrelease<=1.9) {
            return $filter;
        } else {
            return array($filter, array());
        }
    }
}