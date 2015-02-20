<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Mail
 * @subpackage Transport
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 *
 * @see Zend_Mime
 */
// require_once 'Zend/Mime.php';

/**
 *
 * @see Zend_Mail_Protocol_Smtp
 */
// require_once 'Zend/Mail/Protocol/Smtp.php';

/**
 *
 * @see Zend_Mail_Transport_Abstract
 */
// require_once 'Zend/Mail/Transport/Abstract.php';

/**
 * SMTP connection object
 *
 * Loads an instance of Zend_Mail_Protocol_Smtp and forwards smtp transactions
 *
 * @category Zend
 * @package Zend_Mail
 * @subpackage Transport
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */
if (! class_exists ( 'PostmanZendMailTransportGmailApi' )) {
	class PostmanZendMailTransportGmailApi extends Zend_Mail_Transport_Abstract {
		const SERVICE_OPTION = 'service';
		const SENDER_EMAIL_OPTION = 'sender_email';
		
		private $logger;
		
		/**
		 * EOL character string used by transport
		 *
		 * @var string
		 * @access public
		 */
		public $EOL = "\n";
		
		/**
		 * Remote smtp hostname or i.p.
		 *
		 * @var string
		 */
		protected $_host;
		
		/**
		 * Port number
		 *
		 * @var integer|null
		 */
		protected $_port;
		
		/**
		 * Local client hostname or i.p.
		 *
		 * @var string
		 */
		protected $_name = 'localhost';
		
		/**
		 * Authentication type OPTIONAL
		 *
		 * @var string
		 */
		protected $_auth;
		
		/**
		 * Config options for authentication
		 *
		 * @var array
		 */
		protected $_config;
		
		/**
		 * Instance of Zend_Mail_Protocol_Smtp
		 *
		 * @var Zend_Mail_Protocol_Smtp
		 */
		protected $_connection;
		
		/**
		 * Constructor.
		 *
		 * @param string $host
		 *        	OPTIONAL (Default: 127.0.0.1)
		 * @param array|null $config
		 *        	OPTIONAL (Default: null)
		 * @return void
		 *
		 * @todo Someone please make this compatible
		 *       with the SendMail transport class.
		 */
		public function __construct($host = '127.0.0.1', Array $config = array()) {
			if (isset ( $config ['name'] )) {
				$this->_name = $config ['name'];
			}
			if (isset ( $config ['port'] )) {
				$this->_port = $config ['port'];
			}
			if (isset ( $config ['auth'] )) {
				$this->_auth = $config ['auth'];
			}
			
			$this->_host = $host;
			$this->_config = $config;
			$this->logger = new PostmanLogger('PostmanZendMailTransportGmailApi');
		}
		
		/**
		 * Class destructor to ensure all open connections are closed
		 *
		 * @return void
		 */
		public function __destruct() {
			if ($this->_connection instanceof Zend_Mail_Protocol_Smtp) {
				try {
					$this->_connection->quit ();
				} catch ( Zend_Mail_Protocol_Exception $e ) {
					// ignore
				}
				$this->_connection->disconnect ();
			}
		}
		
		/**
		 * Sets the connection protocol instance
		 *
		 * @param Zend_Mail_Protocol_Abstract $client        	
		 *
		 * @return void
		 */
		public function setConnection(Zend_Mail_Protocol_Abstract $connection) {
			$this->_connection = $connection;
		}
		
		/**
		 * Gets the connection protocol instance
		 *
		 * @return Zend_Mail_Protocol|null
		 */
		public function getConnection() {
			return $this->_connection;
		}
		
		/**
		 * Send an email via the Gmail API
		 * 
		 * Uses URI https://www.googleapis.com
		 *
		 *
		 * @return void
		 * @todo Rename this to sendMail, it's a public method...
		 */
		public function _sendMail() {
			
			// Prepare the message in message/rfc822
			$message = $this->header . Zend_Mime::LINEEND . $this->body;
			$this->logger->debug ( 'message: ' . $message );
			
			// The message needs to be encoded in Base64URL
			$mime = rtrim ( strtr ( base64_encode ( $message ), '+/', '-_' ), '=' );
			$msg = new Google_Service_Gmail_Message ();
			$msg->setRaw ( $mime );
			$service = $this->_config [self::SERVICE_OPTION];
			$service->users_messages->send ( 'me', $msg );
			
		}
		
		/**
		 * Format and fix headers
		 *
		 * Some SMTP servers do not strip BCC headers. Most clients do it themselves as do we.
		 *
		 * @access protected
		 * @param array $headers        	
		 * @return void
		 * @throws Zend_Transport_Exception
		 */
		protected function _prepareHeaders($headers) {
			if (! $this->_mail) {
				/**
				 *
				 * @see Zend_Mail_Transport_Exception
				 */
				// require_once 'Zend/Mail/Transport/Exception.php';
				throw new Zend_Mail_Transport_Exception ( '_prepareHeaders requires a registered Zend_Mail object' );
			}
			
			unset ( $headers ['Bcc'] );
			
			// Prepare headers
			parent::_prepareHeaders ( $headers );
		}
	}
}