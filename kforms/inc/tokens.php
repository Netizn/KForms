<?php
class KFormsTokens {
	
	var $form;
	var $fields;
	var $tags;
	var $data;
	var $wp_fields = array(
		array('name' => 'wp_site_url', 'type' => 'wordpress', 'key' => 'siteurl'),
		array('name' => 'wp_name', 'type' => 'wordpress', 'key' => 'name'),
		array('name' => 'wp_description', 'type' => 'wordpress', 'key' => 'description'),
		array('name' => 'wp_admin_email', 'type' => 'wordpress', 'key' => 'admin_email'),
		array('name' => 'wp_language', 'type' => 'wordpress', 'key' => 'language'),
	);
	
	function __construct($form, $fields, $data) {
		$this->form = $form;
		$this->fields = array_merge($fields, $this->wp_fields);
		$tags = array();
		foreach($this->fields as $field) {
			$tags[$field['name']] = $field;
		}
		$this->tags = $tags;
		$this->data = $data;
	}
	
	function parse($content) {
		$tagregexp = join( '|', array_map('preg_quote', array_keys($this->tags)));
		$pattern = '(.?)\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
		return preg_replace_callback('/'.$pattern.'/s', array(&$this, 'token'), $content);
	}
	
	function token($m) {
		// allow [[foo]] syntax for escaping a tag
		if ( $m[1] == '[' && $m[6] == ']' ) {
			return substr($m[0], 1, -1);
		}
		$tag = $m[2];
		$attr = shortcode_parse_atts( $m[3] );

		if(!empty($this->tags[$tag])) {
			if(!empty($this->tags[$tag]['type']) && $this->tags[$tag]['type'] == 'wordpress') {
				return $m[1].get_bloginfo($this->tags[$tag]['key']).$m[6];
			} else {
				if(!empty($this->data[$tag])) {
					if(is_array($this->data[$tag])) {
						$output = $m[1];
						foreach($this->data[$tag] as $item => $val) {
							if(is_array($val)) {
								$output .= $item.': '.join(',', $val)."\n";
							} else {
								$output .= $item.': '.$val."\n";
							}
						}
						$output .= $m[6];
						return $output;
					}
					return $m[1].$this->data[$tag].$m[6];
				} elseif(!empty($attr['default'])) {
					return $m[1].$attr['default'].$m[6];
				}
			}
		}
		return false;
	}	
		
}
?>