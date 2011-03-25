
$("code.demo-code").each(function(index) {
  var $example = $(this),
      $demo = $('div.code-demo').eq(index);

  var source = $example.html()
        .replace(/<\/?a.*?>/ig, "")
        .replace(/<\/?strong.*?>/ig, "")
        .replace(/&lt;/g, "<").replace(/&gt;/g, ">")
        .replace(/&amp;/g, "&");

  var iframe = document.createElement("iframe");

  iframe.src= "../index-blank.html";
  iframe.width = "100%";
  iframe.height = $demo.attr("rel") || "125";
  iframe.style.border = "none";
  iframe.style.background = "#fff";
  $demo.html(iframe);

  var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document) || iframe.document || null;

  if (doc == null) {
    return true;
  }
  source = source
        .replace(/<script>([^<])/g,"<script>window.onload = (function(){\ntry{$1")
        .replace(/([^>])<\/sc/g,  '$1\n}catch(e){}});</sc')
        .replace("</head>", "<style>html,body{border:0; margin:0; padding:0;}</style></head>");

  doc.open();
  doc.write( source );
  doc.close();

});
