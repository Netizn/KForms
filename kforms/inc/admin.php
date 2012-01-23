<?php
if ( !defined('ABSPATH') )
	die('-1');

require_once('k_admin_form.php');

global $kforms_options, $k_status;
$kforms_options = array('kforms_form_add', 'kforms_form_edit');

if(!function_exists('kforms_admin_menu')) {
	function kforms_admin_menu() {
		add_menu_page(__('(K)Forms', 'kforms'), __('(K)Forms', 'kforms'), 'manage_options', 'kforms', null, WP_PLUGIN_URL.'/kforms/assets/images/menu-icon.png');
		add_submenu_page('kforms', __('List forms', 'kforms'), __('List forms', 'kforms'), 'manage_options', 'kforms', 'kforms_forms_list');
		add_submenu_page('kforms', __('Form administration', 'kforms'), __('Add a form', 'kforms'), 'manage_options', 'kforms-edit', 'kforms_form_edit');
		add_submenu_page('kforms', __('Submissions', 'kforms'), __('Submissions', 'kforms'), 'manage_options', 'kforms-submissions', 'kforms_submissions');
		add_submenu_page('kforms', __('Submission administration', 'kforms'), __('Add a submission', 'kforms'), 'manage_options', 'kforms-submission-admin', 'kforms_submission_admin');
	}
}
add_action('admin_menu', 'kforms_admin_menu');

if(!function_exists('kforms_admin_init')) {
	function kforms_admin_init() {
		add_action('admin_enqueue_scripts','kforms_scripts_enqueue',10,1);		
	}
}
add_action('admin_init', 'kforms_admin_init');

if(!function_exists('kforms_scripts_enqueue')) {
	function kforms_scripts_enqueue($hook) {
		if($hook == 'kforms_page_kforms-edit') {
			wp_enqueue_script('kforms-edit-custom', KFORMS_PLUGIN_URL.'assets/js/kforms-edit-custom.js', array('jquery'));
		}
	}
}

if(!function_exists('kforms_contextual_help')) {
	function kforms_contextual_help() {
		$help_text = array(
			'toplevel_page_kforms' => '<p>This is my help message.</p>'
		);
		foreach($help_text as $screen => $text) {
			add_contextual_help($screen, $text);
		}
	}
}
add_action('admin_init', 'kforms_contextual_help');

if(!function_exists('kforms_register_columns')) {
	function kforms_register_columns() {
		$tables = array(
			'toplevel_page_kforms' => array(
				'cb' => '',
				'name' => 'Name',
				'submissions_count' => 'Submissions',
				'shortcode' => 'Shortcode',
			),
			'toplevel_page_kforms_submissions' => array(
				'cb' => '',
				'name' => 'Name',
				'email' => 'Email',
				'form_id' => 'Form',
				'submission_date' => 'Date',
			)
		);
		foreach($tables as $screen => $columns) {
			register_column_headers($screen, $columns);
		}
	}
}
add_action('admin_init', 'kforms_register_columns');

if(!function_exists('kforms_list')) {
	function kforms_list($sort = 'form_id') {
		global $wpdb;
		$sql = "SELECT option_name, option_value FROM $wpdb->options WHERE `option_name` LIKE 'kforms_form_%' ORDER BY option_name ASC";
		$rows = $wpdb->get_results($sql);
		$results = array();
		if(!empty($rows)) {
			foreach($rows as $row) {
				$data = unserialize($row->option_value);
				$results[$data['ID']] = $data;
			}
		}
		return $results;
	}
}

