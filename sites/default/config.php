<?php

$config = array
(
    'db_host'                => 'localhost',
    'db_name'                => 'pushdb',
    'db_user'                => 'root',
    'db_pass'                => '',
    'certificate'            => 'cert/ck.pem',     //path for development or production certificate
    'passphrase'             => '',                //password for certificate.
    'log_file'               => 'logfilename.log', //path for log file. Need to set permissions - CHMOD 777
    'mode'                   => 'production'       //Configuration mode - sandbox or production
);
