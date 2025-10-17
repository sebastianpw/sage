<?php 
require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';
// footer.php - Drop-in Footer mit neutralen Textlinks + Cookie-Banner
?>
<hr style="border:0; border-top:1px solid #ccc; margin:40px 0 10px 0;">

<footer class="legal-footer" style="text-align:center; font-size:0.5em; color:#666; padding:5px 10px;">
    <!-- Minimalistischer Footer mit Rechts-Links -->
    <a href="/impressum.php">Impressum</a> |
    <a href="/datenschutz.php">Datenschutz</a>
</footer>

<!-- Cookie-Banner -->
<div id="cookieNotice" style="position: fixed; bottom: 0; left: 0; right: 0; background: #222; color: #fff; padding: 15px; text-align: center; font-size: 14px; display: none; z-index: 9999;">
</div>

<style>
/* Footer-Links neutralisieren, höhere Spezifität */
.legal-footer a {
    all: unset;           /* entfernt alle geerbten Styles */
    color: #666 !important;
    text-decoration: underline !important;
    font-size: 0.8em !important;
    cursor: pointer !important;
    margin: 0 5px;
}
.legal-footer a:hover {
    color: #000 !important;
}
.legal-footer {
    margin-bottom: 100px; /* Höhe des Banners */
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    var lang = $('html').attr('lang') || 'de';

    function showCookieBanner(lang){
        if(document.cookie.indexOf("cookieAccepted=true") === -1){
            if(lang === "en"){
                $("#cookieNotice").html('This website uses cookies to improve the user experience. <button id="acceptCookies">Accept</button>');
            } else {
                $("#cookieNotice").html('Diese Website verwendet Cookies, um das Nutzererlebnis zu verbessern. <button id="acceptCookies">Akzeptieren</button>');
            }
            $("#cookieNotice").fadeIn();
        }
    }

    showCookieBanner(lang);

    $(document).on("click","#acceptCookies",function(){
        document.cookie="cookieAccepted=true; path=/; max-age="+60*60*24*365;
        $("#cookieNotice").fadeOut();
    });
});
</script>
