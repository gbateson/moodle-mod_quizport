<?php //$Id: user_filter_forms.php,v 1.1.2.2 2007-11-13 09:02:12 skodak Exp $

require_once($CFG->dirroot.'/mod/quizport/lib.forms.php');

class user_add_filter_form extends moodleform {

    function definition() {
        $mform       =& $this->_form;
        $fields      = $this->_customdata['fields'];
        $extraparams = $this->_customdata['extraparams'];

        $mform->addElement('header', 'newfilterhdr', get_string('newfilter','filters'));

        foreach($fields as $ft) {
            $ft->setupForm($mform);
        }

        // in case we wasnt to track some page params
        if ($extraparams) {
            foreach ($extraparams as $key=>$value) {
                $mform->addElement('hidden', $key, $value);
            }
        }

        // Add button
        $mform->addElement('submit', 'addfilter', get_string('addfilter','filters'));

        // Don't use last advanced state
        $mform->setShowAdvanced(false);

        // on Moodle 1.7 and 1.8 the "Advanced" button doesn't show unless the Header has been closed
        // usually this is done by adding another header, but since this form has only one header
        // we add a dummy static object containing some javascript to hide itself
        $js = ''
            .'<script>'."\n"
            .'//<![CDATA['."\n"
            ."var fieldsets = document.getElementsByTagName('fieldset');\n"
            ."if (fieldsets) {\n"
            ."    for (var i=0; i<fieldsets.length; i++) {\n"
            ."        if (fieldsets[i].className=='hidden') {\n"
            ."            fieldsets[i].style.display = 'none';\n"
            ."        }\n"
            ."    }\n"
            ."}\n"
            ."fieldsets = null;\n"
            .'//]]>'."\n"
            .'</script>'."\n"
        ;
        $mform->addElement('static', 'staticjs', $js);
        $mform->closeHeaderBefore('staticjs');
    }

    function display() {
        if (function_exists('print_formslib_js_and_css')) {
            print_formslib_js_and_css($this->_form);
        }
        parent::display();
    }
}

class user_active_filter_form extends moodleform {

    function definition() {
        global $SESSION; // this is very hacky :-(

        $mform       =& $this->_form;
        $fields      = $this->_customdata['fields'];
        $extraparams = $this->_customdata['extraparams'];

        if (!empty($SESSION->user_filtering)) {
            // add controls for each active filter in the active filters group
            $mform->addElement('header', 'actfilterhdr', get_string('actfilterhdr','filters'));

            foreach ($SESSION->user_filtering as $fname=>$datas) {
                if (!array_key_exists($fname, $fields)) {
                    continue; // filter not used
                }
                $field = $fields[$fname];
                foreach($datas as $i=>$data) {
                    $description = $field->get_label($data);
                    $mform->addElement('checkbox', 'filter['.$fname.']['.$i.']', null, $description);
                }
            }

            if ($extraparams) {
                foreach ($extraparams as $key=>$value) {
                    $mform->addElement('hidden', $key, $value);
                }
            }

            $els = array();
            $els[] = &$mform->createElement('submit', 'removeselected', get_string('removeselected','filters'));
            $els[] = &$mform->createElement('submit', 'removeall', get_string('removeall','filters'));
            $mform->addElement('group', 'actfiltergrp', '', $els, ' ', false);
        }
    }
}
