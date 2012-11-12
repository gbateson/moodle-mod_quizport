<!--
// =====================================================================================
function JMix(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JMix';

    this.initQuestion = function (i) {
        this.questions[i].name = i+1; // since there is only one question, this is always "1"
        this.questions[i].type = 5;   // 5 = JMix
        this.questions[i].text = '';  // always empty for JMix
    }

    this.onclickHint = function (i) {
        if (typeof(i)=='undefined') {
            i = 0; // default (actually, i should *always* be 0)
        }
        if (this.questions[i]) {
            this.questions[i].hints++;
        }
        // don't send results, even if this.sendallresults is set,
        // because since the same function, "CheckAnswer" handles both Checks and Hints,
        // the results will be sent at the end of "CheckAnswer" anyway
    }
    this.onclickCheck = function (setScores) {

        // there is only ever one question in JMix
        var q = 0;

        if (this.questions[q].correct.length) {
            // correct answer has already been found
            return;
        }

        // update the GuessSequence array
        if (setScores) {
            GetGuessSequence();
        }

        // get concatenated segments in this g(uess)
        var g = '';
        for (var i=0; i<GuessSequence.length; i++) {
            for (var ii=0; ii<Segments.length; ii++) {
                if (Segments[ii][1] == GuessSequence[i]) {
                    g += (g=='' ? '' : '+') + Segments[ii][0];
                    break;
                }
            }
        }

        if (g.length==0) {
            // no guess sequence
            return;
        }

        // match current guess sequence against possible correct answers
        var MaxCorrectSegments = 0;
        var i_max = Answers.length;
        for (var i=0; i<i_max; i++) {
            var CorrectSegments = 0;
            var ii_max = Answers[i].length;
            for (var ii=0; ii<ii_max; ii++) {
                if (Answers[i][ii] != GuessSequence[ii]) {
                    // incorrect answer was found
                    break;
                }
                CorrectSegments++;
            }
            MaxCorrectSegments = Math.max(CorrectSegments, MaxCorrectSegments);
            if (ii==ii_max) {
                // correct answer was found
                break;
            }
        }

        // set total score for this quiz
        if (setScores) {
            window.Score = Math.max(0, Math.floor(((MaxCorrectSegments - Penalties) * 100) / Segments.length));
        }

        if (i==i_max) { // no correct answer was found
            var responses = this.questions[q].wrong;
        } else {
            var responses = this.questions[q].correct;
        }

        var r_max = responses.length;
        for (var r=0; r<r_max; r++) {
            if (g==responses[r]) {
                // this g(uess) is already in the array of responses
                break;
            }
        }

        if (r==r_max) {
            // this is new response
            responses[r] = g;
        }
        this.questions[q].checks++;

    } // end function : onclickCheck

    this.init(1, sendallclicks, forceajax);
}
JMix.prototype = new hpQuizAttempt();
//-->