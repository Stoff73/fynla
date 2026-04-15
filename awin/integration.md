For 1st-party cookie tracking and one-click integration with proven partners, add the MasterTag to all pages on your website.
Copy the MasterTag code snippet below
Paste it on all pages of your website, except for pages that display/process sensitive payment information.
(The MasterTag is a JavaScript library containing all functions required for our tracking solution and should be unconditionally appended to every page on your site. This should be done as late as possible, for example by placing the HTML script element just above the closing body tag and declaring it as defer="defer")




<!-- Master Tag add just before the closing </body> tag -->
                    <script src="https://www.dwin1.com/126105.js" type="text/javascript" defer="defer"></script>


<!-- Fall-back Conversion Pixel - Mandatory -->
<img src="https://www.awin1.com/sread.img?tt=ns&tv=2&merchant=126105&amount={{order_subtotal}}&cr={{currency_code}}&ref={{order_ref}}&parts={{commission_group}}:{{sale_amount}}&vc={{voucher_code}}&ch=aw&customeracquisition={{customer_acquisition}}" border="0" width="0" height="0">

<!-- Conversion Tag - Mandatory --> 
<script type="text/javascript">
//<![CDATA[ /*** Do not change ***/
var AWIN = AWIN || {};
AWIN.Tracking = AWIN.Tracking || {};
AWIN.Tracking.Sale = {};
/*** Set your transaction parameters ***/
AWIN.Tracking.Sale.amount = "{{order_subtotal}}";
AWIN.Tracking.Sale.orderRef = "{{order_ref}}";
AWIN.Tracking.Sale.parts = "{{commission_group}}:{{sale_amount}}";
AWIN.Tracking.Sale.voucher = "{{voucher_code}}";
AWIN.Tracking.Sale.currency = "{{currency_code}}";
AWIN.Tracking.Sale.channel = "aw";
AWIN.Tracking.Sale.customerAcquisition = "{{customer_acquisition}}";
//]]>
</script>




<?php
    function setAwc() {
        if (!empty($_GET['awc'])) {
            setcookie("awc",$_GET['awc'],time()+ 60 * 60 * 24 * 365,"/", "example.com", true, true);   
     }
 }
?>





<!-- Server to Server Tracking -->
https://www.awin1.com/sread.php?tt=ss&tv=2&merchant=126105&amount={{order_subtotal}}&ch=aw&parts={{commission_group}}:{{sale_amount}}&vc={{voucher_code}}&cr={{currency_code}}&ref={{order_ref}}&cks={{awc}}&customeracquisition={{customer_acquisition}}
