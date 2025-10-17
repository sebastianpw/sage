<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Policy / Datenschutzerklärung</title>
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
    /* Cookie-Banner */
    #cookieNotice { 
      position: fixed; 
      bottom: 0; left: 0; right: 0; 
      background: #222; color: #fff; 
      padding: 15px; text-align: center; 
      font-size: 14px; display: none; z-index: 9999; 
    }
    #cookieNotice button { 
      margin-left: 10px; padding: 5px 10px; 
      background: #4CAF50; border: none; color: #fff; cursor: pointer; 
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
    <h1>Privacy Policy</h1>

    <p><strong>1. General information</strong><br>
    The protection of your personal data is very important to us. We treat your personal data confidentially 
    and in accordance with the statutory data protection regulations (GDPR, BDSG) as well as this privacy policy.</p>

    <p><strong>2. Collection and storage of personal data</strong><br>
    We only collect and use personal data (e.g. name, address, email address) to the extent necessary 
    to provide our services or to fulfill legal obligations.</p>

    <p><strong>3. Use of our website</strong><br>
    The use of our website is generally possible without providing personal data. 
    Insofar as personal data (e.g. name, email address) is collected, this is always done on a voluntary basis.</p>

    <p><strong>4. Contact</strong><br>
    If you contact us via email, the information you provide will be stored for the purpose of processing your request 
    and in case of follow-up questions.</p>

    <p><strong>5. Rights of the data subject</strong><br>
    You have the right at any time to obtain information about your stored personal data, 
    its origin and recipients, and the purpose of data processing, as well as the right to correct, block or delete this data.</p>

    <p><strong>6. SSL encryption</strong><br>
    For security reasons and to protect the transmission of confidential content, our website uses SSL encryption. 
    You can recognize an encrypted connection by the lock symbol in your browser and the “https://” prefix in the address line.</p>

    <p><strong>7. Changes to this privacy policy</strong><br>
    We reserve the right to amend this privacy policy in order to adapt it to current legal requirements 
    or to changes in our services.</p>
  </div>

  <!-- German -->
  <div class="lang de">
    <h1>Datenschutzerklärung</h1>

    <p><strong>1. Allgemeine Hinweise</strong><br>
    Der Schutz Ihrer persönlichen Daten ist uns ein besonderes Anliegen. 
    Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend den gesetzlichen Datenschutzvorschriften 
    (DSGVO, BDSG) sowie dieser Datenschutzerklärung.</p>

    <p><strong>2. Erhebung und Speicherung personenbezogener Daten</strong><br>
    Wir erheben und verwenden personenbezogene Daten (z. B. Name, Anschrift, E-Mail-Adresse) 
    nur, soweit dies zur Bereitstellung unserer Dienste oder zur Erfüllung gesetzlicher Pflichten erforderlich ist.</p>

    <p><strong>3. Nutzung unserer Website</strong><br>
    Die Nutzung unserer Website ist in der Regel ohne Angabe personenbezogener Daten möglich. 
    Soweit personenbezogene Daten (z. B. Name, E-Mail-Adresse) erhoben werden, erfolgt dies stets auf freiwilliger Basis.</p>

    <p><strong>4. Kontaktaufnahme</strong><br>
    Wenn Sie uns per E-Mail kontaktieren, werden die von Ihnen gemachten Angaben 
    zwecks Bearbeitung der Anfrage sowie für den Fall von Anschlussfragen gespeichert.</p>

    <p><strong>5. Rechte der betroffenen Person</strong><br>
    Sie haben jederzeit das Recht auf unentgeltliche Auskunft über Ihre gespeicherten personenbezogenen Daten, 
    deren Herkunft und Empfänger sowie den Zweck der Datenverarbeitung. 
    Außerdem haben Sie ein Recht auf Berichtigung, Sperrung oder Löschung dieser Daten.</p>

    <p><strong>6. SSL-Verschlüsselung</strong><br>
    Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte 
    eine SSL-Verschlüsselung. Eine verschlüsselte Verbindung erkennen Sie am Schloss-Symbol in der Browserzeile 
    sowie am „https://“-Präfix.</p>

    <p><strong>7. Änderung dieser Datenschutzerklärung</strong><br>
    Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen rechtlichen Anforderungen entspricht 
    oder um Änderungen unserer Leistungen umzusetzen.</p>
  </div>

  <!-- Cookie-Banner -->
  <div id="cookieNotice"></div>

</body>
</html>
