<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Impressum / Legal Notice</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 1.5em;
      line-height: 1.6;
      color: #222;
      background: #fff;
      padding-bottom: 80px; /* Platz für Cookie-Banner */
    }
    nav {
      position: sticky;
      top: 0;
      background: #f8f8f8;
      border-bottom: 1px solid #ddd;
      padding: 0.5em;
      display: flex;
      gap: 1em;
      flex-wrap: wrap;
      align-items: center;
    }
    nav a {
      text-decoration: none;
      color: #0066cc;
      font-weight: bold;
      font-size: 0.95em;
    }
    nav a:hover { text-decoration: underline; }
    h1 { font-size: 1.5em; margin-top: 1em; }
    p { margin: 0.4em 0; }
    #lang-switch {
      margin-left: auto;
      border: 1px solid #ccc;
      background: #fff;
      padding: 0.2em 0.6em;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9em;
    }
    #cookieNotice { 
      position: fixed; bottom: 0; left: 0; right: 0; 
      background: #222; color: #fff; padding: 15px; text-align: center; 
      font-size: 14px; display: none; z-index: 9999; 
    }
    #cookieNotice button { 
      margin-left: 10px; padding: 5px 10px; background: #4CAF50; border: none; color: #fff; cursor: pointer; 
    }
  </style>
  
  
  <link rel="stylesheet" href="showcase.css">  
  
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(function(){
      // Sprachumschalter
      $("#lang-switch").click(function(){
        $(".lang").hide();
        if($(this).data("lang") === "en"){
          $(".de").show();
          $(this).data("lang","de").text("English");
          showCookieBanner("de");
        } else {
          $(".en").show();
          $(this).data("lang","en").text("Deutsch");
          showCookieBanner("en");
        }
      });
      $(".de").hide();
      $("#lang-switch").data("lang","en").text("Deutsch");

      // Cookie-Banner Funktion
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

      // Initial Banner
      showCookieBanner("en");

      // Akzeptieren-Button
      $(document).on("click", "#acceptCookies", function(){
        document.cookie = "cookieAccepted=true; path=/; max-age=" + 60*60*24*365;
        $("#cookieNotice").fadeOut();
      });

      // ==== EMAIL OBFUSCATION ====
      function initEmails(){
        // Define your email parts
        var user = "quenicorns.nuster.flarcus.in.space";
        var domain = "example.ai";
        var email = user + "@" + domain;
        var mailto = "mailto:" + email;

        // Replace email placeholders
        $(".email-link").each(function(){
          $(this).attr("href", mailto).text(email).attr("aria-label", "Email: " + email);
        });

        // Optional: Copy-to-clipboard functionality
        $(".copy-email").each(function(){
          $(this).show().on("click", function(){
            if(navigator.clipboard && navigator.clipboard.writeText){
              navigator.clipboard.writeText(email).then(function(){
                alert("Email copied to clipboard");
              }, function(){
                alert("Copy failed — please select & copy manually.");
              });
            }
          });
        });
      }

      // Initialize email links on page load
      initEmails();
    });
  </script>
</head>
<body>

  <nav>
    <a href="showcase.php">Home</a>
    <a href="impressum.php">Impressum</a>
    <a href="datenschutz.php">Datenschutz</a>
    <button id="lang-switch"></button>
  </nav>

  <!-- English -->
  <div class="lang en">
    <h1>Legal Notice</h1>

    <p><strong>1. Information according to §5 TMG</strong><br>
    Any Distance<br>
    Main Steet 999<br>
    12345 Eazy Luv<br>
    Droidia</p>

    <p><strong>2. Contact</strong><br>
    Email: <a href="#" class="email-link">Loading...</a>
    <button class="copy-email" style="display:none">Copy email</button><br>
    Telephone: </p>

    <p><strong>3. Disclaimer</strong><br>
    Despite careful content control, we assume no liability for the content of external links. 
    The operators of linked pages are solely responsible for their content.</p>

    <p><strong>4. Copyright</strong><br>
    The content and works on this website created by the site operator are subject to German copyright law. 
    Any reproduction, editing, distribution, or any form of commercialization of such material beyond the scope of copyright law requires the prior written consent of the author.</p>
  </div>

  <!-- German -->
  <div class="lang de">
    <h1>Impressum</h1>

    <p><strong>1. Angaben gemäß §5 TMG</strong><br>
    Any Distance<br>
    Main Steet 999<br>
    12345 Eazy Luv<br>
    Droidia</p>

    <p><strong>2. Kontakt</strong><br>
    E-Mail: <a href="#" class="email-link">Wird geladen...</a>
    <button class="copy-email" style="display:none">E-Mail kopieren</button><br>
    Telefon: </p>

    <p><strong>3. Haftungsausschluss</strong><br>
    Trotz sorgfältiger inhaltlicher Kontrolle übernehmen wir keine Haftung für die Inhalte externer Links. 
    Für den Inhalt der verlinkten Seiten sind ausschließlich deren Betreiber verantwortlich.</p>

    <p><strong>4. Urheberrecht</strong><br>
    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. 
    Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechts bedürfen der schriftlichen Zustimmung des jeweiligen Autors.</p>
  </div>

  <!-- Cookie-Banner -->
  <div id="cookieNotice"></div>

</body>
</html>
