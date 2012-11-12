<?php // $Id$

class mod_quizport_editcolumnlists extends mod_quizport {
    var $pagehastabs = false;
    var $pagehascolumns = false;
    var $pagehasheader = false;
    var $pagehasfooter = false;

    function print_heading() {
        switch ($this->action) {
            case 'delete':
                $columnlists = $this->get_columnlists($this->columnlisttype);
                if (array_key_exists($this->columnlistid, $columnlists)) {
                    $name = $columnlists[$this->columnlistid];
                } else {
                    $name = '';
                }
                $heading = get_string('deletecolumnlist'.$this->columnlisttype, 'quizport', $name);
                break;
            case 'deleteall':
                $heading = get_string('deleteallcolumnlists'.$this->columnlisttype, 'quizport');
                break;
            default:
                $heading = get_string('editcolumnlists'.$this->columnlisttype, 'quizport');
        }
        print_heading($heading);
    }

    function print_content() {
        global $mform;

        // initizialize data in form
        $defaults = array('columnlistid'=>$this->columnlistid);
        $mform->data_preprocessing($defaults);
        $mform->set_data($defaults);
        unset($defaults);

        // display the form
        $mform->display();
    }

    function print_js() {
        if ($this->columnlisttype) {
            print '<script type="text/javascript">'."\n";
            print '//<![CDATA['."\n";
            print '    if (window.opener) {'."\n";
            print '        var obj = opener.document.getElementById("quizport_columnlists");'."\n";
            print '        if (obj) {'."\n";
            print '            obj.innerHTML = "'.$this->format_columnlists($this->columnlisttype, true).'";'."\n";
            print '        }'."\n";
            print '        window.close();'."\n";
            print '    }'."\n";
            print '//]]>'."\n";
            print '</script>'."\n";
        }
    }
}
?>