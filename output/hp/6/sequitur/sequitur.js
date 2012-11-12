<!--
// =====================================================================================
function Sequitur(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'Sequitur';

    this.initQuestion = function (i) {
        this.questions[i].name = i+1; // since there is only one question, this is always "1"
        this.questions[i].type = 8;   // 8 = Sequitur
        this.questions[i].text = '';  // always empty for Sequitur
    }

    this.onclickCheck = function (Chosen) {
        if (Chosen==0) {
            return; // stop button
        }
        var g = GetTextFromNode(document.getElementById('Choice'+Chosen));
        if (! g) {
            return; // shouldn't happen
        }
        if (Chosen==CurrentCorrect) {
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
        if (window.TotalPointsAvailable) {
            this.score = Math.floor(100 * ScoredPoints / TotalPointsAvailable);
        } else if (Finished) {
            this.score = Math.floor(100 * ScoredPoints / TotalPoints);
        } else {
            this.score = Math.floor(100 * ScoredPoints / (TotalPoints - OptionsThisQ + 1));
        }
    }

    this.init(1, sendallclicks, forceajax);
}
Sequitur.prototype = new hpQuizAttempt();
//-->