if(!function_exists('kforms_forms_list')) {
	function kforms_forms_list() {
		global $k_status;
		$current_screen = 'toplevel_page_kforms';
				
		if(!empty($_GET['action'])) {
			if($_GET['action'] == 'delete' && !empty($_GET['form'])) {
				if(delete_option('kforms_form_'.$_GET['form'])) {
					$k_status = 'deleted';
				}
			}
		}
		
		$forms = kforms_list();		
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br></div>
			<h2>(K)Forms <a href="admin.php?page=kforms-edit" class="button add-new-h2"><?php _e('Add new form', 'kforms'); ?></a></h2>
				<?php
				if(!empty($k_status)) {
					if($k_status == 'deleted') {
						echo '<div id="message" class="updated"><p>'.__('Form <strong>deleted</strong>.').'</p></div>'."\n";
					}
				}
				if(!empty($forms)) {
					?>
					<form action="options.php" method="post">
						<table class="widefat fixed" cellspacing="0">
							<thead>
							<tr>
						<?php print_column_headers('toplevel_page_kforms'); ?>
							</tr>
							</thead>

							<tfoot>
							<tr>
						<?php print_column_headers('toplevel_page_kforms', false); ?>
							</tr>
							</tfoot>

							<tbody>
						<?php
						$columns = get_column_headers($current_screen);
						$hidden = get_hidden_columns($current_screen);
					foreach($forms as $id => $form) {
						echo '<tr>'."\n";
						foreach($columns as $column_name => $column_display_name) {
							$class = "class=\"$column_name column-$column_name\"";
							$style = '';
							if ( in_array($column_name, $hidden) )
								$style = ' style="display:none;"';

							$attributes = "$class$style";
							
							switch ($column_name) {

							case 'cb':
								?>
								<th scope="row" class="check-column"><input type="checkbox" name="form[]" value="<?php echo $id; ?>" /></th>
								<?php
								break;

							case 'ID':
								?>
								<td <?php echo $attributes ?>><?php echo $id; ?></td>
								<?php
								break;
								
							case 'submissions_count':
								$submissions_count = get_option('kforms_count_'.$id);
								echo '<td class="'.$attributes.'">';
								if($submissions_count) {
									echo '<a href="admin.php?page=kforms-submissions&id='.$id.'">'.$submissions_count.'</a>';
								} else {
									echo '0';
								}
								echo '</td>'."\n";
								break;
																
							case 'shortcode':
								?>
								<td <?php echo $attributes ?>>[kforms id="<?php echo $id; ?>"]</td>
								<?php
								break;
								
							case 'name':
								$edit_link = 'admin.php?page=kforms-edit&form='.$id;
								$delete_link = 'admin.php?page=kforms&action=delete&form='.$id;
								$export_link = admin_url('admin-ajax.php').'?action=kforms_submissions_export&form_id='.$id;
								?>
								<td <?php echo $attributes ?>>
									<strong><a href="<?php echo $edit_link; ?>" class="row-title"><?php echo $form['name']; ?></a></strong>
									<?php
									$actions = array();
									if(current_user_can('manage_options')) {
										$actions['edit'] = '<a href="'.$edit_link.'" title="'.esc_attr(__('Edit this form')).'">'.__('Edit settings') . '</a>';
										$actions['delete'] = '<a href="'.$delete_link.'" title="'.esc_attr(__('Delete this form')).'" onclick="return confirm(\''.__('Are you sure you wish to delete this form. This action cannot be undone.', 'kforms').'\');">'.__('Delete') . '</a>';
										$actions['export'] = '<a href="'.$export_link.'" title="'.esc_attr(__('Export this form\'s data')).'">'.__('Export', 'kforms').'</a>';
									}
									$actions = apply_filters('kforms_row_actions', $actions);
									$action_count = count($actions);
									$i = 0;
									echo '<div class="row-actions">';
									foreach ( $actions as $action => $link ) {
										++$i;
										( $i == $action_count ) ? $sep = '' : $sep = ' | ';
										echo "<span class='$action'>$link$sep</span>";
									}
									echo '</div>';
									?>
								</td>
								<?php
								break;
	
							default:
								?>
								<td <?php echo $attributes ?>><?php if(!empty($form[$column_name])) { echo $form[$column_name]; } ?></td>
								<?php
								break;
							}
						}
						echo '</tr>'."\n";
					}
				} else {
					echo '<p>'.__('No forms found.').'</p>'."\n";
				}
				?>
					</tbody>
				</table>
			</form>
			<?php do_action('kforms_credits'); ?>
		</div>
		<?php
		return;
	}
}

if(!function_exists('kforms_credits')) {
	function kforms_credits() {
		echo '<p class="kform-credits">'.__('(K)Forms plugin for Wordpress by <a href="http://www.netizn.co" title="Netizn">Netizn</a>').'. Licensed under GPL.</p>'."\n";
	}
}
add_action('kforms_credits', 'kforms_credits');

