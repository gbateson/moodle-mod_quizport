<!--
// =====================================================================================
function JCross(sendallclicks, forceajax) {
// =====================================================================================
    this.quiztype = 'JCross';

    this.direction = new Array();
    this.direction['A'] = 'across';
    this.direction['D'] = 'down';

    this.initQuestions = function (questionCount) {
        // Note: questionCount is not actually used
        for (var row=0; row<L.length; row++) {
            for (var col=0; col<L[row].length; col++) {
                var q = CL[row][col];
                if (q) {
                    for (var AD in this.direction) {
                        var obj = document.getElementById('Clue_'+AD+'_'+q);
                        if (obj) {
                            var x = this.getQuestionName(q, AD);
                            this.addQuestion(x);
                            this.initQuestion(x, GetTextFromNodeN(obj, 'Clue'));
                        }
                    }
                }
            }
        }
    }

    this.getQuestionName = function (q, AD) {
        return q + '_' + this.direction[AD];
    }

    this.getQuestionPrefix = function (x) {
        // x  is the question key e.g. 1_across, 2_down
        return this.quiztype + '_q' + (parseInt(x)<9 ? '0' : '') + x + '_';
    }

    this.initQuestion = function (x, clue) {
        this.questions[x].name = x;     // e.g. 1_across, 2_down
        this.questions[x].type = 3;     // 3 = JCross
        this.questions[x].text = clue;  // clue text
        // it would be possible to capture all input from "Enter" button into the "guesses" array
        // this may give more information than just the "Check" button
        // this.questions[x].guesses   = new Array();
    }

    this.onclickCheck = function (setScores) {
        if (setScores) {
            var TotLetters = 0;
            var CorrectLetters = 0;
        }
        for (var row=0; row<L.length; row++) {
            for (var col=0; col<L[row].length; col++) {

                if (setScores && L[row][col]) {
                    TotLetters++;
                    if (window.CaseSensitive) {
                        if (L[row][col]==G[row][col]) {
                            CorrectLetters++;
                        }
                    } else {
                        if (L[row][col].toUpperCase()==G[row][col].toUpperCase()) {
                            CorrectLetters++;
                        }
                    }
                }

                var q = CL[row][col];
                if (! q) {
                    // no question number (i.e. not the start of a word)
                    continue;
                }

                for (var AD in this.direction) {
                    var clue = GetTextFromNodeN(document.getElementById('Clue_'+AD+'_'+q), 'Clue');
                    if (! clue) {
                        // no clue - shouldn't happen !!
                        continue;
                    }
                    // set question name e.g. 1_across, 2_down
                    var x = this.getQuestionName(q, AD);

                    if (this.questions[x].correct.length) {
                        // already correct
                        continue;
                    }

                    // the "G" array holds the student's guesses at the correct letters
                    var g = this.getJCrossWord(G, row, col, (AD=='D'));
                    if (! g) {
                        // no g(uess) entered by user
                        continue;
                    }

                    // the "L" array holds the letters in the correct answers
                    var correct = this.getJCrossWord(L, row, col, (AD=='D'));

                    if (window.CaseSensitive) {
                        var is_correct = (g == correct);
                    } else {
                        var is_correct = (g.toUpperCase() == correct.toUpperCase());
                    }
                    if (is_correct) {
                        var responses = this.questions[x].correct;
                    } else {
                        var responses = this.questions[x].wrong;
                    }

                    var r_max = responses.length;
                    for (var r=0; r<r_max; r++) {
                        if (responses[r]==g) {
                            // this g(uess) has been entered before
                            break;
                        }
                    }
                    if (r==r_max) {
                        // if this is a new g(uess), i.e. it has not been entered before
                        // append g(uess) to the end of the array of responses
                        responses[r] = g;
                        this.questions[x].checks++;
                    }
                } // end for AD
            } // end for col
        } // end for row

        // set total score for this quiz
        if (setScores && TotLetters) {
            window.Score = Math.max(0, Math.floor((CorrectLetters - Penalties) * 100) / TotLetters);
        }
    } // end function

    this.getJCrossWord = function (a, r, c, goDown) {
        // a is a 2-dimensional array of letters, r is a row number, c is a column number
        var s = '';
        while (r<a.length && c<a[r].length && a[r][c].length) {
            s += a[r][c];
            if (goDown) {
                r++;
            } else {
                c++;
            }
        }
        return s;
    }

    this.init(1, sendallclicks, forceajax);
}
JCross.prototype = new hpQuizAttempt();
//-->