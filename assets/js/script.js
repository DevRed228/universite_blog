

// Cookie Consent Logic
function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}
function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}
document.addEventListener('DOMContentLoaded', function() {
    var banner = document.getElementById('cookieConsentBanner');
    if (banner && !getCookie('cookie_consent')) {
        banner.style.display = 'block';
        document.getElementById('acceptCookies').onclick = function() {
            setCookie('cookie_consent', 'accepted', 365);
            banner.style.display = 'none';
        };
        document.getElementById('declineCookies').onclick = function() {
            setCookie('cookie_consent', 'declined', 365);
            banner.style.display = 'none';
        };
    }
});


