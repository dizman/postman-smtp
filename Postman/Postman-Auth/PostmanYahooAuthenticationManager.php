<?php
if (! class_exists ( "PostmanYahooAuthenticationManager" )) {
	
	require_once 'PostmanAbstractAuthenticationManager.php';
	require_once 'PostmanStateIdMissingException.php';
	
	/**
	 * Super-simple.
	 * I should have started with Yahoo.
	 *
	 * https://developer.yahoo.com/oauth2/guide/
	 * Get a Client ID at https://developer.apps.yahoo.com/projects
	 *
	 * @author jasonhendriks
	 */
	class PostmanYahooAuthenticationManager extends PostmanAbstractAuthenticationManager implements PostmanAuthenticationManager {
		
		// This endpoint is the target of the initial request. It handles active session lookup, authenticating the user, and user consent.
		const AUTHORIZATION_URL = 'https://api.login.yahoo.com/oauth2/request_auth';
		const GET_TOKEN_URL = 'https://api.login.yahoo.com/oauth2/get_token';
		
		// The SESSION key for the OAuth Transaction Id
		const AUTH_TEMP_ID = 'OAUTH_TEMP_ID';
		
		/**
		 * Constructor
		 *
		 * Get a Client ID from https://account.live.com/developers/applications/index
		 */
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken, $callbackUri) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $callbackUri ) );
			$logger = new PostmanLogger ( get_class ( $this ) );
			parent::__construct ( $logger, $clientId, $clientSecret, $authorizationToken, $callbackUri );
		}
		
		/**
		 * The authorization sequence begins when your application redirects a browser to a Google URL;
		 * the URL includes query parameters that indicate the type of access being requested.
		 *
		 * As in other scenarios, Google handles user authentication, session selection, and user consent.
		 * The result is an authorization code, which Google returns to your application in a query string.
		 *
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::requestVerificationCode()
		 */
		public function requestVerificationCode() {
			
			// Create a state token to prevent request forgery.
			// Store it in the session for later validation.
			$state = md5 ( rand () );
			$_SESSION [self::AUTH_TEMP_ID] = $state;
			
			$params = array (
					'response_type' => 'code',
					'redirect_uri' => urlencode ( $this->getCallbackUri () ),
					'client_id' => $this->getClientId (),
					'state' => $state,
					'language' => 'en-us' 
			);
			
			build_query ( $params );
			$authUrl = $this->getAuthorizationUrl () . '?' . build_query ( $params );
			
			$this->getLogger ()->debug ( 'Requesting verification code from Yahoo' );
			$_SESSION [PostmanAdminController::POSTMAN_ACTION] = self::POSTMAN_AUTHORIZATION_IN_PROGRESS;
			postmanRedirect ( $authUrl );
		}
		
		/**
		 * After receiving the authorization code, your application can exchange the code
		 * (along with a client ID and client secret) for an access token and, in some cases,
		 * a refresh token.
		 *
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::processAuthorizationGrantCode()
		 */
		public function processAuthorizationGrantCode() {
			if (isset ( $_GET ['code'] )) {
				$code = $_GET ['code'];
				$this->getLogger ()->debug ( sprintf ( 'Found authorization code %s in request header', $code ) );
				if (isset ( $_GET ['state'] ) && $_GET ['state'] == $_SESSION [self::AUTH_TEMP_ID]) {
					unset ( $_SESSION [self::AUTH_TEMP_ID] );
					$this->getLogger ()->debug ( 'Found valid state in request header' );
				} else {
					$this->getLogger ()->error ( 'The grant code from Yahoo had no accompanying state and may be a forgery' );
					throw new PostmanStateIdMissingException ();
				}
				$this->requestAuthorizationToken ( $this->getTokenUrl (), $this->getCallbackUri (), $code );
				return true;
			} else {
				$this->getLogger ()->debug ( 'Expected code in the request header but found none - user probably denied request' );
				return false;
			}
		}
		public function getAuthorizationUrl() {
			return self::AUTHORIZATION_URL;
		}
		public function getTokenUrl() {
			return self::GET_TOKEN_URL;
		}
	}
}