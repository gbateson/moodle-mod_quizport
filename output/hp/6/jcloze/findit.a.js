<!--
// =====================================================================================
function JClozeFindItA(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JCloze';

    this.initQuestion = function (i) {
        this.questions[i].name = parseInt(i)+1; // gap number
        this.questions[i].type = 2;             // 2 = JCloze
        this.questions[i].text = I[i][1][0][0]; // the correct word
        this.questions[i].guesses = new Array();
    }

    this.onclickCheck = function (iscorrect,i,g) {
        if (window.Finished) {
            return; // quiz is already finished
        }

        if (i>=GapList.length) {
            i = GapList.length - 1;
        }

        if (iscorrect && GapList[i][1].ErrorFound) {
            return; // gap is already correct
        }


        if (iscorrect) {
            g = GapList[i][1].WrongGapValue;
        }

        // shortcut to this question
        var question = this.questions[i];

        // increment check count (even if gap content has not changed)
        question.checks++;

        var g_max = question.guesses.length;
        if (g_max && g==question.guesses[g_max-1]) {
            // gap content has not changed
            return;
        }
        question.guesses[g_max] = g;

        // create shortcut ot array of correct or wrong responses
        if (iscorrect) {
            var responses = question.correct;
        } else {
            var responses = question.wrong;
        }

        var r_max = responses.length;
        for (var r=0; r<r_max; r++) {
            if (responses[r]==g) {
                // this guess has been entered before
                break;
            }
        }

        if (r==r_max) {
            // if this is a new g(uess), i.e. it has not been entered before
            // append g(uess) to the end of the array of responses
            responses[r] = g;
        }
    } // end function

    this.setQuestionScore = function (q) {
        if (GapList[q]) {
            this.questions[q].score = Math.max(0, 100 * GapList[q][1].Score) + '%';
        }
    }

    this.setScoreAndPenalties = function (forceRecalculate) {
        if (forceRecalculate) {
            window.Score = 0;
            var TotGaps = GapList.length;
            if (TotGaps){
                var TotCorrectChoices = 0;
                for (var i=0; i<TotGaps; i++){
                    if (GapList[i][1].ErrorFound){
                        TotCorrectChoices++;
                    }
                }
                if (TotCorrectChoices > TotWrongChoices){
                    window.Score = Math.floor(100 * (TotCorrectChoices - TotWrongChoices) / TotGaps);
                }
            }
        }
        this.score = window.Score || 0;
        this.penalties = window.Penalties || 0;
    }

    this.init(I.length, sendallclicks, forceajax);
}
JClozeFindItA.prototype = new hpQuizAttempt();
//-->