<?php
/*

Creator : Niko Chiu
Email : nikosychiu@gmail.com

1. Upload all files to Opencart root folder
2. Require packages from composer and add autoload file
    composer require phpmailer/phpmailer
	composer require google/apiclient
3. Refresh modifications in Opencart backend
4. Create oauth client credentials from google api console
5. Enter the client id and secret in the Opencart backend and save (Setting > Setting > Edit store > Gmail Api tap)
6. Get the authorization code from the authorization url
7. Enter the authorization code in the Opencart backend and save (Setting > Setting > Edit store > Gmail Api tap)

*/

class GmailApi{
    private $client;
    private $APPLICATION_NAME = 'GMAIL API';
    private $CREDENTIALS_PATH = DIR_SYSTEM.".credentials/gmail-php-quickstart.json";
    private $CLIENT_SECRET_PATH = DIR_SYSTEM."client_secret.json";
    private $credentialsPath;
	private $SCOPES;
    private $GMAIL_API_AUTH_CODE = "";

	/**
	* @param String $clientID oauth api client id
	* @param String $secret oauth api secret
	* @param String $redirectUri the redirect uri
	*/
    public function __construct($clientID = "", $secret = "", $redirectUri = ""){	
		// If modifying these scopes, delete your previously saved credentials
        $this->SCOPES = implode(' ', [
			Google_Service_Gmail::GMAIL_SEND
		]);

		// Load previously authorized credentials from a file.
        $this->credentialsPath = $this->expandHomeDirectory($this->CREDENTIALS_PATH);

		$this->makeClient($clientID, $secret, $redirectUri);

		$this->refreshAccessToken();
    }

	/**
	* Construct a client
	* @param String $clientID oauth api client id
	* @param String $secret oauth api secret
	* @param String $redirectUri the redirect uri
	*/
	public function makeClient($clientID, $secret, $redirectUri){
		$client = new Google_Client();
		$client->setApplicationName($this->APPLICATION_NAME);
		$client->setScopes($this->SCOPES);

		if(file_exists($this->CLIENT_SECRET_PATH)){
			$client->setAuthConfig($this->CLIENT_SECRET_PATH);
		}else{
			$client->setClientId($clientID);
			$client->setClientSecret($secret);
			if(empty($redirectUri))
				$client->setRedirectUri("urn:ietf:wg:oauth:2.0:oob");
			else
				$client->setRedirectUri($redirectUri);
		}

		$client->setAccessType('offline');

		$this->client = $client;
	}

	/**
	* Get authorization url
	* @return string a url to get the authorization code
	*/
    public function getAuthCodeUrl(){
		if(!empty($this->GMAIL_API_AUTH_CODE))
			return "";

        // Request authorization from the user.
        return $this->client->createAuthUrl();
    }

	/**
	* Check the credentials file exists
	* @return boolean
	*/
	public function checkCredentials(){
		return file_exists($this->credentialsPath);
	}

	/**
	* Create the credentials file
	*/
    public function makeCredentials(){
		if(empty($this->GMAIL_API_AUTH_CODE))
			throw new Exception("No authorization code.");

        if(!$this->checkCredentials()){
            // Exchange authorization code for an access token.
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($this->GMAIL_API_AUTH_CODE);

            // Store the credentials to disk.
            if(!file_exists(dirname($this->credentialsPath))) {
                mkdir(dirname($this->credentialsPath), 0700, true);
            }

            file_put_contents($this->credentialsPath, json_encode($accessToken));
        }
    }

	/**
	* Refresh the access token
	*/
	public function refreshAccessToken(){
		if ($this->checkCredentials()) {
			$accessToken = json_decode(file_get_contents($this->credentialsPath), true);
			
			$this->client->setAccessToken($accessToken);

			// Refresh the token if it's expired.
			if ($this->client->isAccessTokenExpired()) {
				$this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
				file_put_contents($this->CREDENTIALS_PATH, json_encode($this->client->getAccessToken()));
			}
        }
	}

	/**
	* Expands the home directory alias '~' to the full path.
	* @param string $path the path to expand.
	* @return string the expanded path.
	*/
	public function expandHomeDirectory($path) {
		$homeDirectory = getenv('HOME');
		if (empty($homeDirectory)) {
			$homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
		}
		return str_replace('~', realpath($homeDirectory), $path);
	}

	/**
	* Send the email out
	* @param String $fromName sender name
	* @param String $from sender email
	* @param String $to receiver email
	* @param String $subject email subject
	* @param String $msg email message
	*/
	public function sendMail($fromName, $from, $to, $subject, $msg){
        if(!$this->checkCredentials())
			throw new Exception("No credentials exists");

		$mail = new PHPMailer();
		$mail->CharSet = "UTF-8";
		$mail->From = $from;
		$mail->FromName = $fromName;
		$mail->AddAddress($to);
		$mail->AddReplyTo($from,$fromName);
		$mail->Subject = $subject;
		$mail->Body    = $msg;
		$mail->isHTML(true);
		$mail->preSend();
		$mime = $mail->getSentMIMEMessage();

		$data = base64_encode($mime);
		$data = str_replace(array('+','/','='),array('-','_',''),$data); // url safe

		$m = new Google_Service_Gmail_Message();
		$m->setRaw($data);

		$service = new Google_Service_Gmail($this->client);
		$service->users_messages->send('me', $m);

        return true;
	}

	/**
	* Check opencart user login
	* @return Boolean
	*/
	public function isUserLogged(){
		return isset($_SESSION["user_id"]);
	}

	/**
	* Set authorization code
	* 	@param String $authCode
	*/
	public function setAuthCode($authCode){
		$this->GMAIL_API_AUTH_CODE = $authCode;
	}

	/**
	* Check if the authorization working
	* @return Boolean
	*/
	public function isWorking(){
		return !$this->client->isAccessTokenExpired();
	}
}