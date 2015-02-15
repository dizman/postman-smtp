<?php
if (! class_exists ( 'PostmanOAuthScribeFactory' )) {
	class PostmanOAuthScribeFactory {
		private function __construct() {
		}
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanOAuthScribeFactory ();
			}
			return $inst;
		}
		public function createPostmanOAuthScribe($hostname) {
			if (endsWith ( $hostname, 'gmail.com' )) {
				return new PostmanGoogleOAuthScribe ();
			} else if (endsWith ( $hostname, 'live.com' )) {
				return new PostmanMicrosoftOAuthScribe ();
			} else if (endsWith ( $hostname, 'yahoo.com' )) {
				return new PostmanYahooOAuthScribe ();
			} else {
				return new PostmanNonOAuthScribe ();
			}
		}
	}
}
if (! interface_exists ( 'PostmanOAuthHelper' )) {
	interface PostmanOAuthHelper {
		public function isOauthHost();
		public function isGoogle();
		public function isMicrosoft();
		public function isYahoo();
		public function getCallbackUrl();
		public function getClientIdLabel();
		public function getClientSecretLabel();
		public function getCallbackUrlLabel();
		public function getOwnerName();
		public function getServiceName();
		public function getApplicationDescription();
		public function getApplicationPortalName();
		public function getApplicationPortalUrl();
		public function getOAuthPort();
		public function getEncryptionType();
	}
}
if (! class_exists ( 'PostmanAbstractOAuthHelper' )) {
	
	/**
	 *
	 * @author jasonhendriks
	 */
	abstract class PostmanAbstractOAuthHelper implements PostmanOAuthHelper {
		const OAUTH_HELP_TEXT = '<p id="wizard_oauth2_help"><span class="normal">Open the <a href="%1$s" target="_new">%2$s</a>,
						create %7$s using the %5$s below, and enter the %3$s and %4$s.
						See <a href="https://wordpress.org/plugins/postman-smtp/faq/" target="_new">
						How do I get a %6$s %3$s?</a> in the F.A.Q. for help.</span></p>';
		public function getOAuthHelp() {
			return sprintf ( self::OAUTH_HELP_TEXT, $this->getApplicationPortalUrl (), $this->getApplicationPortalName (), $this->getClientIdLabel (), $this->getClientSecretLabel (), $this->getCallbackUrlLabel (), $this->getOwnerName (), $this->getApplicationDescription () );
		}
		function isOauthHost() {
			return $this->isGoogle () || $this->isMicrosoft () || $this->isYahoo ();
		}
		function isGoogle() {
			return false;
		}
		function isMicrosoft() {
			return false;
		}
		function isYahoo() {
			return false;
		}
	}
}
if (! class_exists ( 'PostmanGoogleOAuthScribe' )) {
	class PostmanGoogleOAuthScribe extends PostmanAbstractOAuthHelper {
		public function isGoogle() {
			return true;
		}
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' ) . '?page=postman';
		}
		public function getClientIdLabel() {
			return __ ( 'Client ID' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Client Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Authorized Redirect URI' );
		}
		public function getOwnerName() {
			return __ ( "Google" );
		}
		public function getServiceName() {
			return __ ( "Gmail" );
		}
		public function getApplicationDescription() {
			return __ ( "a Client ID for web application" );
		}
		public function getApplicationPortalName() {
			return __ ( 'Google Developer Console' );
		}
		public function getApplicationPortalUrl() {
			return 'https://console.developers.google.com/';
		}
		public function getOAuthPort() {
			return 465;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_SSL;
		}
	}
}
if (! class_exists ( 'PostmanMicrosoftOAuthScribe' )) {
	class PostmanMicrosoftOAuthScribe extends PostmanAbstractOAuthHelper {
		public function isMicrosoft() {
			return true;
		}
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' );
		}
		public function getClientIdLabel() {
			return __ ( 'Client ID' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Client secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Redirect URL' );
		}
		public function getOwnerName() {
			return __ ( "Microsoft" );
		}
		public function getServiceName() {
			return __ ( "Outlook.com" );
		}
		public function getApplicationDescription() {
			return __ ( "an Application" );
		}
		public function getApplicationPortalName() {
			return __ ( 'Microsoft Developer Center' );
		}
		public function getApplicationPortalUrl() {
			return 'https://account.live.com/developers/applications/index';
		}
		public function getOAuthPort() {
			return 587;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_TLS;
		}
	}
}
if (! class_exists ( 'PostmanYahooOAuthScribe' )) {
	class PostmanYahooOAuthScribe extends PostmanAbstractOAuthHelper {
		public function isYahoo() {
			return true;
		}
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' ) . '?page=postman';
		}
		public function getClientIdLabel() {
			return __ ( 'Consumer Key' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Consumer Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Home Page URL' );
		}
		public function getOwnerName() {
			return __ ( "Yahoo" );
		}
		public function getServiceName() {
			return __ ( "Yahoo Mail" );
		}
		public function getApplicationDescription() {
			return __ ( "an Application" );
		}
		public function getApplicationPortalName() {
			return __ ( 'Yahoo Developer Network' );
		}
		public function getApplicationPortalUrl() {
			return 'https://developer.apps.yahoo.com/projects';
		}
		public function getOAuthPort() {
			return 465;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_SSL;
		}
	}
}
if (! class_exists ( 'PostmanNonOAuthScribe' )) {
	class PostmanNonOAuthScribe extends PostmanAbstractOAuthHelper {
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' ) . '?page=postman';
		}
		public function getClientIdLabel() {
			return __ ( 'Consumer Key' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Consumer Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Home Page URL' );
		}
		public function getOwnerName() {
			return __ ( "Yahoo" );
		}
		public function getServiceName() {
			return __ ( "Yahoo Mail" );
		}
		public function getApplicationDescription() {
			return __ ( "an Application" );
		}
		public function getApplicationPortalName() {
			return __ ( 'Yahoo Developer Network' );
		}
		public function getApplicationPortalUrl() {
			return 'https://developer.apps.yahoo.com/projects';
		}
		public function getOAuthPort() {
			return 465;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_SSL;
		}
	}
}