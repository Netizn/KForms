<?php
if(!function_exists('kforms_validate_email')) {
	function kforms_validate_email($email) {
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return array('valid' => false, 'error' => __('Please check your email address for typos', 'kforms'));
		} else {
			return true;
		}
	}
}
?>