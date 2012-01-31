#!/php -q
<?php

require_once("lib/phpws/websocket.server.php");

/**
 * Simple upload Web Socket Server class that saves the files in the temp directory
 * This class uses the phpwebsocket server (https://github.com/vincentdieltiens/phpws)
 */
class UploadSocketServer implements IWebSocketServerObserver {
	// Show Debug
	protected $debug = true;
	
	// All the uploads
	protected $uploads = array();

	/**
	 * Construct a new server at a given host and a given port
	 * @param $host : the host of the server
	 * @param $port : the port of the server
	 */ 
	public function __construct($host, $port){
		$this->server = new WebSocketServer($host, $port, 'superdupersecretkey');
		$this->server->addObserver($this);
	}

	/**
	 * Handler called when a new client connects to this server
	 * @param $user : the connected client
	 */
	public function onConnect(IWebSocketConnection $user){
		$this->say("[DEMO] {$user->getId()} connected");
	}

	/**
	 * Handler called when a new message is received by a client.
	 * If the message is a STOR message, creates the file in the temp directory, or if it is a
	 * DATA message, add the received data to the file.
	 *
	 * @param $user : the client that has sent a message
	 * @param $msg : the message sent
	 */
	public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
		if( preg_match('#^STOR:(.*?)$#', $msg->getData(), $matches) > 0 ) {
			$data = json_decode($matches[1]);
			$this->initializeUpload($user, $data);
		} else {
			// First decode the data received as a base64 string.
			$data = base64_decode($msg->getData());
			$this->addData($user, $data);
		}
	}

	/**
	 * Handler called when a client disconnect from this server
	 * @param $user : the client that has disconnected
	 */
	public function onDisconnect(IWebSocketConnection $user) {
		$this->say("[DEMO] {$user->getId()} disconnected");
		$this->finishUpload($user);
	}

	
	public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg){
		$this->say("[DEMO] Admin Message received!");
	}
	
	public function say($msg){
		echo "$msg \r\n";
	}

	public function run() {
		$this->server->run();
	}
	
	/**
	 * Initializes the upload for a given client
	 * @param $user : the client that is uploading a file
	 * @param $infos : the informations of the file
	 */
	public function initializeUpload($user, $infos)
	{
		// Add the upload to the list
		$this->uploads[$user->getId()] = new FileUpload($infos->filename, $infos->size);
		
		// Sends a response to the client
		$response = array(
			'type' => 'STOR',
			'message' => 'Upload initialized. Wait for data',
			'code' => 200
		);
		$user->sendString(json_encode($response));
	}
	
	/**
	 * Adds data to the file for a given client
	 * @param $user : the client
	 * @param $data : the data to add to the file
	 */
	public function addData($user, $data)
	{
		if( isset($this->uploads[$user->getId()]) ) {
			$this->uploads[$user->getId()]->addData($data);
			
			// Sends a response to the client
			$user->sendString(json_encode(array(
				'type' => 'DATA',
				'code' => 200,
				'bytesRead' => mb_strlen($data)
			)));
		}
	}
	
	/**
	 * Finishes the upload of a given client
	 * @param $user : the client
	 */
	public function finishUpload($user) {
		if( isset($this->uploads[$user->getId()]) ) {
			$this->uploads[$user->getId()]->close();
			unset($this->uploads[$user->getId()]);
		}
	}
}

/**
 * Class that represents the upload of a file
 */
class FileUpload
{
	// The filename of the uploaded file
	private $filename;
	
	// The size of the uploaded file
	private $size;
	
	// The ressource to the file
	private $fp;
	
	/**
	 * Constructs a new File Upload
	 * @param $filename : the name of the file
	 * @param $size : the size of the file
	 */
	public function __construct($filename, $size) {
		$this->setFilename($filename);
		$this->setSize($size);
		
		$this->fp = fopen('/tmp/'.$filename, 'w+');
	}
	
	/**
	 * Updates the name of the file
	 * @param $filename : the new name
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
	}
	
	/**
	 * Updates the size of the file
	 * @param $size : the new size of the file
	 */
	public function setSize($size) {
		$this->size = $size;
	}
	
	/**
	 * Adds data to the file
	 * @param $data : the data to add to the file
	 */
	public function addData($data) {
		fwrite($this->fp, $data);
	}
	
	/**
	 * Close the file
	 */
	public function close() {
		fclose($this->fp);
	}
}

// Creates the server and run it
$server = new UploadSocketServer("tcp://ip:port", "ip");
$server->run();

?>