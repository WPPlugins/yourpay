var url = jQuery("#ypdiframe").attr("src");
function awaitApproval() {
  try
  {
    var domain = document.domain;
    var iframeDomain = jQuery('#ypiframe').contents().get(0).location.host;
    if(domain == iframeDomain) {
       clearInterval(awaitApproval);
       jQuery('#ypiframe').contents().find("html").html("");
       window.top.location.href = jQuery('#ypiframe').contents().get(0).location.href;
    }
    console.log(Date.now() + "DATE");
  }
catch(err)
  {
    setTimeout(
    function() 
    {
    console.log(Date.now() + "DATE ERR");        
      awaitApproval();
    }, 1000);
  }
}
function prepareFrame() {
    jQuery("#ypiframe").attr("src", url);
    awaitApproval();    
}

// Implementations of 3.05

if (typeof jQuery === "undefined") {
  loadjQuery("//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js");
}

function loadjQuery(url) {
  var script_tag = document.createElement('script');
  script_tag.setAttribute("src", url)
  script_tag.onreadystatechange = function () { // Same thing but for IE
    if (this.readyState == 'complete' || this.readyState == 'loaded') callback();
  }
  document.getElementsByTagName("head")[0].appendChild(script_tag);
  console.log("Load jQuery");
}
jQuery(document).ready(function( $ ) {

var transactiontoken = "";
var checktimer;
    function submityourpayform(clsbtn) {
            $.ajax({
                type: 'POST',
                url: 'https://webservice.yourpay.dk/httpd.php?json=1',
                crossDomain: true,
                data: { "function": "TransactionTokenGenerate" },
                dataType: 'json',
                success: function(responseData, textStatus, jqXHR) {
                    transactiontoken = responseData.Token;
                    respondtoken(transactiontoken);
                    $("body").prepend("<div class='yourpayoverlay'></div>").addClass('cover');
                    $(".yourpayoverlay").addClass('cover');
                    $(".yourpayoverlay").before("<div class='yourpay iframeview'></div>");
                    $(".yourpay.iframeview").before("<div class='yourpay closebutton'><img src='" + clsbtn + "'></div>");
                    $(".yourpay.iframeview").append("<iframe src='https://payments.yourpay.se/betalingsvindue_cardtype.php?paymenttoken=" + transactiontoken + "&" + $(".yourpay_form").serialize() + "'>");
                    $(".yourpay.closebutton").css("margin-left", (($(".yourpay.iframeview").width()/2)-24));
                    
                    $(".yourpay.closebutton").click(function()  {
                        $(".yourpay.closebutton").remove();
                        $(".yourpay.iframeview").remove();
                        $(".yourpayoverlay.cover").remove();
                        clearTimeout(checktimer);
                    });
                    
                },
                error: function (responseData, textStatus, errorThrown) {
                    alert('POST failed.');
                }
            });               
    }
$( window ).resize(function() {
  $(".yourpay.closebutton").css("margin-left", (($(".yourpay.iframeview").width()/2)-24));
});    
function respondtoken(Token){

            $.ajax({
                type: 'POST',
                url: 'https://webservice.yourpay.dk/httpd.php?json=1',
                crossDomain: true,
                data: { "function": "TransactionTokenLookup", "token": Token },
                dataType: 'json',
                success: function(responseData, textStatus, jqXHR) {
                    var url = $(".yourpay_form.overflow input[name='accepturl']").val() + "&";
                    $.each(responseData, function(k, v) {
                        url += k + "=" + v + "&";
                    });               
                    url.trim(url, "&");
                    if(responseData.hasOwnProperty('TransID')){
                        window.location.replace(url);
                    } else{
                        checktimer = setTimeout(function(){
                             respondtoken(Token);
                        },5000); // Adjust the timeout value as you like
                        
                    }
                },
                error: function (responseData, textStatus, errorThrown) {
                    console.log('POST failed.');
                }
            });               
}    
    submityourpayform('https://payments.yourpay.se/img/close-48.png');
    $(".startpayment").click( function() {
        submityourpayform('https://payments.yourpay.se/img/close-48.png');
    });

    jQuery(".yourpayclose").click(function() {
        
        jQuery(".yourpay.outer").css("display","none");
        jQuery(".yourpay.grayedout").css("display","none");
        
    });
    
});


