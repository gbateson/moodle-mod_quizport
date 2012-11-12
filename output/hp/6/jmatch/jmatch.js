<!--
// =====================================================================================
function JMatch(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JMatch';

    this.initQuestion = function (i) {
        if (window.F) {
            this.questions[i].name = F[i][0];
        } else {
            this.questions[i].name = GetTextFromNodeN(document.getElementById('Questions'), 'LeftItem', i);
        }
        this.questions[i].type = 4;  // 4 = JMatch
        this.questions[i].text = ''; // always empty for JMatch
    }

    this.onclickCheck = function (setScores) {
        if (window.F) {
            var i_max = F.length;
        } else if (window.Status) {
            var i_max = Status.length;
        } else {
            var i_max = 0;
        }
        var TotalCorrect = 0;
        for (var i=0; i<i_max; i++) {
            if (this.questions[i].correct.length) {
                // this question has already been correctly answered
                TotalCorrect++;
                continue;
            }

            // get the guess, if any
            var g = this.GetJMatchRHS(i);
            if (g.length==0) {
                // no g(uess) entered
                continue;
            }

            if (g==this.GetJMatchRHS(i, true)) {
                var responses = this.questions[i].correct;
                TotalCorrect++;
            } else {
                var responses = this.questions[i].wrong;
            }

            var r_max = responses.length;
            for (var r=0; r<r_max; r++) {
                if (g==responses[r]) {
                    // this g(uess) is already in the array of responses
                    break;
                }
            }

            if (r==r_max) {
                // this is new response to questions[i]
                responses[r] = g;
                this.questions[i].checks++;
            }
        } // end for loop

        if (setScores && i_max) {
            window.Score = Math.floor(((TotalCorrect - Penalties) * 100) / i_max);
        }
    }

    this.GetJMatchRHS = function (q, getCorrect) {
        var rhs = '';
        if (window.F && window.D) {
            // v6+ (=drag and drop)
            var ii = getCorrect ? 1 : 2;
            for (var i=0; i<D.length; i++) {
                if (F[q][1]==D[i][ii]) {
                    rhs = D[i][0];
                    break;
                }
            }
        } else if (window.Status) {
            // v6 (=plain old select elements)
    		var obj = document.getElementById(Status[q][2]);
    		if (obj) { // not correct yet
    			if (getCorrect) {
    				var k = GetKeyFromSelect(obj); // HP function
    				var i_max = obj.options.length;
    				for (var i=0; i<i_max; i++) {
    					if (obj.options[i].value==k) {
                            break;
                        }
    				}
    				if (i>=i_max) {
                        i = 0; // shouldn't happen
                    }
    			} else {
    				// get current guess, if any
    				var i = obj.selectedIndex;
    			}
    			if (i) {
                    rhs = obj.options[i].innerHTML;
                }
    		} else { // correct
                rhs = GetTextFromNodeN(document.getElementById('Questions'), 'RightItem', q);
    		}
        }
        return rhs;
    }

    if (window.F) {
        this.init(F.length, sendallclicks, forceajax);
    } else if (window.Status) {
        this.init(Status.length, sendallclicks, forceajax);
    }
}
JMatch.prototype = new hpQuizAttempt();
//-->