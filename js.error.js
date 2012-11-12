<!--
function window_onerror(msg, url, line) {
    if (typeof(window.errorimage)=='undefined') {
        window.errorimage = new Image();
        window.errorimage.id = 'errorimage';
        window.errorimage.count = 0;
    }
    if (window.errorimage.count < 5) {
        window.errorimage.count++;
        var src = 'jserror.php';
        var amp = '?';
        if (typeof(msg)=='string' && msg) {
            src += amp + 'msg=' + escape(msg);
            amp = '&';
        }
        if (typeof(url)=='string' && url) {
            src += amp + 'url=' + escape(url);
            amp = '&';
        }
        if (typeof(line)=='number') {
            src += amp + 'line=' + escape(line);
            amp = '&';
        }
        if (amp=='&') {
            window.errorimage.src = src;
        }
    }
    return false; // display standard js error in browser
    //return true; // do NOT display standard js error in browser
}
window.onerror = window_onerror;

function image_onerror() {
    if (this.id && this.id=='errorimage') {
        // do nothing
    } else {
        window_onerror('Image did not load: ' + this.src, window.location);
    }
}
if (Image.prototype) {
    Image.prototype.onerror = image_onerror;
}
//-->