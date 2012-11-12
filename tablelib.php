<?php // $Id$

// block direct access to this script
if (empty($CFG)) {
    die;
}

// get parent class - Moodle's standard flexible table
if ($CFG->majorrelease<=1.4) {
    require_once($CFG->legacylibdir.'/tablelib.php');
} else {
    require_once($CFG->libdir.'/tablelib.php');
}

class quizport_flexible_table extends flexible_table {
    var $row_attributes = array();
    var $caption = '';

    // constructor function
    function quizport_flexible_table($uniqueid) {
        global $CFG;
        if ($CFG->majorrelease<=1.4) {
            $this->pixpath = $CFG->legacypixpath;
        } else {
            $this->pixpath = $CFG->pixpath;
        }
        parent::flexible_table($uniqueid);
    }

    // new $attributes parameter to store row attributes (id, class, etc)
    function add_data($row, $attributes=array()) {
        if(! $this->setup) {
            return false;
        }
        $this->data[] = $row;
        $this->row_attributes[] = $attributes;
    }

    // new method to set table caption
    function set_caption($caption) {
        $this->caption = $caption;
    }

    // original print_html is divided into sub-functions to improve readability
    //     print_html_initialbars
    //     print_html_headers
    //     print_html_data (contains significant modifications to allow rowspan/colspan >1)
    function print_html() {
        global $CFG;

        if (!$this->setup) {
            return false;
        }

        $this->print_html_initialbars();

        // Paging bar
        if ($this->use_pages) {
            print_paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl, $this->request[TABLE_VAR_PAGE]);
        }

        if (empty($this->data)) {
            print_heading(get_string('nothingtodisplay'));
            return true;
        }

        // Start of main data table

        print '<table'.$this->make_attributes_string($this->attributes).'><tbody>'."\n";

        // print caption
        if ($this->caption) {
            print '<caption>'.$this->caption.'</caption>'."\n";
        }

        $this->print_html_headers();

        $this->print_html_data();

        print '</tbody></table>'."\n";

