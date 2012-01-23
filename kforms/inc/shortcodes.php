<?php
if(!function_exists('kforms_form_tag')) {
	function kforms_tag($atts, $content = null, $code = "") {
		global $kforms_data, $kforms_status_msg, $kforms_success;
		extract(
			shortcode_atts(
				array(
					'id' => "contact_form"
				),
				$atts
			)
		);
		$output = '';
		
		$form = get_option('kforms_form_'.$id);
		
		if(!empty($kforms_success[$id]) && empty($form['thank_you_message'])) {
			$kforms_status_msg[$id][] = array('success', __('Thank you!', 'kforms'));
		}
		
		if(!empty($kforms_status_msg[$id])) {
			$output .= '<div id="kforms-message">'."\n";
			foreach($kforms_status_msg[$id] as $msg) {
				$output .= '<p class="'.$msg[0].'">'.$msg[1].'</p>'."\n";
			}
			$output .= '</div>'."\n";
		}
		if($form) {
			if(empty($kforms_success[$id]) || empty($form['thank_you_message'])) {
				$form += array(
					'form_structure' => '',
					'method' => 'post'
				);
				$output .= '<form method="'.$form['method'].'" class="kforms">'."\n";
				$output .= '	<input type="hidden" name="'.KFORMS_POST_KEY.'[form_id]" value="'.$id.'" />'."\n";
				$output .= do_shortcode($form['form_structure']);
				$output .= '</form>'."\n";
			} elseif(!empty($form['thank_you_message'])) {
				require_once('tokens.php');				
				$fields = kforms_parse_structure($form['form_structure']);
				$tokens = new KFormsTokens($form, $fields, $kforms_data);
				$output .= $tokens->parse($form['thank_you_message']);
			}
		}
		return $output;
	}
}
add_shortcode('kforms', 'kforms_tag');

if(!function_exists('kforms_submit_tag')) {
	function kforms_submit_tag($atts, $content = null, $code = "") {
		extract(
			shortcode_atts(
				array(
					'name' => false,
					'id' => null,
					'text' => null,
				),
				$atts
			)
		);
		if(empty($text)) {
			$text = __('Submit');
		}
		$output = '<div class="kform submit">'."\n";
		$output .= '	<input type="submit" '.(!empty($name) ? 'name="'.$name.'"':'').(!empty($id) ? 'id="'.$id.'"':'').'value="'.$text.'" />'."\n";
		$output .= '</div>'."\n";
		return $output;
	}
}
add_shortcode('submit', 'kforms_submit_tag');

if(!function_exists('kforms_field_tag')) {
	function kforms_field_tag($atts, $content = null, $code = "") {
		global $kforms_data, $kforms_errors;

		$invalid = false;
		extract(
			shortcode_atts(
				array(
					'type' => 'text',
					'name' => false,
					'id' => null,
					'label' => null,
					'value' => null,
					'default' => false,
					'error' => null,
					'options_callback' => false,
					'validate' => array(),
					'required' => false,
					'required_star' => 'true',
				),
				$atts
			)
		);
		if($name) {
			if(empty($id)) {
				$id = str_replace(' ','',ucwords(str_replace('_',' ',$name)));
			}
			
			if($label !== false && $type != 'hidden') {
				if(is_null($label)) {
					$label = ltrim(preg_replace('/([A-Z])/e',"'_'.strtolower('$1')",$name),'_'); // decamelize
					$label = ucwords(str_replace('_', ' ', $label)); // humanize
				}
				$the_label = '<label for="'.$id.'">'.$label.(!empty($required) && $required_star == "true"? ' *':'').'</label>'."\n";
			}
			
			if(!empty($kforms_data[$name])) {
				$value = $kforms_data[$name];
			}
			
			if(empty($value) && $default) {
				$value = $default;
			}
			
			if(!empty($kforms_errors[$name])) {
				$invalid = true;
				$error_msg = $kforms_errors[$name];
			}
						
			if($type != 'hidden') {
				$output = '<div class="kforms-field '.$type.($invalid ? ' error':'').'">';
			}			
			
			$name = KFORMS_POST_KEY.'['.$name.']';
						
			switch($type) {
				case 'checkbox':
					$output .= '<input type="hidden" id="_'.$id.'" name="'.$name.'" value="0" />';
					$output .= '<input type="checkbox" id="'.$id.'" name="'.$name.'" value="1" '.($value == 1 ? 'checked="checked" ':'').'/>';
					$output .= $the_label;				
					break;
					
				case 'radios':
					break;
					
				case 'hidden':
					$output .= '<input type="hidden" id="'.$id.'" name="'.$name.'" value="'.$value.'" />';
					break;
				
				case 'textarea':
					$output .= $the_label;
					$output .= '<textarea name="'.$name.'" id="'.$id.'">';
					$output .= $value;
					$output .= '</textarea>';
					break;

				case 'select':
					$output .= $the_label;				
					$output .= '<select name="'.$name.'" id="'.$id.'">';
					if($options_callback) {
						$output .= call_user_func($options_callback);
					} else {
						$output .= do_shortcode($content);
					}
					$output .= '</select>';
					break;

				case 'text':
					$output .= $the_label;				
					$output .= '<input type="text" id="'.$id.'" name="'.$name.'" value="'.$value.'" />';
					break;
					
				default:
					if(function_exists('kforms_tag_'.$type)) {
						$func = 'kforms_tag_'.$type;
						$output .= $func($id, $name, $label, $value);
					}
					break;
			}
			
			if($invalid) {
				$output .= '<div class="kforms-error">'.$error_msg.'</div>';
			}
			
			if($type != 'hidden') {
				$output .= '</div>'."\n";
			}
			
			return $output;
		}
	}
}
add_shortcode('field', 'kforms_field_tag');

if(!function_exists('kforms_option_tag')) {
	function kforms_option_tag($atts, $content = null, $code = "") {
		extract(
			shortcode_atts(
				array(
					'value' => null,
				),
				$atts
			)
		);
		if(!empty($value) && !empty($content)) {
			$output = '<option value="'.$value.'">'.$content.'</option>'."\n";
		}
		return $output;
	}
}
add_shortcode('option', 'kforms_option_tag');
?>