<?hh

header('Error-Redirect: true');

// Generic Error Page - Will redirect to /index.php?page=error if that page is not throwing an HTTP error itself
echo
  <<<END
    <!doctype html>
    <html lang="en">
      <head>
      <title>An Error Occured</title>
      <style>
        body { text-align: center; padding: 150px; }
        h1 { font-size: 50px; }
        body { font: 20px Helvetica, sans-serif; color: #21b4ba; background-color: #13242b }
        page { display: block; text-align: left; width: 650px; margin: 0 auto; }
        a { color: #ead44d; text-decoration: none; }
        a:hover { color: #ead44d; text-decoration: none; }
      </style>
      <meta charset="UTF-8">
      <script type="text/javascript" src="/static/dist/js/app.js"></script>
      </head>
      <body>
        <page>
          <h1>An Error Occured!</h1>
          <div>
            <p>Please check your request and try again.</p>
            <p>If the problem persists, you should contact an admin.</p>
          </div>
          <script type="text/javascript">
            jQuery.get('/index.php?page=error', function(data, response, xhr) {
              if ((xhr.status === 200) && (xhr.getResponseHeader('Error-Redirect') === null)) {
                console.log("Redirect to '/index.php?page=error'");
                window.location="/index.php?page=error";
              }
            });
          </script>
        </page>
      </body>
    </html>
END
;
