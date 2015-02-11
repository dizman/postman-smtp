<?php
if (! interface_exists ( "PostmanAuthenticationManager" )) {
	interface PostmanAuthenticationManager {
		
		const POSTMAN_AUTHORIZATION_IN_PROGRESS = 'request_oauth_permission';
		const FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE = 60;
		
		public function isAccessTokenExpired();
		public function refreshToken();
		public function requestVerificationCode();
		public function handleAuthorizatinGrantCode();
	}
}