<?php
/*
Plugin Name: (K)Forms
Plugin URI: http://www.netizn.co/
Description: Wordpress form builder
Version: 0.1
Author: Martin Petts
Author URI: http://www.martinpetts.com/

Copyright 2011 Martin Petts
*/
if ( !defined('ABSPATH') )
	die('-1');

define('KFORMS_VERSION', '0.1');
define('KFORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ));

if(!defined('KFORMS_POST_KEY')) {
	define('KFORMS_POST_KEY', 'kforms');
}

global $kforms_data, $kforms_errors, $kforms_core_fields, $kforms_status_msg, $kforms_success;

$kforms_data = array();
$kforms_errors = array();
$kforms_status_msg = array();
$kforms_success = array();
$kforms_core_fields = array('name' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'address_1' => '', 'address_2' => '', 'city' => '', 'postcode' => '', 'country' => '', 'phone_1' => '', 'phone_2' => '', 'message' => '', 'email_ok' => 0, 'post_ok' => 0, 'phone_ok' => 0);

if(!function_exists('pr')) {
	function pr($arr) {
		echo '<pre>';
		print_r($arr);
		echo '</pre>';
	}
}

if(!function_exists('kforms_install')) {
	function kforms_install() {
		global $wpdb;
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";

		$table_name = $wpdb->prefix . "kforms_submissions";
		$sql = "CREATE TABLE " . $table_name . " (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) DEFAULT '' NOT NULL,
			first_name VARCHAR(255) DEFAULT '' NOT NULL,
			last_name VARCHAR(255) DEFAULT '' NOT NULL,
			email VARCHAR(255) DEFAULT '' NOT NULL,
			address_1 VARCHAR(255) DEFAULT '' NOT NULL,
			address_2 VARCHAR(255) DEFAULT '' NOT NULL,
			city VARCHAR(255) DEFAULT '' NOT NULL,
			postcode VARCHAR(255) DEFAULT '' NOT NULL,
			country VARCHAR(255) DEFAULT '' NOT NULL,
			phone_1 VARCHAR(255) DEFAULT '' NOT NULL,
			phone_2 VARCHAR(255) DEFAULT '' NOT NULL,
			message LONGTEXT NOT NULL,
			email_ok TINYINT(1) DEFAULT '0' NOT NULL,
			post_ok TINYINT(1) DEFAULT '0' NOT NULL,
			phone_ok TINYINT(1) DEFAULT '0' NOT NULL,
			submission_date datetime NOT NULL default '0000-00-00 00:00:00',
			form_id VARCHAR(255) DEFAULT 'contact_form' NOT NULL,
			source VARCHAR(255) DEFAULT '' NOT NULL,
			referrer VARCHAR(255) DEFAULT '' NOT NULL,
			ip VARCHAR(64) DEFAULT '' NOT NULL,
			PRIMARY KEY  (ID)
		) $charset_collate;";
		dbDelta($sql);
		
		$table_name = $wpdb->prefix . "kforms_submissionmeta";
		$sql = "CREATE TABLE " . $table_name . " (
			smeta_id bigint(20) unsigned NOT NULL auto_increment,
			kforms_submission_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (smeta_id),
			KEY user_id (submission_id),
			KEY meta_key (meta_key)
		) $charset_collate;";
		dbDelta($sql);
	}
	register_activation_hook(__FILE__, 'kforms_install');
}

if(!function_exists('kforms_init')) {
	function kforms_init() {
		global $wpdb, $kforms_data;
		
		$wpdb->kforms_submissions = $wpdb->prefix.'kforms_submissions';
		$wpdb->kforms_submissionmeta = $wpdb->prefix.'kforms_submissionmeta';
		
		if(!empty($_POST[KFORMS_POST_KEY])) {
			$kforms_data = $_POST[KFORMS_POST_KEY];
			kforms_handler($kforms_data);
		}
		
		// To do: make overloadable
		wp_enqueue_style('kforms', KFORMS_PLUGIN_URL.'assets/css/forms.css');
	}
}
add_action('init', 'kforms_init');

