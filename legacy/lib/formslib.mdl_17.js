function lockoptionsall(formid) {
    var form = document.forms[formid];
    var dependons = eval(formid+'items');
    var tolock = Array();
    for (var dependon in dependons) {
        var master = form[dependon];
        for (var condition in dependons[dependon]) {
            for (var value in dependons[dependon][condition]) {
                var lock;
                switch (condition) {
                  case 'notchecked':
                      lock = !master.checked; break;
                  case 'checked':
                      lock = master.checked; break;
                  case 'noitemselected':
                      lock = master.selectedIndex==-1; break;
                  case 'eq':
                      lock = master.value==value; break;
                  default:
                      lock = master.value!=value; break;
                }
                for (var ei in dependons[dependon][condition][value]) {
                    var eltolock = dependons[dependon][condition][value][ei];
                    if (tolock[eltolock] != null){
                        tolock[eltolock] =
                                lock || tolock[eltolock];
                    } else {
                        tolock[eltolock] = lock;
                    }
                }
            }
        }
    }
    for (var el in tolock){
        if (form[el]) {
            form[el].disabled = tolock[el];
        }
    }
    return true;
}
