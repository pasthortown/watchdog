<?php

$config = [
    "TARGET_DIR"=>"/home/siturin_publish",
    "SOURCE_DIR"=>"/opt/security/siturin_publish_source",
    "SERVER"=>"192.168.20.50 - siturin-pruebas.turismo.gob.ec",
    "MAIL_TO_NOTIFY"=>"luis.salazar@turismo.gob.ec"
];

$excludes = ['vendor'];

define("CONFIG", $config);
define("EXCLUDES", $excludes);
?>