if(!function_exists('kforms_handler')) {
	function kforms_handler($data) {
		global $wp_version, $kforms_errors, $kforms_status_msg, $kforms_success;
		$submit_data = array();
		
		if(!empty($data['form_id'])) {
			$form_id = $data['form_id'];
			$form = get_option('kforms_form_'.$form_id);
			if($form) {
				$fields = kforms_parse_structure($form['form_structure']);
				foreach($fields as $field) {
					if(!empty($field['required'])) {
						if(empty($data[$field['name']]) || $data[$field['name']] == '') {
							$kforms_errors[$field['name']] = (!empty($field['error']) ? __($field['error']) : __('Please fill in this field'));
						}
					}
					
					if(!empty($field['validate'])) {
						if(strpos($field['validate'], '|')) {
							$validation_types = explode('|', $field['validate']);
						} else {
							$validation_types = array($field['validate']);
						}
						if(!empty($validation_types)) {
							require_once('inc/validate.php');
							foreach($validation_types as $validation_type) {
								$func = 'kforms_validate_'.$validation_type;
								if(function_exists($func)) {
									$validation_result = $func($data[$field['name']]);
									if(is_array($validation_result) && !empty($validation_result['error'])) {
										$kforms_errors[$field['name']] = $validation_result['error'];
									}
								}
							}
						}
					}

					if(isset($data[$field['name']])) {
						$submit_data[$field['name']] = $data[$field['name']];
					}					
				}
				if(empty($kforms_errors)) {
					// Sort out names
					if(!isset($submit_data['name']) && (isset($submit_data['first_name']) || isset($submit_data['last_name']))) {
						$submit_data['name'] = (!empty($submit_data['first_name']) ? $submit_data['first_name'] : '').(!empty($submit_data['last_name']) ? ' '.$submit_data['last_name'] : '');
						$fields['name'] = array('name' => 'name');
					}
					if((!isset($submit_data['first_name']) || !isset($submit_data['last_name'])) && isset($submit_data['name'])) {
						require_once('lib/name_parser.php');
						$name = split_full_name($submit_data['name']);
						if(!isset($submit_data['first_name'])) {
							$submit_data['first_name'] = $name['fname'];
							$fields['first_name'] = array('name' => 'first_name');
						}
						if(!isset($submit_data['last_name'])) {
							$submit_data['last_name'] = $name['lname'];
							$fields['last_name'] = array('name' => 'last_name');
						}
					}
					
					// Allow themes and plugins to handle
					do_action('kforms_submit_'.$form_id, $form, $submit_data);
					kforms_add_submission($form_id, $submit_data);
					
					// Send the notification
					if(!empty($form['notifications'])) {
						require_once('inc/tokens.php');
						$tokens = new KFormsTokens($form, $fields, $submit_data);
						foreach($form['notifications'] as $notification) {
							$to = $tokens->parse($notification['recipient_to']);
							$from = $tokens->parse($notification['recipient_from']);
							$cc = $tokens->parse($notification['recipient_cc']);
							$bcc = $tokens->parse($notification['recipient_bcc']);
							$subject = $tokens->parse($notification['recipient_subject']);
							$message = $tokens->parse($notification['recipient_message']);
							$headers = array();
							if(!empty($from)) {
								$headers[] = 'from: '.$from;
							}
							// Wordpress bug prior to 3.2 does not enable CC/BCC
							if(version_compare($wp_version, '3.2') >= 0) {
								if(!empty($cc)) {
									$headers[] = 'cc: '.$cc;
								}
								if(!empty($bcc)) {
									$headers[] = 'bcc: '.$bcc;
								}
							}
							$attachments = array();
							if(empty($subject)) {
								$subject = sprintf(__('%s submission'), $form['name']);
							}
							wp_mail($to, $subject, $message, $headers, $attachments);
						}
					}
					$kforms_success[$form_id] = true;
					$kforms_data = $submit_data;
				} else {
					$kforms_status_msg[$form_id][] = array('error', __('Please correct the form errors', 'kforms'));
				}
			} else {
				kforms_error('missing_form', $data);
			}
		}
	}
}

