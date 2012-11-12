<!--
// =====================================================================================
function JClozeJGloss(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JCloze';

    this.initQuestion = function (i) {
        this.questions[i].name = parseInt(i)+1; // gap number
        this.questions[i].type = 2; // 2 = JCloze
        this.questions[i].text = I[i][2]; // clue (=definition)
        this.questions[i].correct = I[i][1][0][0]; // correct answer
    }

    this.onclickCheck = function (i) {
        if (typeof(i)=='number' && this.questions[i]) {
            this.questions[i].checks++;
            this.questions[i].score = '100%';
        }
        if (window.Finished) {
            return;
        }
        var count = 0;
        for (var i in this.questions) {
            if (this.questions[i].checks) {
                count++;
            }
        }
        if (count) {
            if (count==this.questions.length) {
                window.Score = 100;
                window.Finished = true;
            } else {
                window.Score = Math.floor(100 * (count / this.questions.length));
            }
        }
    }

    this.init(I.length, sendallclicks, forceajax);
}
JClozeJGloss.prototype = new hpQuizAttempt();
//-->