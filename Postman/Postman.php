<?php

// setup the main entry point
if (! class_exists ( 'Postman' )) {
	
	/**
	 * This is the setup for Postman.
	 *
	 * Execution always begins here:
	 * - the wp_mail hook is created, if Postman is properly configured
	 * - the admin screen hooks are created, if the current user is an admin
	 * - the ajax endpoints are created, if the current user is an admin
	 *
	 * @author jasonhendriks
	 * @copyright Jan 16, 2015
	 */
	class Postman {
		private $logger;
		private $messageHandler;
		private $wpMailBinder;
		private $pluginData;
		private $rootPluginFilenameAndPath;
		
		/**
		 * The constructor
		 *
		 * @param unknown $rootPluginFilenameAndPath
		 *        	- the __FILE__ of the caller
		 */
		public function __construct($rootPluginFilenameAndPath, $version) {
			assert ( ! empty ( $rootPluginFilenameAndPath ) );
			assert ( ! empty ( $version ) );
			$this->rootPluginFilenameAndPath = $rootPluginFilenameAndPath;
			
			// load the dependencies
			require_once 'PostmanOptions.php';
			require_once 'PostmanLogger.php';
			require_once 'PostmanUtils.php';
			require_once 'postman-common-functions.php';
			require_once 'Postman-Mail/PostmanTransportRegistry.php';
			require_once 'Postman-Mail/PostmanSmtpModuleTransport.php';
			require_once 'Postman-Mail/PostmanGmailApiModuleTransport.php';
			require_once 'PostmanOAuthToken.php';
			require_once 'PostmanWpMailBinder.php';
			require_once 'PostmanConfigTextHelper.php';
			require_once 'PostmanActivationHandler.php';
				
			// get plugin metadata - alternative to get_plugin_data
			$this->pluginData = array (
					'name' => __ ( 'Postman SMTP', 'postman-smtp' ),
					'version' => $version 
			);
			
			// register the plugin metadata filter (part of the Postman API)
			add_filter ( 'postman_get_plugin_metadata', array (
					$this,
					'getPluginMetaData' 
			) );
			
			// create an instance of the logger
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->logger->debug ( sprintf ( '%1$s v%2$s starting', $this->pluginData ['name'], $this->pluginData ['version'] ) );
			
			// load the text domain
			$this->loadTextDomain ( $rootPluginFilenameAndPath );
			
			// register the email transports
			$this->registerTransports ();
			
			// store an instance of the WpMailBinder
			$this->wpMailBinder = PostmanWpMailBinder::getInstance ();
			
			// bind to wp_mail - this has to happen before the "init" action
			// this design allows other plugins to register a Postman transport and call bind()
			// bind may be called more than once
			$this->wpMailBinder->bind ();
			
			// register activation handler on the activation event
			// must be called in constructor
			register_activation_hook ( $rootPluginFilenameAndPath, array (
					new PostmanActivationHandler (),
					'activate_postman' 
			) );
			
			// register the shortcode handler on the add_shortcode event
			add_shortcode ( 'postman-version', array (
					$this,
					'version_shortcode' 
			) );
			
			// register the admin functions on the plugins_loaded hook,
			// because we need the ability to test for user capabilities
			// and that function is loaded in pluggables.php
			add_action ( 'plugins_loaded', array (
					$this,
					'setup_admin' 
			) );
			
			// register the check for configuration errors on the wp_loaded hook,
			// because we want it to run after the OAuth Grant Code check on the init hook
			add_action ( 'wp_loaded', array (
					$this,
					'check_for_configuration_errors' 
			) );
		}
		
		/**
		 * If the user is an administrator, creates the Admin screens
		 */
		public function setup_admin() {
			// check if this is an admin user on the admin screen
			if (PostmanUtils::isAdminOnAdminScreen ()) {
				
				$this->logger->debug ( 'Admin start-up sequence' );
				
				$options = PostmanOptions::getInstance ();
				$authToken = PostmanOAuthToken::getInstance ();
				$rootPluginFilenameAndPath = $this->rootPluginFilenameAndPath;
				
				// load the dependencies
				require_once 'PostmanMessageHandler.php';
				require_once 'PostmanAdminController.php';
				require_once 'Postman-Controller/PostmanDashboardWidgetController.php';
				require_once 'Postman-Controller/PostmanAdminPointer.php';
				require_once 'Postman-Email-Log/PostmanEmailLogController.php';
				
				// load email log service, in case another plugin (eg. WordPress importer)
				// is doing something related to custom post types
				require_once 'Postman-Email-Log/PostmanEmailLogService.php';
				
				// create and store an instance of the MessageHandler
				$this->messageHandler = new PostmanMessageHandler ();
				
				// create the Admin Controllers
				new PostmanDashboardWidgetController ( $rootPluginFilenameAndPath, $options, $authToken, $this->wpMailBinder );
				new PostmanAdminController ( $rootPluginFilenameAndPath, $options, $authToken, $this->messageHandler, $this->wpMailBinder );
				new PostmanEmailLogController ( $rootPluginFilenameAndPath );
				new PostmanAdminPointer ( $rootPluginFilenameAndPath );
				
				// register the Postman signature (only if we're on a postman admin screen) on the in_admin_footer event
				if (PostmanUtils::isCurrentPagePostmanAdmin ()) {
					add_action ( 'in_admin_footer', array (
							$this,
							'print_signature' 
					) );
				}
				
				// getting an instance reference performs lazy initialization
				// lazy initialization registers the custom post type for all callers
				PostmanEmailLogService::getInstance ();
			}
		}
		
		/**
		 * Check for configuration errors and displays messages to the user
		 */
		public function check_for_configuration_errors() {
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanOAuthToken::getInstance ();
			
			// did Postman fail binding to wp_mail()?
			if ($this->wpMailBinder->isUnboundDueToException ()) {
				// this message gets printed on ANY WordPress admin page, as it's a pretty fatal error that
				// may occur just by activating a new plugin
				if (PostmanUtils::isAdminOnAdminScreen ()) {
					// I noticed the wpMandrill and SendGrid plugins have the exact same error message here
					// I've adopted their error message as well, for shits and giggles .... :D
					$this->messageHandler->addError ( __ ( 'Postman: wp_mail has been declared by another plugin or theme, so you won\'t be able to use Postman until the conflict is resolved.', 'postman-smtp' ) );
					// $this->messageHandler->addError ( __ ( 'Error: Postman is properly configured, but the current theme or another plugin is preventing service.', 'postman-smtp' ) );
				}
			} else if (! $this->wpMailBinder->isBound ()) {
				$transport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
				$scribe = PostmanConfigTextHelperFactory::createScribe ( $options->getHostname (), $transport );
				$readyToSend = PostmanTransportRegistry::getInstance ()->isPostmanReadyToSendEmail ( $options, $authToken );
				
				$virgin = $options->isNew ();
				if (! $readyToSend && ! $virgin) {
					// if the configuration is broken, and the user has started to configure the plugin
					// show this error message
					$message = PostmanTransportRegistry::getInstance ()->getCurrentTransport ()->getMisconfigurationMessage ( $scribe, $options, $authToken );
					if ($message) {
						// output the warning message
						$this->logger->warn ( 'Transport has a configuration problem: ' . $message );
						// on pages that are Postman admin pages only, show this error message
						if (PostmanUtils::isAdmin () && PostmanUtils::isCurrentPagePostmanAdmin ()) {
							$this->messageHandler->addError ( $message );
						}
					}
				}
				
				// on pages that are NOT Postman admin pages only, show this error message
				if (! PostmanUtils::isCurrentPagePostmanAdmin () && ! $readyToSend) {
					// on pages that are *NOT* Postman admin pages only....
					// if the configuration is broken
					// show this error message
					add_action ( 'admin_notices', Array (
							$this,
							'display_configuration_required_warning' 
					) );
				}
			}
		}
		
		/**
		 * Returns the plugin version number and name
		 * Part of the Postman API
		 *
		 * @return multitype:unknown NULL
		 */
		public function getPluginMetaData() {
			// get plugin metadata
			return $this->pluginData;
		}
		
		/**
		 * This is the general message that Postman requires configuration, to warn users who think
		 * the plugin is ready-to-go as soon as it is activated.
		 * This message only goes away once
		 * the plugin is configured.
		 */
		public function display_configuration_required_warning() {
			$this->logger->debug ( 'Displaying configuration required warning' );
			$message = sprintf ( __ ( 'WordPress is still handling email delivery, <em>not</em> Postman.', 'postman-smtp' ) );
			$message .= ' ';
			/* translators: where %s is the URL to the Postman Setup page */
			$message .= sprintf ( __ ( '<a href="%s">Configure</a> the plugin.', 'postman-smtp' ), PostmanUtils::getSettingsPageUrl () );
			$this->messageHandler->printMessage ( $message, PostmanMessageHandler::WARNING_CLASS );
		}
		
		/**
		 * Register the email transports.
		 *
		 * The Gmail API used to be a separate plugin which was registered when that plugin
		 * was loaded. But now both the SMTP and Gmail API transports are registered here.
		 *
		 * @param unknown $pluginData        	
		 */
		private function registerTransports() {
			PostmanTransportRegistry::getInstance ()->registerTransport ( new PostmanSmtpModuleTransport () );
			PostmanTransportRegistry::getInstance ()->registerTransport ( new PostmanGmailApiModuleTransport () );
		}
		
		/**
		 * Print the Postman signature on the bottom of the page
		 *
		 * http://striderweb.com/nerdaphernalia/2008/06/give-your-wordpress-plugin-credit/
		 */
		function print_signature() {
			printf ( '<a href="https://wordpress.org/plugins/postman-smtp/">%s</a> %s<br/>', $this->pluginData ['name'], $this->pluginData ['version'] );
		}
		
		/**
		 * Loads the appropriate language file
		 */
		private function loadTextDomain($rootPluginFilenameAndPath) {
			$textDomain = 'postman-smtp';
			$langDir = basename ( dirname ( $rootPluginFilenameAndPath ) ) . '/Postman/languages/';
			$success = load_plugin_textdomain ( $textDomain, false, $langDir );
		}
		
		/**
		 * Shortcode to return the current plugin version.
		 *
		 * From http://code.garyjones.co.uk/get-wordpress-plugin-version/
		 *
		 * @return string Plugin version
		 */
		function version_shortcode() {
			return $this->pluginData ['version'];
		}
	}
}
