<?php


class APNS {



	private $mode = '';

	private $db;

	private $apnsData;

	private $showErrors = true;

    private $jsonOutput = true;

	private $logErrors = true;

	private $logPath = '/usr/local/apns/apns.log';

	private $logMaxSize = 1048576; // max log size before it is truncated

    private $badgeMaxValue = 999;

	private $certificate = '/usr/local/apns/apns.pem';

	private $ssl = 'ssl://gateway.push.apple.com:2195';

	private $feedback = 'ssl://feedback.push.apple.com:2196';

	private $sandboxSsl = 'ssl://gateway.sandbox.push.apple.com:2195';

	private $sandboxFeedback = 'ssl://feedback.sandbox.push.apple.com:2196';




	function __construct($db, $args=NULL, $certificate=NULL, $logPath=NULL, $passphrase = NULL, $mode = NULL) {

		if(!empty($certificate) && file_exists($certificate))
		{
			$this->certificate = $certificate;
		}


		$this->db = $db;
        $this->mode = $mode;
		$this->checkSetup();
		$this->apnsData = array(
			'production'=>array(
				'certificate'=>$this->certificate,
				'ssl'=>$this->ssl,
				'feedback'=>$this->feedback,
                'passphrase'=>$passphrase
			),
			'sandbox'=>array(
				'certificate'=>$this->certificate,
				'ssl'=>$this->sandboxSsl,
				'feedback'=>$this->sandboxFeedback,
                'passphrase'=>$passphrase
            )
		);

		if ($logPath !== null) {
			$this->logPath = $logPath;
		}

		if(!empty($args)) {
			switch($args['q']){

                case "register_token":
                    $this->_registerToken(
                        $args['uid'],
                        $args['token']
                    );
                    break;

                case "send_message":
                    $this->_sendMessage(
                        $args['uid'],
                        $args['message'],
                        isset($args['badge'])?(int)$args['badge']:null
                    );
                    break;


                case "cleanup_tokens":
                    $this->_customCheckFeedback();
                    break;

                case "delete_token":
                    $this->_deleteToken(
                        $args['token']
                    );
                    break;

				default:
                    $output = array('status' => 'error', 'message' => 'No APNS Task Provided');
                    $this->_printOutput($output);
					break;
			}
		} else {
            $output = array('status' => 'error', 'message' => 'Empty Request');
            $this->_printOutput($output);
        }
	}



	private function checkSetup(){

		if(!file_exists($this->certificate)) $this->_triggerError('Missing Certificate.', E_USER_ERROR);

		clearstatcache();
		$certificateMod = substr(sprintf('%o', fileperms($this->certificate)), -3);

		if($certificateMod>644)  $this->_triggerError('Production Certificate is insecure! Suggest chmod 644.');
	}



    private function truncate_string($string, $max_length) {
        $max_length = max($max_length, 0);

        if (mb_strlen($string, 'UTF-8') <= $max_length) {
            return $string;
        }

        $dots = mb_substr('...', 0, $max_length, 'UTF-8');
        $max_length -= mb_strlen($dots, 'UTF-8');
        $max_length = max($max_length, 0);

        $string = mb_substr($string, 0, $max_length, 'UTF-8') . $dots;

        return $string;
    }



    private function _registerToken($uid, $devicetoken){

        if(strlen($uid)==0) $this->_triggerError('UID must not be blank.', E_USER_ERROR);
        else if(strlen($devicetoken)!=64) $this->_triggerError('Device Token must be 64 characters in length.', E_USER_ERROR);

        $devicetoken = $this->db->prepare($devicetoken);
        $uid = $this->db->prepare($uid);

        $this->db->query("SET NAMES 'utf8';");
        $sql = "INSERT IGNORE INTO `user_devices` (`uid`, `devicetoken`, `status`) VALUES ('{$uid}','{$devicetoken}','active');";
        $this->db->query($sql);

        $output = array('status' => 'success', 'message' => 'Device has been successfully registered');
        $this->_printOutput($output);

    }

    private function _deleteToken($devicetoken){

        if(strlen($devicetoken)!=64) $this->_triggerError('Device Token must be 64 characters in length.', E_USER_ERROR);

        $devicetoken = $this->db->prepare($devicetoken);

        $this->db->query("SET NAMES 'utf8';");
        $sql = "DELETE FROM `user_devices` WHERE `devicetoken`='{$devicetoken}';";
        $this->db->query($sql);

        $output = array('status' => 'success', 'message' => 'Token has been successfully removed');
        $this->_printOutput($output);

    }



