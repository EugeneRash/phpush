<?php

function conf_path() {
    static $conf = '';

    if ($conf) {
        return $conf;
    }

    $confdir = 'sites';
    $uri = explode('/', $_SERVER['SCRIPT_NAME'] ? $_SERVER['SCRIPT_NAME'] : $_SERVER['SCRIPT_FILENAME']);
    $server = explode('.', implode('.', array_reverse(explode(':', rtrim($_SERVER['HTTP_HOST'], '.')))));
    for ($i = count($uri) - 1; $i > 0; $i--) {
        for ($j = count($server); $j > 0; $j--) {
            $dir = implode('.', array_slice($server, -$j)) . implode('.', array_slice($uri, 0, $i));
            if (file_exists("$confdir/$dir/config.php")) {
                $conf = "$confdir/$dir";
                return $conf;
            }
        }
    }

    $conf = "$confdir/default";
    return $conf;

}