if(!function_exists('kforms_form_edit')) {
	function kforms_form_edit() {
		global $k_status;
		
		if(!empty($_GET['form'])) {			
			$form_id = 'kforms_form_'.filter_input(INPUT_GET, 'form', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
			$nonce_key = $form_id;
		} 
		else {
			$form_id = false;
			$nonce_key = false;
		}
		
		$options = array(
			'id_callback_func' => 'kforms_option_key',
			'nonce_key' => $nonce_key,
			'presave_filter' => 'kforms_form_edit_presave'
		);
		$form = new KAdminForm($form_id, $options);
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?php echo ($form_id ? __('Edit form', 'kforms'):__('Add a form', 'kforms')); ?></h2>
			<?php
			if(!empty($k_status)) {
				if($k_status == 'saved') {
					echo '<div id="message" class="updated"><p>'.__('Form <strong>saved</strong>.').'</p></div>'."\n";
				}
			}
			echo $form->create();
			echo '<tr><td colspan="2"><h3>'.__('Basic settings', 'kforms').'</h3></td></tr>'."\n";
			
			echo $form->input('ID');
			echo $form->input('name');
			echo $form->input('form_structure', array('type' => 'textarea', 'description' => '[field name="field_name" label="Label for field" validate="email" required="true"] [submit text="Save"]'));
			
			echo '<tr><td colspan="2"><h3>'.__('Notifications', 'kforms').'</h3></td></tr>'."\n";
			if(!empty($form->data['notifications'])) {
				foreach($form->data['notifications'] as $nkey => $notification) {
					echo '<tr id="kforms-edit-notifications-'.$nkey.'"><td style="vertical-align: top;">'."\n";
					echo '<h4>#'.($nkey + 1).'</h4>'."\n";
					echo '<p><a href="#" id="kforms-remove-notification-'.$nkey.'" class="kforms-remove-notification">Remove</span></p>'."\n";
					echo '</td><td>'."\n";
					echo $form->createTable();
					echo $form->input('notifications.'.$nkey.'.recipient_to', array('label' => __('Recipient email address')));
					echo $form->input('notifications.'.$nkey.'.recipient_cc', array('label' => __('Recipient cc address'), 'description' => __('Separate email addresses with commas')));
					echo $form->input('notifications.'.$nkey.'.recipient_bcc', array('label' => __('Recipient bcc address'), 'description' => __('Separate email addresses with commas')));
					echo $form->input('notifications.'.$nkey.'.recipient_from', array('label' => __('From address'), 'description' => __('Who should send the notification email?')));
					echo $form->input('notifications.'.$nkey.'.recipient_subject', array('label' => __('Subject')));
					echo $form->input('notifications.'.$nkey.'.recipient_message', array('type' => 'textarea', 'description' => ''));
					echo $form->endTable();
					echo '</td></tr>'."\n";
				}
			}
			
			echo '<tr id="kforms-edit-notifications-new"><td style="vertical-align: top;">'."\n";
			echo '<h4>'.__('Add a new notification', 'kforms').'</h4>'."\n";
			echo '</td><td>'."\n";
			echo $form->createTable();
			echo $form->input('notifications.new.recipient_to', array('label' => __('Recipient email address')));
			echo $form->input('notifications.new.recipient_cc', array('label' => __('Recipient cc address'), 'description' => __('Separate email addresses with commas')));
			echo $form->input('notifications.new.recipient_bcc', array('label' => __('Recipient bcc address'), 'description' => __('Separate email addresses with commas')));
			echo $form->input('notifications.new.recipient_from', array('label' => __('From address'), 'description' => __('Who should send the notification email?')));
			echo $form->input('notifications.new.recipient_subject', array('label' => __('Subject')));			
			echo $form->input('notifications.new.recipient_message', array('type' => 'textarea', 'description' => ''));
			echo $form->endTable();
			echo '</td></tr>'."\n";
			
			echo '<tr><td colspan="2"><h3>'.__('Thank you page', 'kforms').'</h3></td></tr>'."\n";
			echo $form->input('thank_you_message', array('type' => 'textarea', 'description' => __('Message that replaces the form on successful completion', 'kforms')));
			
			echo $form->submit(__('Save form', 'kforms'));
			echo $form->end();
			do_action('kforms_credits');
		?>
		</div>
		<?php
	}
}

if(!function_exists('kforms_form_edit_presave')) {
	function kforms_form_edit_presave($data) {
		$errors = false;
		if(!empty($data['notifications']['new']['recipient_to'])) {
			$n_count = count($data['notifications']);
			$data['notifications'][$n_count - 1] = $data['notifications']['new'];
		}
		if(!empty($data['notifications']['new'])) {
			unset($data['notifications']['new']);
		}
		return $data;
	}	
	add_filter('kforms_form_edit_presave', 'kforms_form_edit_presave');
}

if(!function_exists('kforms_option_key')) {
	function kforms_option_key($data) {
		if(!empty($data['ID'])) {
			return 'kforms_form_'.$data['ID'];
		}
		return false;
	}
}

if(!function_exists('kforms_submissions')) {
	function kforms_submissions() {
		$current_screen = 'toplevel_page_kforms_submissions';
		
		$forms = kforms_list();

		$pagenum = isset($_GET['paged']) ? absint($_GET['paged']) : 0;
		if(empty($pagenum)) {
			$pagenum = 1;
		}
		$per_page = 10;
		$count_submissions = kforms_submissions_count();
		$submissions = kforms_submissions_query($per_page, $pagenum);
		$num_pages = ceil($count_submissions / $per_page);
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br></div>
			<h2>(K)Forms Submissions</h2>
			<div class="tablenav">
			
		<?php
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $num_pages,
			'current' => $pagenum
		));
		?>
		<?php if ( $page_links ) { ?>
				<div class="tablenav-pages"><?php
		$page_links_text = sprintf('<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
			number_format_i18n(($pagenum - 1) * $per_page + 1),
			number_format_i18n(min($pagenum * $per_page, $count_submissions)),
			number_format_i18n($count_submissions),
			$page_links
		);
		echo $page_links_text;
			?></div>
		<?php } ?>
			</div>
			<?php
			if(!empty($submissions)) {
			?>
			<form method="post">
				<table class="widefat fixed" cellspacing="0">
					<thead>
					<tr>
				<?php print_column_headers($current_screen); ?>
					</tr>
					</thead>

					<tfoot>
					<tr>
				<?php print_column_headers($current_screen, false); ?>
					</tr>
					</tfoot>

					<tbody>
					<?php
					$columns = get_column_headers($current_screen);
					$hidden = get_hidden_columns($current_screen);
					foreach($submissions as $submission) {
						echo '<tr>'."\n";
						foreach($columns as $column_name => $column_display_name) {
							$class = "class=\"$column_name column-$column_name\"";
							$style = '';
							if ( in_array($column_name, $hidden) )
								$style = ' style="display:none;"';

							$attributes = "$class$style";
							
							switch ($column_name) {

							case 'cb':
								?>
								<th scope="row" class="check-column"><input type="checkbox" name="submission[]" value="<?php echo $submission->ID; ?>" /></th>
								<?php
								break;

							case 'ID':
								?>
								<td <?php echo $attributes ?>><?php echo $submission->ID; ?></td>
								<?php
								break;
																
							case 'name':
								$edit_link = 'admin.php?page=kforms-submission-admin&id='.$submission->ID;
								?>
								<td <?php echo $attributes ?>>
									<strong><a href="<?php echo $edit_link; ?>" class="row-title"><?php echo $submission->name; ?></a></strong>
									<?php
									$actions = array();
									if(current_user_can('manage_options')) {
										$actions['edit'] = '<a href="'.$edit_link.'" title="'.esc_attr(__('View this submission')).'">'.__('View submission') . '</a>';
									}
									$actions = apply_filters('kforms_row_actions', $actions);
									$action_count = count($actions);
									$i = 0;
									echo '<div class="row-actions">';
									foreach ( $actions as $action => $link ) {
										++$i;
										( $i == $action_count ) ? $sep = '' : $sep = ' | ';
										echo "<span class='$action'>$link$sep</span>";
									}
									echo '</div>';
									?>
								</td>
								<?php
								break;
								
							case 'submission_date':
								$m_time = $submission->submission_date;
								$time = mysql2date('G', $m_time, false);

								$time_diff = time() - $time;

								if ( $time_diff > 0 && $time_diff < 24*60*60 ) {
									$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
								} else {
									$h_time = mysql2date(__('Y/m/d'), $m_time);
								}								
								echo '<td '.$attributes.'>';
								echo $h_time;
								echo '</td>';
								break;

							default:
								?>
								<td <?php echo $attributes ?>><?php if(!empty($submission->$column_name)) { echo $submission->$column_name; } ?></td>
								<?php
								break;
							}
						}
						echo '</tr>'."\n";
					}
					?>
					</tbody>
				</table>
			</form>
			<?php 
			} else {
				echo '<p>'.__('There have been no submissions.').'</p>'."\n";
			}			
			?>

			<div class="tablenav">
			<?php
			if($page_links) {
				echo "<div class='tablenav-pages'>$page_links_text</div>";
			}
			?>
			</div>
			<?php do_action('kforms_credits'); ?>
		</div>
		<?php
		return;
	}
}


