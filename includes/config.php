<?php 

if(!ini_get('date.timezone')) {
    ini_set("date.timezone", "America/Los_Angeles");
 }

 //LIVE
 $db = new mysqli('localhost', 'cb2hyrosusr10', '9oz9r2N8%', 'cb2hyros'); 

 define('HYROS_API_KEY', "5cf449c230aaeae18faeb3a45cdb9170f7bd07725ecc8552189f54dbb7597724");
