<!--
// =====================================================================================
function JQuiz(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JQuiz';

    this.initQuestion = function (i) {
        var txt = GetTextFromNodeN(document.getElementById('Q_'+i), 'QuestionText');
        this.questions[i].name = txt; // the question (not always unique!)
        this.questions[i].type = 6;   // 6 = JQuiz
        this.questions[i].text = '';  // always empty for JQuiz
        this.questions[i].weighting = I[i][0];
    }

    this.onclickCheck = function (args) {
        if (! args) {
            // no args - shouldn't happen !!
            return;
        }
        var q = args[0]; // clue/question number
        var g = args[1]; // student's g(uess) at the correct response

        if (! g.length) {
            // no response
            return;
        }
        var G = g.toUpperCase(); // used for shortanswer only
        var correct_answer = ''; // used for multiselect only

        // set index of answer array in I (the question array)
        var i_max = I[q][3].length;
        for (var i=0; i<i_max; i++) {

            if (! I[q][3][i][2]) {
                // not a correct answer
                continue;
            }

            if (I[q][2]==3) {
                // multiselect
                correct_answer += (correct_answer  ? '+' : '') + I[q][3][i][0];
            } else {
                // multichoice, shortanswer
                if (window.CaseSensitive) {
                    if (g==I[q][3][i][0]) {
                        // case sensitive match found
                        break;
                    }
                } else {
                    if (G==I[q][3][i][0].toUpperCase()) {
                        // case INsensitive match found
                        break;
                    }
                }
            }
        } // end for loop

        if (i<i_max || g==correct_answer) {
            var responses = this.questions[q].correct;
        } else {
            var responses = this.questions[q].wrong;
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

        // increment check count
        this.questions[q].checks++;
    }

    this.setQuestionScore = function (q) {
        // questions that were not displayed have State[q] == null
        if (State[q]) {
            this.questions[q].score = Math.max(0, I[q][0] * State[q][0]) + '%';
        }
    }

    this.setScoreAndPenalties = function (forceRecalculate) {
        if (forceRecalculate) {
            // based on JQuiz calculateOverallScore()
            var TotalWeighting = 0;
            var TotalScore = 0;
            var TotalCount = 0;
            for (var QNum=0; QNum<State.length; QNum++){
                if (State[QNum]){
                    // question was displayed
                    TotalWeighting += I[QNum][0];
                    if (State[QNum][0] > -1){
                        // question was attempted
                        TotalScore += (I[QNum][0] * State[QNum][0]);
                        TotalCount ++;
                    }
                }
            }
            if (TotalWeighting > 0){
                window.Score = Math.floor((TotalScore/TotalWeighting)*100);
            } else if (TotalCount) {
                window.Score = 100;
            } else {
                // no questions attempted
                window.Score = 0;
            }
        }
        this.score = window.Score || 0;
        this.penalties = window.Penalties || 0;
    }

    this.init(State.length, sendallclicks, forceajax);
}
JQuiz.prototype = new hpQuizAttempt();
//-->