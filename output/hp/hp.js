<!--
// =====================================================================================
function hpQuizAttempt() {
// =====================================================================================
    this.status    = 0;
    this.redirect  = 0;
    this.penalties = 0;
    this.score     = 0;
    this.forceajax = false;
    this.sendallclicks = false;

    this.questions = new Array();

    this.quiztype  = ''; // JCloze JCross JMatch JMix JQuiz
    this.form      = null; // reference to document.forms['store']
    this.formlock  = false; // prevents duplicate sets of results being sent
    this.starttime = null; // Date object for start time as recorded by client
    this.endtime   = null; // Date object for end time as recorded by client

    this.init = function (questionCount, sendallclicks, forceajax) {
        this.form = this.findForm('store', self);
        if (questionCount) {
            this.initQuestions(questionCount);
        }
        if ((typeof(sendallclicks)=='string' && parseInt(sendallclicks)) || (typeof(sendallclicks)=='number' && sendallclicks) || (typeof(sendallclicks)=='boolean' && sendallclicks)) {
            this.sendallclicks = true;
        }
        if ((typeof(forceajax)=='string' && parseInt(forceajax)) || (typeof(forceajax)=='number' && forceajax) || (typeof(forceajax)=='boolean' && forceajax)) {
            this.forceajax = true;
        }
        this.status = 1; // in progress
        this.starttime = new Date();
    }

    this.initQuestions = function (questionCount) {
        for (var i=0; i<questionCount; i++) {
            this.addQuestion(i);
            this.initQuestion(i);
        }
    }

    this.initQuestion = function (i) {
        // this function will be "overloaded" by subclass
    }

    this.addQuestion = function (i) {
        this.questions[i] = new hpQuestion();
    }

    this.onclickClue = function (i) {
        this.questions[i].clues++;
        if (this.sendallclicks) {
            this.onunload(0);
        }
    }

    this.onclickHint = function (i) {
        this.questions[i].hints++;
        if (this.sendallclicks) {
            this.onunload(0);
        }
    }

    this.onclickCheck = function (setScores) {
        // this function will be "overloaded" by subclass
    }

    this.addFields = function (xml) {
        // looop through fields in this attempt
        for (var fieldname in this) {
            switch (fieldname) {
                // case 'quiztype':
                case 'status':
                case 'penalties':
                case 'score':
                    xml.addField(this.quiztype+'_'+fieldname, this[fieldname]);
                    break;

                case 'questions':
                    var keys = object_keys(this.questions, 1); // 1 = properties only
                    var x;
                    while (x = keys.shift()) {
                        this.questions[x].addFields(xml, this.getQuestionPrefix(x));
                    }
                    break;
            }
        }
    }

    this.getQuestionPrefix = function (i) {
        return this.quiztype + '_q' + (parseInt(i)<9 ? '0' : '') + (parseInt(i)+1) + '_';
    }

    this.setQuestionScore = function (q) {
        this.questions[q].score = 0;
    }

    this.setScoreAndPenalties = function (forceRecalculate) {
        if (forceRecalculate) {
            // trigger this.onclickCheck() event to save responses and set scores
            this.onclickCheck(true);
        }
        this.score = window.Score || 0;
        this.penalties = window.Penalties || 0;
    }

    this.lock = function () {
        this.formlock = true;
    }

    this.unlock = function () {
        this.formlock = false;
    }

    this.islocked = function () {
        return this.formlock;
    }

    this.onunload = function (status, flag) {
        if (! this.form) {
            // results have already been submitted
            return true;
        }

        if (this.islocked()) {
            // results have just been submitted so don't send duplicates
            // this may happen if user clicks on a link away from the page
            // both the "onclick" and the "onunload" event call this function
            return true;
        }

        // make sure flag is set : 0=do everything, 1=set form values, -1=send form
        if (typeof(flag)=='undefined') {
            flag = 0;
        }

        // lock the form for 2 seconds
        if (flag<=0) {
            this.lock();
            setTimeout('if(window.HP)HP.unlock();', 2000);
        }

        // set form values if necessary
        if (flag>=0) {

            // set status : 0=undefined, 1=in progress, 2=timed out, 3=abandoned, 4=completed
            if (status) {
                this.status = status;
                if (status>1) {
                    // we set this flag here to tell the server that it is OK to redirect,
                    // but whether we actually get redirected or not is up to the server
                    this.redirect = 1;
                }
                var forceRecalculate = false;
            } else {
                // onunload has been triggered by user navigating away from this page
                // so we want to try to send results and let them continue
                var forceRecalculate = true;
                this.forceajax = true;
            }

            // get end time and round down duration to exact number of seconds
            this.endtime = new Date();
            var duration = this.endtime - this.starttime;
            this.endtime.setTime(this.starttime.getTime() + duration - (duration % 1000));

            // set score for each question
            var keys = object_keys(this.questions, 1); // 1 = properties only
            var q;
            while (q = keys.shift()) {
                this.setQuestionScore(q);
            }

            // set score and penalties
            this.setScoreAndPenalties(forceRecalculate);

            // create XML
            var XML = new hpXML();
            this.addFields(XML);

            // transfer results to form
            this.form.mark.value = this.score;
            this.form.detail.value = XML.getXML();
            this.form.status.value = this.status;
            this.form.redirect.value = this.redirect;
            this.form.starttime.value = this.getTimeString(this.starttime);
            this.form.endtime.value = this.getTimeString(this.endtime);
        } // end if flag>=0 (set values)

        // send form if necessary
        if (flag<=0) {
            // submit results to Moodle

             // cancel the check for navigating away from this page
            window.onbeforeunload = null;

            // based on http://www.captain.at/howto-ajax-form-post-request.php
            var useajax = false;
            if (typeof(window.HP_httpRequest)=='undefined') {
                window.HP_httpRequest = false;
                if (this.forceajax || this.redirect==0) {
                    if (window.XMLHttpRequest) { // Mozilla, Safari,...
                        HP_httpRequest = new XMLHttpRequest();
                    } else if (window.ActiveXObject) { // IE
                        try {
                            HP_httpRequest = new ActiveXObject("Msxml2.XMLHTTP");
                        } catch (e) {
                            try {
                                HP_httpRequest = new ActiveXObject("Microsoft.XMLHTTP");
                            } catch (e) {
                                HP_httpRequest = false;
                            }
                        }
                    }
                }
                if (HP_httpRequest) {
                    useajax = true;
                }
            }

            if (useajax) {
                var parameters = '';
                var i_max = this.form.elements.length;
                for (var i=0; i<i_max; i++) {
                    var obj = this.form.elements[i];
                    if (! obj.name) {
                        continue;
                    }
                    var value = this.getFormElementValue(obj);
                    if (! value) {
                        continue;
                    }
                    parameters += (parameters=='' ? '' : '&') + obj.name + '=' + escape(value); // encodeURI
                }
                HP_httpRequest.onreadystatechange = HP_onreadystatechange;
                HP_httpRequest.open(this.form.method, this.form.action, (this.forceajax ? false : true)); // false=SYNCHRONOUS, true=ASYNCHRONOUS
                HP_httpRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                HP_httpRequest.send(parameters);
            } else {
                this.form.submit();
            }

            if (this.status>1) {
                // quiz is finished, so ensure results do not get submitted again
                this.form = null;
            } else if (window.quizportbeforeunload) {
                // quiz is not finished yet, so restore onbeforeunload
               window.onbeforeunload = window.quizportbeforeunload;
            }
       }

    } // end function onunload

    this.getFormElementValue = function (obj) {
        var v = ''; // value
        var t = obj.type;
		if (t=='text' || t=='textarea' || t=='password' || t=='hidden') {
			v = obj.value;
        } else if (t=='radio' || t=='checkbox') {
			if (obj.checked) {
                v = obj.value;
            }
        } else if (t=='select-one' || t=='select-multiple') {
			var i_max = obj.options.length;
			for (var i=0; i<i_max; i++) {
				if (obj.options[i].selected) {
					v += (v=='' ? '' : ',') + obj.options[i].value;
				}
			}
        } else if (t=='button' || t=='reset' || t=='submit') {
            // do nothing
        } else {
            // radio or checkbox groups
            var i_max = obj.length || 0;
            for (var i=0; i<i_max; i++) {
                if (obj[i].checked) {
                    v += (v=='' ? '' : ',') + obj[i].value;
                }
            }
        }
        return v;
    }

    this.getTimeString = function (date) {
        if (date==null) {
            // get default Date object
            date = new Date();
        }
        // get year, month and day (yyyy-mm-dd)
        var s = date.getFullYear() + '-' + pad(date.getMonth()+1, 2) + '-' + pad(date.getDate(), 2);
        // get hours, minutes and seconds (hh:mm:ss)
        s += ' ' + pad(date.getHours(), 2) + ':' + pad(date.getMinutes(), 2) + ':' + pad(date.getSeconds(), 2);
        // get time difference (+xxxx)
        var x = date.getTimezoneOffset(); // e.g. -540
        if (typeof(x)=='number') {
            s += ' ' + (x<0 ? '+' : '-');
            x = Math.abs(x);
            s += pad(parseInt(x/60), 2) + pad(x - (parseInt(x/60)*60), 2);
        }
        return s;
    }

    this.findForm = function (id, w) {
        var f = w.document.forms[id];
        if (! f) {
            var i_max = (w.frames ? w.frames.length : 0);
            for (var i=0; i<i_max; i++) {
                f = this.findForm(id, w.frames[i]);
                if (f) break;
            }
        }
        return f;
    }
}

