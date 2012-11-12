<!--
// =====================================================================================
function JMatchFlashcard(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JMatch';

    this.initQuestion = function (i) {
        this.questions[i].name = GetTextFromNode(document.getElementById('L_' + i));
        this.questions[i].type = 4;  // 4 = JMatch
        this.questions[i].text = ''; // always empty for JMatch
    }

    this.onclickCheck = function (CurrItem) {
        if (CurrItem && CurrItem.id && CurrItem.id.match(new RegExp('^I_\\d+$'))) {
            var i = parseInt(CurrItem.id.substring(2));
            if (Stage==1) {
                this.questions[i].checks++;
            } else {
                this.questions[i].correct[0] = GetTextFromNode(document.getElementById('R_' + i));
            }
        }
    }

    this.setScoreAndPenalties = function (forceRecalculate) {
        // do nothing
    }

    if (window.QList) {
        this.init(QList.length, sendallclicks, forceajax);
    }
}
JMatchFlashcard.prototype = new hpQuizAttempt();
//-->