if(!function_exists('kforms_submission_admin')) {
	function kforms_submission_admin() {
		global $k_status;
		$id = false;
		if(!empty($_GET['id'])) {
			$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
			$nonce_key = $id;
		} else {
			$nonce_key = false;
		}
		$options = array(
			'type' => 'wp_kforms_submissions',
			'nonce_key' => $nonce_key,
		);
		$forms = kforms_list();
		$forms_list = array();
		if(!empty($forms)) {
			foreach($forms as $fid => $f) {
				$forms_list[$fid] = $f['name'];
			}
		}
		$form = new KAdminForm($id, $options);
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?php echo ($id ? __('Edit submission', 'kforms'):__('Add a submission', 'kforms')); ?></h2>
		<?php
		if(!empty($k_status)) {
			if($k_status == 'saved') {
				echo '<div id="message" class="updated"><p>'.__('Form <strong>saved</strong>.').'</p></div>'."\n";
			}
		}	
		echo $form->create();
		echo '<tr><td colspan="2"><h3>'.__('Basic information', 'kforms').'</h3></td></tr>'."\n";
		echo $form->input('name');
		echo $form->input('first_name');
		echo $form->input('last_name');
		echo $form->input('email');
		echo $form->input('address_1');
		echo $form->input('address_2');
		echo $form->input('city');
		echo $form->input('postcode');
		echo $form->input('country');
		echo $form->input('phone_1');
		echo $form->input('phone_2');
		echo $form->input('message', array('type' => 'textarea'));
		echo $form->input('email_ok', array('type' => 'checkbox'));
		echo $form->input('post_ok', array('type' => 'checkbox'));
		echo $form->input('phone_ok', array('type' => 'checkbox'));
		echo $form->input('submission_date');
		echo $form->input('form_id', array('type' => 'select', 'options' => $forms_list, 'label' => __('Form completed', 'kforms')));
		echo $form->input('source');
		echo $form->input('referrer');
		echo $form->input('ip', array('label' => __('IP address', 'kforms')));
		$kform = false;
		if(!empty($form->data['form_id'])) {
			$kform = get_option('kforms_form_'.$form->data['form_id']);
		} elseif(!empty($_GET['form_id'])) {
			$form_id = filter_input(INPUT_GET, 'form_id', FILTER_VALIDATE_INT);
			$kform = get_option('kforms_form_'.$form_id);
		}
		echo '<tr><td colspan="2"><h3>'.__('Additional fields', 'kforms').'</h3></td></tr>'."\n";
		$additional_fields = false;
		if($kform) {
			$fields = kforms_parse_structure($kform['form_structure']);
		}
		if(!empty($fields)) {
			foreach($fields as $field) {
				if(!kforms_is_core_field($field['name'])) {
					echo $form->input($field['name'], array('type' => 'textarea', 'label' => $field['label']));
					$additional_fields = true;
				}
			}
		}
		if(!$additional_fields) {
			echo '<tr><td colspan="2"><p>'.__('None', 'kforms').'</p></td></tr>';
		}
		echo $form->submit(__('Save submission', 'kforms'));
		echo $form->end();
		do_action('kforms_credits');
		return;
	}
}

