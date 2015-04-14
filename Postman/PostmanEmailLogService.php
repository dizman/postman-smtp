<?php
include_once (ABSPATH . 'wp-admin/includes/plugin.php');

if (! class_exists ( 'PostmanEmailLog' )) {
	class PostmanEmailLog {
		public $body;
		public $subject;
		public $message;
		public $success;
		public $sender;
		public $recipients;
		public $sessionTranscript;
	}
}

if (! class_exists ( 'PostmanEmailLogFactory' )) {
	class PostmanEmailLogFactory {
		public static function createLogFromPostmanMailEngine(PostmanMailEngine $engine, $transcript) {
			$log = new PostmanEmailLog ();
			$log->subject = $mail->getSubject ();
			$log->body = '';
			if ($mail->getBodyText ())
				$log->body .= $mail->getBodyText ()->getRawContent ();
			if ($mail->getBodyHtml ())
				$log->body .= $mail->getBodyHtml ()->getRawContent ();
			$log->message = 'Ok';
			$log->sender = $mail->getFrom ();
			$log->recipients = $mail->getRecipients ();
			$log->success = true;
			return $log;
		}
		public static function createSuccessLog(PostmanMessage $message, $transcript) {
			return PostmanEmailLogFactory::createLog ( $message, $transcript, 'Ok', true );
		}
		public static function createFailureLog(PostmanMessage $message, $transcript, $statusMessage) {
			return PostmanEmailLogFactory::createLog ( $message, $transcript, $statusMessage, false );
		}
		private static function createLog(PostmanMessage $message, $transcript, $statusMessage, $success) {
			$log = new PostmanEmailLog ();
			$log->subject = $message->getSubject ();
			$log->body = $message->getBody ();
			$log->message = $statusMessage;
			$log->sender = $message->getSender ()->getEmail ();
			$log->recipients = PostmanEmailLogFactory::flattenEmails ( $message->getToRecipients () );
			$log->success = $statusMessage;
			$log->sessionTranscript = 'n/a';
			if (! empty ( $transcript )) {
				$log->sessionTranscript = $transcript;
			}
			return $log;
		}
		private static function flattenEmails(array $addresses) {
			$flat = '';
			$count = 0;
			foreach ( $addresses as $address ) {
				if ($count > 0) {
					$flat .= ', ';
				}
				$flat .= $address->getEmail ();
				$count ++;
			}
			return $flat;
		}
	}
}

if (! class_exists ( 'PostmanEmailLogService' )) {
	class PostmanEmailLogService {
		
		// constants
		const POSTMAN_CUSTOM_POST_TYPE_SLUG = 'postman_sent_mail';
		
		// member variables
		private $logger;
		private $inst;
		
		/**
		 * Constructor
		 */
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			add_action ( 'init', array (
					$this,
					'init' 
			) );
			add_filter ( 'dashboard_glance_items', array (
					$this,
					'custom_glance_items' 
			), 10, 1 );
		}
		
		/**
		 * singleton instance
		 */
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanEmailLogService ();
			}
			return $inst;
		}
		
		/**
		 */
		public function init() {
			$this->create_post_type ();
			$this->createTaxonomy ();
		}
		private function truncateEmailLog() {
			$args = array (
					'post_type' => 'self::POSTMAN_CUSTOM_POST_TYPE_SLUG' 
			);
			$FORCE_DELETE = true;
			// wp_delete_post( $postid, $FORCE_DELETE );
		}
		
		/**
		 * Create a custom post type
		 * Callback function - must be public scope
		 *
		 * register_post_type should only be invoked through the 'init' action.
		 * It will not work if called before 'init', and aspects of the newly
		 * created or modified post type will work incorrectly if called later.
		 *
		 * https://codex.wordpress.org/Function_Reference/register_post_type
		 */
		function create_post_type() {
			register_post_type ( self::POSTMAN_CUSTOM_POST_TYPE_SLUG, array (
					'labels' => array (
							'name' => _x ( 'Email Log', 'A List of Emails that have been sent', 'postman-smtp' ) 
					),
					'show_in_nav_menus' => true,
					'show_ui' => true,
					'has_archive' => true 
			) );
			
			$this->logger->debug ( 'Created custom post type \'postman_email\'' );
		}
		
		/**
		 * From http://wordpress.stackexchange.com/questions/8569/wp-insert-post-php-function-and-custom-fields
		 */
		public function writeToEmailLog(PostmanEmailLog $log) {
			// Create post object
			// from http://stackoverflow.com/questions/20444042/wordpress-how-to-sanitize-multi-line-text-from-a-textarea-without-losing-line
			$sanitizedBody = implode ( PHP_EOL, array_map ( 'sanitize_text_field', explode ( PHP_EOL, $log->body ) ) );
			$my_post = array (
					'post_type' => self::POSTMAN_CUSTOM_POST_TYPE_SLUG,
					'post_title' => wp_slash ( sanitize_text_field ( $log->subject ) ),
					'post_content' => wp_slash ( $sanitizedBody ),
					'post_excerpt' => wp_slash ( sanitize_text_field ( $log->message ) ),
					'post_status' => 'private' 
			);
			
			// Insert the post into the database
			$post_id = wp_insert_post ( $my_post );
			$this->logger->debug ( sprintf ( 'Saved message #%s to the database', $post_id ) );
			$this->logger->debug ( $log );
			
			// meta
			update_post_meta ( $post_id, 'from_header', wp_slash ( sanitize_text_field ( $log->sender ) ) );
			update_post_meta ( $post_id, 'to_header', wp_slash ( sanitize_text_field ( $log->recipients ) ) );
			update_post_meta ( $post_id, 'status', sanitize_text_field ( $log->success ) );
			// from http://stackoverflow.com/questions/20444042/wordpress-how-to-sanitize-multi-line-text-from-a-textarea-without-losing-line
			$sanitizedTranscript = implode ( PHP_EOL, array_map ( 'sanitize_text_field', explode ( PHP_EOL, $log->sessionTranscript ) ) );
			update_post_meta ( $post_id, 'session_transcript', wp_slash ( $sanitizedTranscript ) );
		}
		
		/**
		 */
		private function createTaxonomy() {
			// create taxonomy
			$args = array ();
			register_taxonomy ( 'postman_sent_mail_category', 'success', $args );
			register_taxonomy ( 'postman_sent_mail_category', 'fail', $args );
		}
		
		/**
		 * From http://www.hughlashbrooke.com/2014/02/wordpress-add-items-glance-widget/
		 *
		 * @param unknown $items        	
		 * @return string
		 */
		function custom_glance_items($items = array()) {
			$post_types = array (
					self::POSTMAN_CUSTOM_POST_TYPE_SLUG 
			);
			
			foreach ( $post_types as $type ) {
				
				if (! post_type_exists ( $type ))
					continue;
				
				$num_posts = wp_count_posts ( $type );
				
				if ($num_posts) {
					
					$published = intval ( $num_posts->publish );
					$post_type = get_post_type_object ( $type );
					
					$text = _n ( '%s ' . $post_type->labels->singular_name, '%s ' . $post_type->labels->name, $published, 'your_textdomain' );
					$text = sprintf ( $text, number_format_i18n ( $published ) );
					
					if (current_user_can ( $post_type->cap->edit_posts )) {
						$items [] = sprintf ( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $type, $text ) . "\n";
					} else {
						$items [] = sprintf ( '<span class="%1$s-count">%2$s</span>', $type, $text ) . "\n";
					}
				}
			}
			
			return $items;
		}
	}
}