function HP_onreadystatechange() {
    // http://www.webdeveloper.com/forum/showthread.php?t=108334
    if (! window.HP_httpRequest) {
        return false;
    }
    if (HP_httpRequest.readyState==4) {
        switch (HP_httpRequest.status) {
            case 200:
                // we do not expect to get any real content on this channel
                // it is probably an error message from the server, so display it
                document.write(HP_httpRequest.responseText);
                document.close();
                break;
            case 204:
                // the server has fulfilled the request
                // we can unset the HP_httpRequest object
                window.HP_httpRequest = null;
                break;
            default:
                // alert('Unexpected httpRequest.status: '+HP_httpRequest.status);
        }
    }
}

// =====================================================================================
function hpQuestion() {
// =====================================================================================
    this.name      = '';
    this.type      = '';
    this.text      = '';

    this.score     = 0;
    this.weighting = 0;
    this.hints     = 0;
    this.clues     = 0;
    this.checks    = 0;
    this.correct   = new Array();
    this.wrong     = new Array();
    this.ignored   = new Array();

    this.addFields = function (xml, prefix) {
        // add fields for this question
        for (var fieldname in this) {
            switch (fieldname) {
                case 'name':
                case 'type':
                case 'text':
                case 'score':
                case 'weighting':
                case 'hints':
                case 'clues':
                case 'checks':
                case 'correct':
                case 'wrong':
                case 'ignored':
                    xml.addField(prefix+fieldname, this[fieldname]);
                    break;
            }
        }
    }
}