if(!function_exists('kforms_submissions_query')) {
	function kforms_submissions_query($limit = 25, $page = 1) {
		global $wpdb;
		if($page > 1) {
			$offset = $limit * ($page - 1);
		} else {
			$offset = 0;
		}
		$sql = "SELECT * FROM $wpdb->kforms_submissions WHERE 1 = 1 LIMIT $offset, $limit";
		return $wpdb->get_results($sql);
	}
}

if(!function_exists('kforms_submissions_export')) {
	add_action('wp_ajax_kforms_submissions_export', 'kforms_submissions_export');
	function kforms_submissions_export() {
		global $wpdb, $kforms_core_fields;
		$headers = array_keys($kforms_core_fields);
		$headers = array_merge($headers, array(
			'submission_date',
			'form_id',
			'source',
			'referrer',
			'ip'
		));
		array_unshift($headers, 'ID');
		$where = "1 = 1";
		if(!empty($_GET['form_id'])) {
			$form_id = filter_input(INPUT_GET, 'form_id', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
			$where = "`Submission`.`form_id` = '".$form_id."'";
		}
		$results = $wpdb->get_results("SELECT * FROM `".$wpdb->kforms_submissions."` AS `Submission` WHERE ".$where." ORDER BY `Submission`.`submission_date` DESC", ARRAY_A);
		if($results) {
			foreach($results as $key => $result) {
				$meta = kforms_get_submission_meta($result['ID']);
				if(!empty($meta)) {
					foreach($meta as $meta_key => $meta_vals) {
						$results[$key][$meta_key] = $meta_vals[0];
						if(!in_array($meta_key, $headers)) {
							$headers[] = $meta_key;
						}
					}
				}
			}
			array_unshift($results, $headers); 
			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=submissions_export.csv");
			header("Pragma: no-cache");
			header("Expires: 0");
			kforms_outputCSV($results);
			die();
		} else {
			die('No results');
		}
	}
}

if(!function_exists('kforms_outputCSV')) {
	function kforms_outputCSV($data) {
	    $outstream = fopen("php://output", "w");
	    function __outputCSV(&$vals, $key, $filehandler) {
	        fputcsv($filehandler, $vals); // add parameters if you want
	    }
	    array_walk($data, "__outputCSV", $outstream);
	    fclose($outstream);
	}
}

if(!function_exists('kforms_submissions_count')) {
	function kforms_submissions_count() {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM $wpdb->kforms_submissions";
		return $wpdb->get_var($wpdb->prepare($sql));
	}
}

/**
 * CGeorges: Adding widget support
 */

class KForms_Widget extends WP_Widget {
	function __construct() {
		parent::WP_Widget( /* Base ID */'kforms_widget', /* Name */'KForms Widget', array( 'description' => '(K)Forms Widget to show forms in sidebar' ) );
	}

	function form($instance) {
		// outputs the options form on admin
		if ( $instance ) {
			$title = esc_attr( $instance[ 'title' ] );
			$form_id = esc_attr( $instance[ 'contact_list' ] );
		}
		else {
			$title = _e( 'New title' );
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		
		<?php 
		echo "<p><label for='form_select'>"._e('Form:')."</label> <select id='form_select' class='widefat' name='".$this->get_field_name('contact_list')."'>";
		$list = kforms_list();
		
		foreach ($list as $id => $item)
		{
			echo "<option value='".$item['ID']."' ".( (isset($form_id) && $form_id==$item['ID'])?' selected=\'selected\'':'' ).">".$item['name']."</option>";
		}
		echo "</select></p>";
		
	}

	function update($new_instance, $old_instance) {
		// processes widget options to be saved
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['contact_list'] = strip_tags($new_instance['contact_list']);
		return $instance;
	}

	function widget($args, $instance) {
		// outputs the content of the widget
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title; 

		echo kforms_tag(array("id" => $instance['contact_list']));
		
		echo $after_widget;
	}

}
add_action( 'widgets_init', create_function( '', 'register_widget("KForms_Widget");' ) );
?>