    private function _sendMessage($uid, $message, $badge) {

        if(strlen($uid)==0) $this->_triggerError('UID must not be blank.', E_USER_ERROR);
        else if(strlen($message)==0) $this->_triggerError('Message can not be empty.', E_USER_ERROR);

        $tokens = array();

        $uid = $this->db->prepare($uid);
        $sql = "SELECT `devicetoken` FROM `user_devices` WHERE `uid`= $uid AND `status` = 'active'";

        $result = $this->db->query($sql);
        while($res = $result->fetch_array(MYSQLI_ASSOC)) {
            $tokens[] = $res['devicetoken'];
        }


        if(count($tokens) > 0) {

            foreach($tokens as $token) {
                $this->_sendMessageToDevice($token, $message, $badge);
            }

            $output = array('status' => 'success', 'message' => 'Message successfully delivered');
            $this->_printOutput($output);

        } else {
            $this->_triggerError('Provided UID not found in Database', E_USER_ERROR);
        }

    }


    private function _sendMessageToDevice($deviceToken, $message, $badge) {


        $development = $this->mode;

        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $this->apnsData[$development]['certificate']);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->apnsData[$development]['passphrase']);

        $fp = stream_socket_client($this->apnsData[$development]['ssl'], $error, $errorString, 100, (STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT), $ctx);

        if (!$fp) {
            $this->_triggerError("Failed to connect to APNS: {$error} {$errorString}.");
        }


        $body['aps']['sound'] = 'default';
        if(!is_null($badge) && $badge >= 0) {
            $body['aps']['badge'] = ($badge > $this->badgeMaxValue)?$this->badgeMaxValue:$badge;
        }

        if(mb_detect_encoding($message) != 'UTF-8') {
            $message = mb_convert_encoding($message, 'UTF-8' );
        }

        $body['aps']['alert'] = '';
        $body['aps']['alert'] = $this->truncate_string($message, 128 - mb_strlen(json_encode($body), 'UTF-8'));

        $payload = preg_replace_callback(
            '/\\\u([0-9a-fA-F]{4})/', create_function('$_m', 'return mb_convert_encoding("&#" . intval($_m[1], 16) . ";", "UTF-8", "HTML-ENTITIES");'),
            json_encode($body)
        );


        $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

        $result = fwrite($fp, $msg, strlen($msg));
        if (!$result) {
            $this->_triggerError("Message not delivered.", E_USER_ERROR);
        }

        fclose($fp);

    }


    private function _customUnregisterDevice($token){
        $sql = "UPDATE `user_devices`
				SET `status`='inactive'
				WHERE `devicetoken`='{$token}'
				LIMIT 1;";
        $this->db->query($sql);
    }


    private function _customCheckFeedback(){


        $development = $this->mode;
        $ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $this->apnsData[$development]['certificate']);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->apnsData[$development]['passphrase']);
        stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
		$fp = stream_socket_client($this->apnsData[$development]['feedback'], $error,$errorString, 100, (STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT), $ctx);

        if(!$fp) $this->_triggerError("Failed to connect to device: {$error} {$errorString}.");
		while ($devcon = fread($fp, 38)){


			$arr = unpack("H*", $devcon);
			$rawhex = trim(implode("", $arr));
			$token = substr($rawhex, 12, 64);
			if(!empty($token)){
				$this->_customUnregisterDevice($token);
                $output = array('status' => 'success', 'message' => "Unregistering Device Token: {$token}.");
                $this->_printOutput($output);
			}
		}

		fclose($fp);

        $output = array('status' => 'success', 'message' => "Cleanup completed");
        $this->_printOutput($output);

    }



	function _triggerError($error, $type=E_USER_NOTICE){

        if($this->jsonOutput) {

            $output = array('status' => 'error', 'message' => $error);
            $this->_printOutput($output);

        } elseif($this->showErrors) {
            trigger_error($error, $type);
        }

        $backtrace = debug_backtrace();
		$backtrace = array_reverse($backtrace);
		$error .= "\n";
		$i=1;
		foreach($backtrace as $errorcode){
			$file = ($errorcode['file']!='') ? "-> File: ".basename($errorcode['file'])." (line ".$errorcode['line'].")":"";
			$error .= "\n\t".$i.") ".$errorcode['class']."::".$errorcode['function']." {$file}";
			$i++;
		}
		$error .= "\n\n";
		if($this->logErrors && file_exists($this->logPath)){
			if(filesize($this->logPath) > $this->logMaxSize) $fh = fopen($this->logPath, 'w');
			else $fh = fopen($this->logPath, 'a');
			fwrite($fh, $error);
			fclose($fh);
		}

        exit;
  	}


    function _printOutput($output) {
        header('Content-Type: application/json');
        print json_encode($output);
    }


}
