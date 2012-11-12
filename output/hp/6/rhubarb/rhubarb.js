<!--
// =====================================================================================
function Rhubarb(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'Rhubarb';

    this.initQuestion = function (i) {
        this.questions[i].name = i+1; // since there is only one question, this is always "1"
        this.questions[i].type = 7;   // 7 = Rhubarb
        this.questions[i].text = '';  // always empty for Rhubarb
    }

    this.onclickCheck = function (g) {
        var G = g.toUpperCase();
        var i_max = Words.length;
        for (var i=0; i<i_max; i++) {
            if (G==Words[i].toUpperCase()) {
                // a correct word
                break;
            }
        }
        if (i<i_max) {
            var responses = this.questions[0].correct;
        } else {
            var responses = this.questions[0].wrong;
        }

        var r_max = responses.length;
        for (var r=0; r<r_max; r++) {
            if (responses[r]==g) {
                // this g(uess) has been entered before
                break;
            }
        }
        if (r==r_max) {
            // if this is a new g(uess), i.e. it has not been entered before,
            // append g(uess) to the end of the array of responses
            responses[r] = g;
            this.questions[0].checks++;
        }
    }

    this.setScoreAndPenalties = function (forceRecalculate) {
        if (forceRecalculate) {
        }
        this.score = Math.floor(100*Correct/TotalWords);
    }

    this.init(1, sendallclicks, forceajax);
}
Rhubarb.prototype = new hpQuizAttempt();
//-->