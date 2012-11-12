<!--
// =====================================================================================
function JMemori(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JMatch';

    this.initQuestion = function (i, name) {
        this.questions[i].name = name;
        this.questions[i].type = 4;  // 4 = JMatch
        this.questions[i].text = ''; // always empty for JMatch
    }

    this.onclickCheck = function (id) {
        if (cardno==0) {
            return; // first card
        }

        var id_min = Math.min(id, clickarray[0][1]);
        var id_max = Math.max(id, clickarray[0][1]);

        if (id_min==id_max) {
            return; // same card
        }

        if (! this.questions[id_min]) {
            this.addQuestion(id_min);
            this.initQuestion(id_min, M[id_min][0]);
        }

        if (M[id_min][1][0]==M[id_max][1][0]) {
            var responses = this.questions[id_min].correct;
        } else {
            var responses = this.questions[id_min].wrong;
        }
        var g = M[id_max][0]; // content

        var r_max = responses.length;
        for (var r=0; r<r_max; r++) {
            if (g==responses[r]) {
                // this g(uess) is already in the array of responses
                break;
            }
        }

        if (r==r_max) {
            // this is new response to questions[id_min]
            responses[r] = g;
            this.questions[id_min].checks++;
        }
    }

    this.setScoreAndPenalties = function (forceRecalculate) {
        if (forceRecalculate) {
            window.Score = CalculateScore();
        }
        this.score = window.Score || 0;
        this.penalties = window.WMatches || 0;
    }

    // we don't set up the questions initially, because they
    // will be set up as needed when the user chooses a pair
    this.init(0, sendallclicks, forceajax);
}
JMemori.prototype = new hpQuizAttempt();
//-->