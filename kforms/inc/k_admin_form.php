<?php
if(!defined('K_ADMIN_FORM_POST_KEY')) {
	define('K_ADMIN_FORM_POST_KEY', 'k_admin_form');
}
class KAdminForm {
	
	var $id = false;
	var $primary_key = 'ID';
	var $type = 'wp_options';
	var $id_callback_func = null;
	var $tableLayout = true;
	var $data = array();
	var $errors = array();
	var $autoload = false;
	
	function __construct($id = false, $options = array()) {
		$this->id = $id;
		$nonce_key = false;
		$presave_filter = false;
		if(!empty($options['id_callback_func'])) {
			$this->id_callback_func = $options['id_callback_func'];
		}
		if(!empty($options['nonce_key'])) {
			$nonce_key = $options['nonce_key'];
		}
		if(!empty($options['presave_filter'])) {
			$presave_filter = $options['presave_filter'];
		}
		if(!empty($options['type'])) {
			$this->type = $options['type'];
		}
		if(!empty($_POST[K_ADMIN_FORM_POST_KEY])) {
			if(!$nonce_key) {
				$nonce_key = 'k_admin_form';
			}
			check_admin_referer($nonce_key);
			$this->data = $_POST[K_ADMIN_FORM_POST_KEY];
			if($presave_filter) {
				$this->data = apply_filters($presave_filter, $this->data, $this->id);
			}
			$this->saveData();
		}
		if(empty($this->data)) {
			$this->load();
		}
	}
	
	
	function saveData() {
		global $k_status;
		
		$value = false;
		$result = false;
		if(!empty($this->data)) {
			if($this->type == 'wp_options') {
				if(isset($this->data)) {
					$value = $this->data;
				}
				if(!is_array($value)) {
					$value = trim($value);
				}
				if($value) {
					$value = stripslashes_deep($value);
					if($this->id) {
						if(!empty($this->data[$this->primary_key]) && $this->id == $this->data[$this->primary_key]) {
							update_option($this->id, $value);
							$result = true;
						} else {
							delete_option($this->id);
							$this->id = false;
						}
					}
					if(!$this->id) {
						$key = call_user_func($this->id_callback_func, $this->data);
						if($key) {
							add_option($key, $value, '', ($this->autoload ? 'yes':'no'));
							$result = true;
						} else {
							wp_die(__('No key set'));
						}
					}
				}
			} elseif($this->type == 'wp_kforms_submissions') {
				if($this->id) {
					$result = kforms_update_submission($this->id, $this->data);
				} else {
					$result = kforms_add_submission($this->data['form_id'], $this->data);
				}
			}
		}
		if($result) {
			$k_status = 'saved';
		}
	}
	
	function load() {
		switch($this->type) {
			case 'wp_options':
				$this->data = get_option($this->id);
				break;
				
			case 'wp_kforms_submissions':
				$this->data = kforms_get_submission($this->id);
				break;
		}
	}
	
	function create($options = array()) {
		$options = array_merge($options, array('method' => 'post', 'action' => null, 'class' => null));
		$output = '<form method="'.$options['method'].'" class="">'."\n";
		$output .= $this->nonceField();
		if($this->tableLayout) {
			$output .= $this->createTable();
		}
		return $output;
	}
	
	function createTable() {
		$this->tableLayout = true;
		return '<table class="form-table">';
	}
	
	function endTable() {
		return '</table>';
	}
	
	function nonceField() {
		if($this->id) {
			$nonce_key = $this->id;
		} else {
			$nonce_key = 'k_admin_form';
		}
		return wp_nonce_field($nonce_key, '_wpnonce', true, false)."\n";
	}	
	