// =====================================================================================
function hpXML() {
// =====================================================================================
    this.xml = '';
    this.fields = new Array();

    this.addField = function (name, value) {
        this.fields[this.fields.length] = new hpField(name, value);
    }

    this.getXML = function () {
        this.xml = '<'+'?xml version="1.0"?'+'>\n';
        this.xml += '<hpjsresult><fields>\n';
        for (var i=0; i<this.fields.length; i++) {
            this.xml += this.fields[i].getXML();
        }
        this.xml += '</fields></hpjsresult>\n';
        return this.xml;
    }
}

// =====================================================================================
function hpField(name, value) {
// =====================================================================================
    this.name = name;
    this.value = value;
    this.data = ''; // xml field data (i.e. "value" encoded for XML)

    this.getXML = function () {
        this.data = '';
        switch (typeof(this.value)) {
            case 'string':
                this.data += this.encode_entities(this.value);
                break;

            case 'object': // array
                var i_max = this.value.length;
                for (var i=0; i<i_max; i++) {
                    var v = trim(this.value[i]);
                    if (v.length) {
                        this.data += (i==0 ? '' : ',') +  this.encode_entities(v);
                    }
                }
                break;

            case 'number':
                this.data = ('' + this.value);
                break;
        }
        if (this.data.length==0) {
            return '';
        } else {
            if (this.data.indexOf('<')>=0 && this.data.indexOf('>')>=0) {
                this.data = '<' + '![CDATA[' + this.data + ']]' + '>';
            }
            return '<field><fieldname>' + this.name + '</fieldname><fielddata>' + this.data + '</fielddata></field>\n';
        }
    }

    this.encode_entities = function (s_in) {
        var i_max = (s_in) ? s_in.length : 0;
        var s_out = '';
        for (var i=0; i<i_max; i++) {
            var c = s_in.charCodeAt(i);
            // 34 : double quote .......["] &quot;
            // 38 : ampersand ..........[&] &amp;
            // 39 : single quote .......['] &apos;
            // 43 : plus sign ..........[+]
            // 44 : comma ..............[,]
            // 60 : left angle bracket .[<] &lt;
            // 62 : right angle bracket [>] &gt;
            // >=128 multibyte character
            if (c==38 || c==43 || c==44 || c>=128) {
                // encode ampersand, plus sign, comma and multibyte chars
                s_out += '&#x' + pad(c.toString(16), 4) + ';';
            } else {
                s_out += s_in.charAt(i);
            }
        }
        return s_out;
    }
};

