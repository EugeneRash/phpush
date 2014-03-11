<?PHP


require_once 'common.php';

$conf_path = conf_path();
if(!$conf_path) {
    die('Missing config.php');
} else {
    require_once $conf_path.'/config.php';
}


if(!function_exists("__autoload")){ 
	function __autoload($class_name){
		require_once('classes/class_'.$class_name.'.php');
	}
}

$db = new DbConnect($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$db->show_errors();


$apns = new APNS($db, $_GET, $config['certificate'], $config['log_file'], $config['passphrase'], $config['mode']);