        // Paging bar
        if ($this->use_pages) {
            print_paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl, $this->request[TABLE_VAR_PAGE]);
        }
    }

    function make_attributes_string(&$attributes) {
        // the standard version of this function  adds unnecessary trailing space
        // and has unnecessary check for empty($attributes)
        $string = '';
        foreach ($attributes as $name => $value) {
            $string .= (' '.$name.'="'.$value.'"');
        }
        return $string;
    }

    function print_html_initialbars() {

        if ($this->use_initials && isset($this->columns['fullname'])) {

            $strall = get_string('all');
            $alpha  = explode(',', get_string('alphabet'));

            // Bar of first initials

            print '<div class="initialbar firstinitial">'.get_string('firstname').' : ';
            if (!empty($this->sess->i_first)) {
                print '<a href="'.$this->baseurl.$this->request[TABLE_VAR_IFIRST].'=">'.$strall.'</a>';
            } else {
                print '<strong>'.$strall.'</strong>';
            }
            foreach ($alpha as $letter) {
                if ($letter == $this->sess->i_first) {
                    print ' <strong>'.$letter.'</strong>';
                } else {
                    print ' <a href="'.$this->baseurl.$this->request[TABLE_VAR_IFIRST].'='.$letter.'">'.$letter.'</a>';
                }
            }
            print '</div>';

            // Bar of last initials

            print '<div class="initialbar lastinitial">'.get_string('lastname').' : ';
            if (!empty($this->sess->i_last)) {
                print '<a href="'.$this->baseurl.$this->request[TABLE_VAR_ILAST].'=">'.$strall.'</a>';
            } else {
                print '<strong>'.$strall.'</strong>';
            }
            foreach ($alpha as $letter) {
                if ($letter == $this->sess->i_last) {
                    print ' <strong>'.$letter.'</strong>';
                } else {
                    print ' <a href="'.$this->baseurl.$this->request[TABLE_VAR_ILAST].'='.$letter.'">'.$letter.'</a>';
                }
            }
            print '</div>';
        }
    } // end function print_html_initialbars

    function print_html_headers() {
        global $CFG;

        if (empty($this->headers)) {
            // headers not defined, so don't print any thing
            return true;
        }

        print '<tr>';
        foreach ($this->columns as $column => $index) {
            $icon_hide = '';
            $icon_sort = '';

            if ($this->is_collapsible) {
                if (!empty($this->sess->collapse[$column])) {
                    // some headers contain < br/> tags, do not include in title
                    $icon_hide = ' <a href="'.$this->baseurl.$this->request[TABLE_VAR_SHOW].'='.$column.'"><img src="'.$this->pixpath.'/t/switch_plus.gif" title="'.get_string('show').' '.strip_tags($this->headers[$index]).'" alt="'.get_string('show').'" /></a>';
                }
                else if ($this->headers[$index] !== null) {
                    // some headers contain < br/> tags, do not include in title
                    $icon_hide = ' <a href="'.$this->baseurl.$this->request[TABLE_VAR_HIDE].'='.$column.'"><img src="'.$this->pixpath.'/t/switch_minus.gif" title="'.get_string('hide').' '.strip_tags($this->headers[$index]).'" alt="'.get_string('hide').'" /></a>';
                }
            }

            $primary_sort_column = '';
            $primary_sort_order  = '';
            if (reset($this->sess->sortby)) {
                $primary_sort_column = key($this->sess->sortby);
                $primary_sort_order  = current($this->sess->sortby);
            }

            switch($column) {

                case 'fullname':
                if (method_exists($this, 'is_sortable') && $this->is_sortable($column)) {
                    // Moodle >= 1.6
                    $icon_sort_first = $icon_sort_last = '';
                    if ($primary_sort_column == 'firstname') {
                        $lsortorder = get_string('asc');
                        if ($primary_sort_order == SORT_ASC) {
                            $icon_sort_first = ' <img src="'.$this->pixpath.'/t/down.gif" alt="'.get_string('asc').'" />';
                            $fsortorder = get_string('asc');
                        }
                        else {
                            $icon_sort_first = ' <img src="'.$this->pixpath.'/t/up.gif" alt="'.get_string('desc').'" />';
                            $fsortorder = get_string('desc');
                        }
                    }
                    else if ($primary_sort_column == 'lastname') {
                        $fsortorder = get_string('asc');
                        if ($primary_sort_order == SORT_ASC) {
                            $icon_sort_last = ' <img src="'.$this->pixpath.'/t/down.gif" alt="'.get_string('asc').'" />';
                            $lsortorder = get_string('asc');
                        }
                        else {
                            $icon_sort_last = ' <img src="'.$this->pixpath.'/t/up.gif" alt="'.get_string('desc').'" />';
                            $lsortorder = get_string('desc');
                        }
                    } else {
                        $fsortorder = get_string('asc');
                        $lsortorder = get_string('asc');
                    }
                    $this->headers[$index] = '<a href="'.$this->baseurl.$this->request[TABLE_VAR_SORT].'=firstname">'.get_string('firstname').get_accesshide(get_string('sortby').' '.get_string('firstname').' '.$fsortorder).'</a> '.$icon_sort_first.' / '.
                                          '<a href="'.$this->baseurl.$this->request[TABLE_VAR_SORT].'=lastname">'.get_string('lastname').get_accesshide(get_string('sortby').' '.get_string('lastname').' '.$lsortorder).'</a> '.$icon_sort_last;
                }
                break;

                case 'userpic':
                    // do nothing, do not display sortable links
                break;

                default:
                if (method_exists($this, 'is_sortable') && $this->is_sortable($column)) {
                    // Moodle >= 1.6
                    if ($primary_sort_column == $column) {
                        if ($primary_sort_order == SORT_ASC) {
                            $icon_sort = ' <img src="'.$this->pixpath.'/t/down.gif" alt="'.get_string('asc').'" />';
                            $localsortorder = get_string('asc');
                        }
                        else {
                            $icon_sort = ' <img src="'.$this->pixpath.'/t/up.gif" alt="'.get_string('desc').'" />';
                            $localsortorder = get_string('desc');
                        }
                    } else {
                        $localsortorder = get_string('asc');
                    }
                    $this->headers[$index] = '<a href="'.$this->baseurl.$this->request[TABLE_VAR_SORT].'='.$column.'">'.$this->headers[$index].get_accesshide(get_string('sortby').' '.$this->headers[$index].' '.$localsortorder).'</a>';
                }
            }

            if ($this->headers[$index] === null) {
                print '<th class="header c'.$index.$this->column_class[$column].'" scope="col">&nbsp;</th>';
            } else if (!empty($this->sess->collapse[$column])) {
                print '<th class="header c'.$index.$this->column_class[$column].'" scope="col">'.$icon_hide.'</th>';
            } else {
                // took out nowrap for accessibility, might need replacement
                if (!is_array($this->column_style[$column])) {
                    // $usestyles = array('white-space:nowrap');
                    $usestyles = '';
                } else {
                    // $usestyles = $this->column_style[$column]+array('white-space'=>'nowrap');
                    $usestyles = $this->column_style[$column];
                }
                print '<th class="header c'.$index.$this->column_class[$column].'" '.$this->make_styles_string($usestyles).' scope="col">'.$this->headers[$index];
                if ($icon_sort) {
                    print $icon_sort;
                }
                if ($icon_hide) {
                    print '<div class="commands">'.$icon_hide.'</div>';
                }
                print '</th>';
            }

        }
        print '</tr>'."\n";
    } // end function print_html_headers()

    function print_html_data() {
        // see print_html_data in old "mod/hotpot/report/default.php"
        // basically, any cell data can be an array which has properties
        //     e.g. $cell['text'], $cell['rowspan'], $cell['colspan'], $cell['attributes']

        if (empty($this->columns) || empty($this->data)) {
            return false;
        }

        $suppress_enabled = array_sum($this->column_suppress);
        $suppress_lastrow = null;

        $colcount = count($this->columns);
        $colbyindex = array_flip($this->columns);

        // for each column, keep count or how many rows after current row are to be skipped
        $skipcol = array();
        for ($c=0; $c<$colcount; $c++) {
            $skipcol[$c] = 0;
        }

        $oddeven = 1;
        foreach ($this->data as $row => $cells) {

            // start this row
            $oddeven = $oddeven ? 0 : 1;
            if (array_key_exists('class', $this->row_attributes[$row])) {
                if ($this->row_attributes[$row]['class']=='') {
                    unset($this->row_attributes[$row]['class']);
                }
            } else {
                $this->row_attributes[$row]['class'] = 'r'.$oddeven;
            }
            print '<tr'.$this->make_attributes_string($this->row_attributes[$row]).'>'."\n";

            if (is_null($cells)) {
                // print separator
                print '<td colspan="'.$colcount.'"><div class="tabledivider"></div></td>'."\n";
            } else {
                // print row of cells
                for ($col=0; $col<$colcount; $col++) {

                    if ($skipcol[$col]>0) {
                        // this cell is to be skipped
                        $skipcol[$col]--;
                        continue;
                    }

                    if (isset($cells[$col])) {
                        $cell = &$cells[$col];
                    } else {
                        // cell was not defined - a bit naughty really, but we can manage
                        $cell = '';
                    }

                    $text = '';
                    $column = $colbyindex[$col];
                    $styles = $this->column_style[$column];
                    $attributes = array('class' => 'cell c'.$col.$this->column_class[$column]);

                    // do we suppress printing of this $cell text?
                    $suppress = true;
                    if (empty($this->sess->collapse[$column])) {
                        if (empty($this->column_suppress[$column]) || is_null($suppress_lastrow) || $suppress_lastrow[$col] !== $cell) {
                            $suppress = false;
                        }
                    }

                    if (is_array($cell)) {
                        if (array_key_exists('text', $cell)) {
                            $text = $cell['text'];
                            unset($cell['text']);
                        }
                        if (array_key_exists('class', $cell)) {
                            $attributes['class'] .= ' '.$cell['class'];
                            unset($cell['class']);
                        }
                        if (array_key_exists('styles', $cell)) {
                            $styles = array_merge($styles, $cell['styles']);
                            unset($cell['styles']);
                        }
                        if (array_key_exists('rowspan', $cell)) {
                            if ($cell['rowspan']>1) {
                                // skip cells below this one
                                $skipcol[$col] = $cell['rowspan'] - 1;
                                $attributes['rowspan'] = $cell['rowspan'];
                            }
                            unset($cell['rowspan']);
                        }
                        if (array_key_exists('colspan', $cell)) {
                            if ($cell['colspan']>1) {
                                // skip cells to the right of this one
                                $c_max = min($colcount, $col + $cell['colspan']);
                                for ($c=$col+1; $c<$c_max; $c++) {
                                    $skipcol[$c]++;
                                }
                                $attributes['colspan'] = $cell['colspan'];
                            }
                            unset($cell['colspan']);
                        }
                        // transfer other cell attributes
                        foreach ($cell as $name => $value) {
                            $attributes[$name] = $value;
                        }
                    } else {
                        $text = $cell;
                    } // end if is_array($cell)

                    if ($suppress || $text=='') {
                        $text = '&nbsp;';
                    }

                    print '<td'.$this->make_attributes_string($attributes).$this->make_styles_string($styles).'>'.$text.'</td>'."\n";
                } // end foreach $cells
            } // end if is_null ($cells)

            // finish this row
            print '</tr>'."\n";

            if ($suppress_enabled) {
                $suppress_lastrow = $cells;
            }
        }
    } // end function print_html_data()

} // end class quizport_flexible_table
?>