///////////////////////////////////////////
// handle quiz events and send results
///////////////////////////////////////////

/**
 * HP_send_results
 *
 * @param integer evt one of the HP.EVENT_xxx contants
 * @return boolean
 */
function HP_send_results(evt) {
    if (evt==null || window.HP==null) {
        return ''; // shouldn't happen !!
    }

    // extract and convert event type, if necessary
    if (typeof(evt)=='object') {
        evt = (evt.type ? evt.type.toUpperCase() : '');
        evt = (HP['EVENT_' + evt] || HP.EVENT_EMPTY);
    }

    // default status
    var status = HP.STATUS_NONE;

    // default action is not to send results
    var send_results = false;

    switch (true) {

        case HP.end_of_quiz():
            // quiz is already finished
            break;

        case HP.end_of_quiz(evt):
            // quiz has just finished
            send_results = true;
            switch (evt) {
                case HP.EVENT_TIMEDOUT:   status = HP.STATUS_TIMEDOUT;  break;
                case HP.EVENT_ABANDONED:  status = HP.STATUS_ABANDONED; break;
                case HP.EVENT_COMPLETED:  status = HP.STATUS_COMPLETED; break;
                case HP.EVENT_SETVALUES:  status = HP.STATUS_COMPLETED; break;
                case HP.EVENT_SENDVALUES: status = HP.STATUS_COMPLETED; break;
            }
            break;

        case HP.navigation_event(evt) && (HP.quiz_input_event() || HP.quiz_button_event()):
            // navigation event, following a button or input event
            // we need to set status to ABANDONED, because this may be our last chance
            send_results = true;
            status = HP.STATUS_ABANDONED;
            break;

        case (HP.quiz_input_event(evt) || HP.quiz_button_event(evt)) && HP.navigation_event():
            // button or input event, following a navigation event
            // we need to set status to INPROGRESS, in case it was set to ABANDONED above
            send_results = true;
            status = HP.STATUS_INPROGRESS;
            break;

        case HP.sendallclicks && HP.quiz_button_event(evt):
            // send all button events for the "click report"
            send_results = true;
            status = HP.STATUS_INPROGRESS;
            break;
    }

    if (send_results) {
        HP.send_results(evt, status);
    }

    if (evt==HP.EVENT_BEFOREUNLOAD && window.HP_beforeunload) {
        return HP_beforeunload();
    } else {
        return evt;
    }
};
//-->