if(!function_exists('kforms_add_submission')) {
	function kforms_add_submission($form_id = 'contact_form', $data) {
		global $wpdb, $kforms_core_fields;
		$record = array();
		$record['form_id'] = $form_id;
		$record['submission_date'] = date('Y-m-d H:i:s');
		$record['ip'] = $_SERVER['REMOTE_ADDR'];
		$record['source'] = $_SERVER['REQUEST_URI'];
		if(!empty($_SERVER['HTTP_REFERER'])) {
			$record['referrer'] = $_SERVER['HTTP_REFERER'];
		} else {
			$record['referrer'] = '';
		}
		if(!empty($kforms_core_fields)) {
			foreach($kforms_core_fields as $field => $default) {
				if(isset($data[$field])) {
					$record[$field] = $data[$field];
					unset($data[$field]);
				}
				else {					
					$record[$field] = $default;
				}
			}
		}
		$result = $wpdb->insert($wpdb->kforms_submissions, $record);
		if($result) {
			$submission_id = $wpdb->insert_id;
			if(!empty($data)) {
				foreach($data as $field => $value) {
					kforms_add_submission_meta($submission_id, $field, $value);
				}
			}
			kforms_increase_submission_count($form_id);
		}
		return $result;
	}
}

if(!function_exists('kforms_update_submission')) {
	function kforms_update_submission($id, $data) {
		global $wpdb, $kforms_core_fields;
		$record = array();
		$record['form_id'] = $data['form_id'];
		$record['submission_date'] = $data['submission_date'];
		$record['ip'] = $data['ip'];
		$record['source'] = $data['source'];
		$record['referrer'] = $data['referrer'];
		unset($data['form_id']);
		unset($data['submission_date']);
		unset($data['ip']);
		unset($data['source']);
		unset($data['referrer']);
		if(!empty($kforms_core_fields)) {
			foreach($kforms_core_fields as $field => $default) {
				if(isset($data[$field])) {
					$record[$field] = $data[$field];
					unset($data[$field]);
				}
				else {					
					$record[$field] = $default;
				}
			}
		}
		$result = $wpdb->update($wpdb->kforms_submissions, $record, array('ID' => $id));
		if($result) {
			if(!empty($data)) {
				foreach($data as $field => $value) {
					kforms_update_submission_meta($id, $field, $value);
				}
			}
		}
		return $result;
	}
}

if(!function_exists('kforms_get_submission')) {
	function kforms_get_submission($submission_id) {
		global $wpdb;
		$result = $wpdb->get_row("SELECT * FROM `".$wpdb->kforms_submissions."` WHERE ID = $submission_id", ARRAY_A);
		if($result) {
			$meta = kforms_get_submission_meta($submission_id);
		}
		return $result;
	}
}

if(!function_exists('kforms_add_submission_meta')) {
	function kforms_add_submission_meta($submission_id, $meta_key, $meta_value, $unique = false) {
		return add_metadata('kforms_submission', $submission_id, $meta_key, $meta_value, $unique);
	}
}

if(!function_exists('kforms_get_submission_meta')) {
	function kforms_get_submission_meta($submission_id) {
		return get_metadata('kforms_submission', $submission_id);
	}
}

if(!function_exists('kforms_update_submission_meta')) {
	function kforms_update_submission_meta($submission_id, $meta_key, $meta_value) {
		return update_metadata('kforms_submission', $submission_id, $meta_key, $meta_value);
	}
}

if(!function_exists('kforms_increase_submission_count')) {
	function kforms_increase_submission_count($form_id, $increase = 1) {
		$current_count = get_option('kforms_count_'.$form_id, 0);
		update_option('kforms_count_'.$form_id, $current_count + $increase);
	}
}

if(!function_exists('kforms_error')) {
	function kforms_error($type, $args = array()) {
		
	}
}

if(!function_exists('kforms_parse_structure')) {
	function kforms_parse_structure($structure) {
		$fields = array();
		$tag = 'field';
		$matches = array();
		$pattern = '(.?)\[('.$tag.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
		preg_match_all('/'.$pattern.'/s', $structure, $matches);
		if(!empty($matches[3])) {
			foreach($matches[3] as $atts) {
				$fields[] = shortcode_parse_atts($atts);
			}
		}
		return $fields;
	}
}

if(!function_exists('kforms_is_core_field')) {
	function kforms_is_core_field($field) {
		global $kforms_core_fields;
		$log_fields = array(
			'submission_date',
			'form_id',
			'source',
			'referrer',
			'ip'
		);
		if(array_key_exists($field, $kforms_core_fields) || in_array($field, $log_fields)) {
			return true;
		}
		return false;
	}
}

require_once('inc/admin.php');
require_once('inc/shortcodes.php');
?>