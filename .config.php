<?php

$config = [
    "TARGET_DIR"=>"./objetivo",
    "SOURCE_DIR"=>"./prueba",
    "SERVER"=>"192.168.20.50 - siturin-pruebas.turismo.gob.ec",
    "MAIL_TO_NOTIFY"=>"luis.salazar@turismo.gob.ec"
];

$excludes = ['vendor'];

define("CONFIG", $config);
define("EXCLUDES", $excludes);
?>

