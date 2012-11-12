//<![CDATA[
// modified "showAdvancedOnClick" function, which hides only the section containing the button which was clicked
function quizport_showAdvancedOnClick(button, hidetext, showtext){
    // locate the FIELDSET object containing the button that was clicked
    if (window.showAdvancedInit) {
        // Moodle >= 1.9: "button" is actually the "e" (event) object
        if (button.target) {
            button = button.target;
        } else if (button.srcElement) {
            button = button.srcElement;
        }
        hidetext = button.moodle.hideLabel;
        showtext = button.moodle.showLabel;
    }
    var obj = button;
    while (obj && obj.tagName!='FIELDSET') {
        obj = obj.parentNode;
    }
    if (obj) {
        // get all "advanced" elements in this FIELDSET object
        var toSet = findChildNodes(obj, null, 'advanced');

        // get previous show/hide settings
        var last = button.form.elements['mform_showadvanced_last'];
        if (! last) {
            return false;
        }
        if (last.value=='') {
            var lastvalue = 0;
        } else {
            var lastvalue = parseInt(last.value);
        }

        var showhide = 0; // 0=do nothing, 1=show, -1=hide
        if (button.initialized) {
            if (lastvalue & button.bitmask) {
                // this section is currently visible, so hide it
                showhide = -1;
                last.value = (lastvalue & (~button.bitmask));
            } else {
                // this section is currently hidden, so make it visible
                showhide = 1;
                last.value = (lastvalue | button.bitmask);
            }
        } else {
            if (lastvalue && ! (lastvalue & button.bitmask)) {
                // this section is showing but it should be hidden, so hide it
                showhide = -1;
            }
            button.initialized = true;
        }
        switch (showhide) {
            case 1:
                elementShowAdvanced(toSet, true);
                button.value = hidetext;
                break;
            case -1:
                elementShowAdvanced(toSet, false);
                button.value = showtext;
                break;
        }
    }
    //never submit the form if js is enabled.
    return false;
}

var obj = document.getElementsByTagName('input');
if (obj) {
    var bitmask = 1;
    for (var i=0; i<obj.length; i++) {
        if (obj[i].name && obj[i].name=='mform_showadvanced') {
            obj[i].bitmask = bitmask;
            if (window.showAdvancedInit) {
                YAHOO.util.Event.removeListener(obj[i], 'click', showAdvancedOnClick);
                YAHOO.util.Event.addListener(obj[i], 'click', quizport_showAdvancedOnClick);
                quizport_showAdvancedOnClick(obj[i]);
            } else {
                window.showAdvancedOnClick = quizport_showAdvancedOnClick;
                obj[i].onclick(); // obj.click()
            }
            bitmask = bitmask * 2;
        }
    }
    obj = null;
}

function getBitMask(id) {
    switch (id) {
        case 'general': return 1;
        case 'displayhdr': return 2;
        case 'accesscontrolhdr': return 4;
        case 'assessmenthdr': return 8;
        default: return 0;
    }
}
function getObjValue(obj) {
    var v = ''; // the value
    var t = (obj && obj.type) ? obj.type : "";
    if (t=="text" || t=="textarea" || t=="hidden") {
        v = obj.value;
    } else if (t=="select-one" || t=="select-multiple") {
        var l = obj.options.length;
        for (var i=0; i<l; i++) {
            if (obj.options[i].selected) {
                v += (v=="" ? "" : ",") + obj.options[i].value;
            }
        }
    }
    return v;
}
function getDir(s) {
    if (s.substring(0,7)=='http://' || s.substring(0,8)=='https://') {
        return '';
    }
    if (s.charAt(0) != '/') {
        s = '/' + s;
    }
    return s.substring(0, s.lastIndexOf('/'));
}
function quizport_AddWhiteSpace(BeforeOrAfter, IdOrName, x, IsCheckbox) {
    var obj = false;
    if (IsCheckbox) {
        var objs = document.getElementsByTagName('input');
        var i_max = objs.length;
        for (var i=0; i<i_max; i++) {
            if (objs[i].type && objs[i].type=='checkbox') {
                if (objs[i].name && objs[i].name==IdOrName) {
                    obj = objs[i];
                    break;
                }
            }
        }
        objs = null;
    } else if (document.getElementById) {
        obj = document.getElementById(IdOrName);
    }
    if (obj) {
        // set target classnames of container element
        if (BeforeOrAfter=='before') {
            var targetclassname = new RegExp('\\b' + 'fitem|ftextarea' + '\\b');
        } else {
            var targetclassname = new RegExp('\\b' + 'fitem|ftextarea|fgroup' + '\\b');
        }

        // locate the appropriate container element:
        //  - DIV object (class="fitem")
        //  - FIELDSET object (class="felement fgroup")
        while (obj && ! (obj.className && obj.className.match(targetclassname))) {
            obj = obj.parentNode;
        }
    }
    if (obj) {
        // add extra white space to the container element
        if (BeforeOrAfter=='before') {
            obj.style.marginTop = (x ? x : '12px');
        } else {
            obj.style.marginBottom = (x ? x : '12px');
        }
    }
}

// unit + quiz
quizport_AddWhiteSpace('after', 'id_name', '18px');
quizport_AddWhiteSpace('after', 'id_sourcefile');
quizport_AddWhiteSpace('after', 'id_configfile');
quizport_AddWhiteSpace('after', 'id_allowresume');

if (document.getElementById('id_delay3')) {
    // quiz only
    quizport_AddWhiteSpace('before', 'id_timelimitdisable');
    quizport_AddWhiteSpace('after', 'id_delay3');
} else {
    // unit only
    quizport_AddWhiteSpace('after', 'id_height');
    quizport_AddWhiteSpace('after', 'entry_attempts', '', true);
    quizport_AddWhiteSpace('after', 'exit_grades', '', true);
    quizport_AddWhiteSpace('before', 'timelimitdisable', '', true);
    quizport_AddWhiteSpace('after', 'delay2disable', '', true);
}
//]]>