	function input($fieldName, $options = array()) {
		$options += array(
			'value' => null,
			'label' => null,
			'type' => 'text',
			'description' => false,
			'id' => false,
			'options' => array(),
			'maxYear' => date('Y', strtotime('+10 years')),
			'minYear' => date('Y', strtotime('-10 years')),
			'empty' => false,
			'required' => false
		);
				
		$field_name_arr = array();
		if(strpos($fieldName, '.')) {
			$field_name_arr = explode('.', $fieldName);
			$name = '['.implode('][', $field_name_arr).']';
		} else {
			$field_name_arr[0] = $fieldName;
			$name = '['.$fieldName.']';
		}

		if($options['label'] == null && $options['label'] !== false) {
			$options['label'] = ucwords(str_replace('_', ' ', end($field_name_arr)));
		}
		
		// To do: Find a better recursive way to do this
		if(!empty($field_name_arr[2]) && isset($this->data[$field_name_arr[0]][$field_name_arr[1]][$field_name_arr[2]])) {
			$options['value'] = $this->data[$field_name_arr[0]][$field_name_arr[1]][$field_name_arr[2]];
		}
		elseif(!empty($field_name_arr[1]) && isset($this->data[$field_name_arr[0]][$field_name_arr[1]])) {
			$options['value'] = $this->data[$field_name_arr[0]][$field_name_arr[1]];
		} elseif(!empty($this->data[$field_name_arr[0]]) && !is_array($this->data[$field_name_arr[0]])) {
			$options['value'] = $this->data[$field_name_arr[0]];
		}
		$options['value'] = stripslashes($options['value']);
		
		if(!$options['id']) {
			$options['id'] = str_replace(' ','',ucwords(str_replace('_',' ',str_replace('.', '_', $fieldName))));
		}
		
		$error = false;
		$error_msg = false;
		if(!empty($field_name_arr[1]) && !empty($this->errors[$field_name_arr[0]][$field_name_arr[1]])) {
			$error = true;
			$error_msg = '<p class="form-error">'.$this->errors[$field_name_arr[0]][$field_name_arr[1]].'</p>'."\n";
		}
		
		if($this->tableLayout) {
			$output = '<tr class="'.($error ? ' error':'').'">';
		} else {
			$output = '';
		}
		
		if($options['label']) {
			if($this->tableLayout) {
				$output .= '<th scope="row">';	
			} 
			$output .= '<label for="'.$options['id'].'">'.$options['label'].'</label>'.(!empty($options['required']) ? ' <span class="description">'.__('(required)').'</span>':'');
			if($this->tableLayout) {
				$output .= '</th>';
			}
		} else {
			if($this->tableLayout) {
				$output .= '<th></th>'."\n";
			}
		}
		
		if($this->tableLayout) {
			$output .= '<td>'."\n";
		}

		switch($options['type']) {
			case 'text':
			default:
				$output .= '<input type="text" name="'.K_ADMIN_FORM_POST_KEY.$name.'" value="'.$options['value'].'" id="'.$options['id'].'" class="regular-text" />';
				break;
			
			case 'password':
				$output .= '<input type="password" name="'.K_ADMIN_FORM_POST_KEY.$name.'" value="'.$options['value'].'" id="'.$options['id'].'" />';
				break;
				
			case 'textarea':
				$output .= '<textarea name="'.K_ADMIN_FORM_POST_KEY.$name.'" class="large-text" rows="10" cols="50">'.$options['value'].'</textarea>'."\n";
				break;
				
			case 'checkbox':
				$output .= '<label for="'.$options['id'].'">';
				$output .= '<input type="hidden" name="'.K_ADMIN_FORM_POST_KEY.$name.'" value="0" id="_'.$options['id'].'" />';
				$output .= '<input type="checkbox" name="'.K_ADMIN_FORM_POST_KEY.$name.'" value="1" id="'.$options['id'].'" '.($options['value'] == 1 ? 'checked="checked" ':'').'/> ';
				if($options['label']) {
					$output .= $options['label'];
				}
				$output .= '</label>'."\n";
				
				break;
				
			case 'select':
				$output .= '<select name="'.K_ADMIN_FORM_POST_KEY.$name.'" id="'.$options['id'].'">';
				if($options['empty']) {
					$output .= '<option value="">'.($options['empty'] !== true ? $options['empty']:'').'</option>'."\n";
				}
				foreach($options['options'] as $option_val => $option_text) {
					$output .= '<option value="'.$option_val.'"'.($option_val == $options['value']  ? ' selected="selected"':'').'>'.$option_text.'</option>';
				}
				$output .= '</select>';
				break;
							
		}
		if($error_msg) {
			$output .= $error_msg;
		}
		if($options['description']) {
			$output .= ' <span class="description">'.$options['description'].'</span>'."\n";
		}
		if($this->tableLayout) {
			$output .= '</td>'."\n";
			$output .= '</tr>';
		}
		return $output;
	}
		
	function submit($text = 'Submit', $class = "button-primary") {
		$output = '';
		if($this->tableLayout) {
			$output .= '<tr>';
			$output .= '<td colspan="2">';
		}
		$output .= '<p class="submit"><input type="submit" class="'.$class.'" value="'.$text.'" /></p>'."\n";
		if($this->tableLayout) {
			$output .= '</td></tr>';
		}
		return $output;
	}
	
	function end() {
		$output = '';
		if($this->tableLayout) {
			$output .= $this->endTable();
		}
		$output .= '</form>'."\n";
		return $output;
	}
	
}
?>