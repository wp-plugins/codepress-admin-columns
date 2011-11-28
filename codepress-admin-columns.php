<?php
/*
Plugin Name: 		Codepress Admin Columns
Version: 			1.0.1
Description: 		This plugin makes it easy to customize Admin Columns for your Posts, Pages and Custom Post Type Screens. 
Author: 			Tobias Schutter
Author URI: 		http://www.codepress.nl
Plugin URI: 		http://www.codepress.nl/plugins/codepress-admin-columns/
Text Domain: 		codepress-admin-columns
Domain Path: 		/languages
License:			GPLv2

Copyright 2011  Codepress  info@codepress.nl

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'CPAC_VERSION', '1.0.1' );

/**
 * Init Class
 *
 * @since     1.0
 */
$cpac = new Codepress_Admin_Columns;

/**
 * Advanced Admin Columns Class
 *
 * @since     1.0
 *
 */
class Codepress_Admin_Columns 
{	
	private $post_types, 
			$options, 
			$options_default, 
			$slug,
			$textdomain;
	
	/**
	 * Construct
	 *
	 * @since     1.0
	 */
	function __construct()
	{	
		add_action( 'wp_loaded', array( &$this, 'init') );		
	}
	
	/**
	 * Initilize plugin.
	 *
	 * Loading sequence is determined and intialized.
	 *
	 * @since     1.0
	 */
	function init()
	{			
		// vars
		$this->post_types 		= $this->get_post_types();
		$this->handle_requests();		
		$this->options 			= get_option('cpac_options');		
		$this->options_default 	= get_option('cpac_options_default');				
		
		// slug
		$this->slug				= 'codepress-admin-columns';
		$this->textdomain		= 'codepress-admin-columns';
		
		// translations
		load_plugin_textdomain( $this->textdomain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		// Actions and hooks
		add_action( 'admin_menu', array( &$this, 'settings_menu') );
		add_action( 'admin_init', array( &$this, 'register_settings') );
		add_action( 'admin_init', array( &$this, 'register_columns' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts') );
		add_action( 'manage_pages_custom_column', array( &$this, 'manage_column_value'), 10, 2 );	
		add_action( 'manage_posts_custom_column', array( &$this, 'manage_column_value'), 10, 2 );
		add_action( 'admin_print_styles' , array( &$this, 'column_styles') );
		add_filter( 'request', array( &$this, 'handle_requests_orderby_column') );
		add_filter( 'plugin_action_links',  array( &$this, 'add_settings_link'), 10, 2);				
	}

	/**
	 * Admin Menu.
	 *
	 * Create the admin menu link for the settings page.
	 *
	 * @since     1.0
	 */
	public function settings_menu() 
	{
		$page = add_options_page(
			// Page title
			esc_html__( 'Admin Columns Settings', $this->textdomain ), 
			// Menu Title
			esc_html__( 'Admin Columns', $this->textdomain ), 
			// Capability
			'manage_options',
			// Menu slug
			$this->slug,
			// Callback
			array( &$this, 'plugin_settings_page')
		);		

		// css scripts
		add_action( "admin_print_styles-$page", array( &$this, 'admin_styles') );
	}	
	
	/**
	 * Add Settings link to plugin page
	 *
	 * @since     1.0
	 */
	function add_settings_link( $links, $file ) 
	{
		if ( $file != plugin_basename( __FILE__ ))
			return $links;

		array_unshift($links, '<a href="' . admin_url("admin.php") . '?page=' . $this->slug . '">' . __( 'Settings' ) . '</a>');
		return $links;
	}	
		
	/**
	 *	Register Columns
	 *
	 * 	@since     1.0
	 */
	public function register_columns()
	{	
		foreach ( $this->post_types as $post_type ) {
			
			// register column per post type
			add_filter("manage_edit-{$post_type}_columns", array(&$this, 'callback_set_column'));
					
			// register column as sortable
			add_filter( "manage_edit-{$post_type}_sortable_columns", array(&$this, 'callback_set_sortable_column'));
		}
	}
	
	/**
	 *	Callback Set Column
	 *
	 * 	@since     1.0
	 */
	public function callback_set_column($columns) 
	{	
		global $post_type;
		$columns = $this->set_column($columns, $post_type);
		
		return $columns;
	}
	
	/**
	 *	Callback Set Sortable Column
	 *
	 * 	@since     1.0
	 */
	public function callback_set_sortable_column($columns) 
	{	
		global $post_type;
		$columns = $this->set_sortable_filter($columns, $post_type);
					
		return $columns;
	}
	
	/**
	 * Settings Page Template.
	 *
	 * This function in conjunction with others usei the WordPress
	 * Settings API to create a settings page where users can adjust
	 * the behaviour of this plugin. 
	 *
	 * @since     1.0
	 */
	public function plugin_settings_page() 
	{
		// loop through post types
		$rows = '';
		foreach ( $this->post_types as $post_type ) {
			
			// post type label
			$label = $this->get_singular_name($post_type);
					
			// id
			$id = $this->sanitize_string($post_type); 
			
			// build draggable boxes
			$boxes = $this->get_column_options($post_type);
			
			// class
			$class = $this->is_menu_type_current($post_type) ? ' current' : ' hidden';
			
			$rows .= "
				<tr id='cpac-box-{$id}' valign='top' class='cpac-box-row{$class}'>
					<th class='cpac_post_type' scope='row'>
						{$label}
					</th>
					<td>
						<h3 class='cpac_post_type hidden'>{$label}</h3>
						{$boxes}
					</td>
				</tr>
			";
		}
		
		// Post Type Menu
		$menu = $this->get_post_type_menu();
		
	?>
		<div id="cpac" class="wrap">
			<?php screen_icon($this->slug) ?>
			<h2><?php _e('Codepress Admin Columns', $this->textdomain); ?></h2>
			<?php echo $menu ?>
			<div class="postbox-container" style="width:70%;">
				<div class="metabox-holder">	
					<div class="meta-box-sortables">
					
						<div id="general-cpac-settings" class="postbox">
							<div title="Click to toggle" class="handlediv"><br></div>
							<h3 class="hndle">
								<span><?php _e('Admin Columns', $this->textdomain ); ?></span>
							</h3>
							<div class="inside">
								<form method="post" action="options.php">
								
								<?php settings_fields( 'cpac-settings-group' ); ?>
								
								<table class="form-table">
								
									<?php echo $rows ?>								
									
									<tr class="bottom" valign="top">
										<th scope="row"></th>
										<td>
											<p class="submit">
												<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
											</p>
										</td>
									</tr>				
								</table>			
								</form>						
							</div>						
						</div><!-- general-settings -->
						
						<div id="restore-cpac-settings" class="postbox">
							<div title="Click to toggle" class="handlediv"><br></div>
							<h3 class="hndle">
								<span><?php _e('Restore defaults', $this->textdomain) ?></span>
							</h3>
							<div class="inside">
								<form method="post" action="">					
									<input type="submit" class="button" name="cpac-restore-defaults" value="<?php _e('Restore default settings', $this->textdomain ) ?>" onclick="return confirm('<?php _e("Warning! ALL saved admin columns data will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", $this->textdomain); ?>');" />
								</form>
								<p class="description"><?php _e('This will delete all column settings and restore the default settings.', $this->textdomain); ?></p>
							</div>
						</div><!-- restore-cpac-settings -->
					
					</div>
				</div>
			</div><!-- .postbox-container -->
			
			<div class="postbox-container" style="width:20%;">
				<div class="metabox-holder">	
					<div class="meta-box-sortables">
					
						<div id="side-cpac-settings" class="postbox">
							<div title="Click to toggle" class="handlediv"><br></div>
							<h3 class="hndle">
								<span><?php _e('Need support?', $this->textdomain) ?></span>
							</h3>
							<div class="inside">
								<p><?php printf(__('If you are having problems with this plugin, please talk about them in the <a href="%s">Support forums</a>.', $this->textdomain), 'http://wordpress.org/tags/codepress-admin-columns' );?></p>
								<p><?php printf(__("If you're sure you've found a bug, or have a feature request, please <a href='%s'>submit your feedback</a>.", $this->textdomain), 'http://www.codepress.nl/plugins/codepress-admin-columns#feedback');?></p>
							</div>
						</div><!-- side-cpac-settings -->
					
					</div>
				</div>
			</div><!-- .postbox-container -->
			
		</div>
	<?php
	}

	/**
	 * Get a list of Column options per post type
	 *
	 * @since     1.0
	 */
	private function get_column_options($post_type) 
	{	
		// merge all columns
		$display_columns 	= $this->get_merged_columns($post_type);
		
		// define
		$list = '';	
		
		// loop throught the active columns
		if ( $display_columns ) {
			foreach ( $display_columns as $key => $values ) {		
				
				// add items to the list
				$list .= $this->get_box($post_type, $key, $values);
			
			}
		}
		
		// custom field button
		$button_add_column = '';
		if ( $this->get_postmeta_by_posttype($post_type) )
			$button_add_column = "<a href='javacript:;' class='cpac-add-customfield-column button'>+ " . __('Add Custom Field Column') . "</a>";
		
		return "
			<div class='cpac-box'>
				<ul class='cpac-option-list'>
					{$list}			
				</ul>
				{$button_add_column}
				<div class='cpac-reorder-msg'></div>		
			</div>
			";
	}

	/**
	 * Get merged columns
	 *
	 * @since     1.0
	 */
	private function get_merged_columns($post_type) 
	{	
		//get saved database columns
		$db_columns 		= $this->get_db_columns($post_type);
		
		// get wp default columns
		$wp_default_columns = $this->get_wp_default_columns($post_type);
		
		// get custom columns
		$wp_custom_columns 	= $this->get_custom_columns($post_type);
		
		// merge wp default and custom columns
		$default_columns = wp_parse_args($wp_custom_columns, $wp_default_columns);
		
		// loop throught the active columns
		if ( $db_columns ) {
			foreach ( $db_columns as $key => $values ) {
			
				// get column meta options from custom columns
				if ( strpos($key, 'column-meta-') !== false )
					$db_columns[$key]['options'] = $wp_custom_columns['column-meta-1']['options'];			
				
				// add static options
				else	
					$db_columns[$key]['options'] = $default_columns[$key]['options'];
				
				unset($default_columns[$key]);			
			}
		}	
		
		// merge all
		$display_columns = wp_parse_args($db_columns, $default_columns);

		return $display_columns;		
	}


	/**
	 * Get checkbox
	 *
	 * @since     1.0
	 */
	private function get_box($post_type, $key, $values) 
	{
		$classes = array();

		// set state
		$state 	= isset($values['state']) ? $values['state'] : '';
		
		// set sortorder
		$sortorder 	= isset($values['sortorder']) && $values['sortorder'] == 'on' ? 'on' : '';
		
		// class
		$classes[] = "cpac-box-{$key}";
		if ( $state )
			$classes[] = 'active';
		if ( ! empty($values['options']['class']) )
			$classes[] = $values['options']['class'];
		$class = implode(' ', $classes);
			
		// more box options	
		$more_options 	= $this->get_additional_box_options($post_type, $key, $values);
		$action 		= "<a class='cpac-action' href='#open'>open</a>";
		
		// hide box options
		if ( ! empty($values['options']['hide_options']) ) {
			$action = $more_options = '';
		}
		
		$list = "
			<li class='{$class}'>
				<div class='cpac-sort-handle'></div>			
				<div class='cpac-type-options'>
					
					<div class='cpac-checkbox'></div>
					<input type='hidden' class='cpac-state' name='cpac_options[columns][{$post_type}][{$key}][state]' value='{$state}'/>				
					<input type='hidden' name='cpac_options[columns][{$post_type}][{$key}][sortorder]' value='{$sortorder}'/>
					<label class='main-label'>{$values['label']}</label>								
				</div>
				<div class='cpac-meta-title'>
					{$action}
					<span>{$values['options']['type_label']}</span>
				</div>
				<div class='cpac-type-inside'>				
					<label for='cpac_options[columns][{$post_type}][{$key}][label]'>Label: </label>
					<input type='text' name='cpac_options[columns][{$post_type}][{$key}][label]' value='{$values['label']}' class='text'/>
					<br/>
					{$more_options}
				</div>
			</li>
		";
		
		return $list;
	}

	/**
	 * Get additional box option fields
	 *
	 * @since     1.0
	 */
	private function get_additional_box_options($post_type, $key, $values) 
	{
		$fields = '';
		
		// Custom Fields	
		if ( strpos($key, 'column-meta-') !== false )
			$fields .= $this->get_box_options_customfields($post_type, $key, $values);
		
		return $fields;
	}

	/**
	 * Box Options: Custom Fields
	 *
	 * @since     1.0
	 */
	private function get_box_options_customfields($post_type, $key, $values) 
	{
		// get post meta fields	
		$fields = $this->get_postmeta_by_posttype($post_type);
		
		if ( empty($fields) ) 
			return false;
		
		// set meta field options
		$current = ! empty($values['field']) ? $values['field'] : '' ;
		$field_options = '';
		foreach ($fields as $field) {	
			$field_options .= sprintf
			(
				'<option value="%s"%s>%s</option>',
				$field,
				$field == $current? ' selected="selected"':'',
				$field
			);		
		}
		
		// set meta fieldtype options
		$currenttype = ! empty($values['field_type']) ? $values['field_type'] : '' ;
		$fieldtype_options = '';
		$fieldtypes = array(
			''				=> __('Default'),
			'image'			=> __('Image'),
			'library_id'	=> __('Media Library Icon', $this->textdomain),
			'excerpt'		=> __('Excerpt'),
			'array'			=> __('Multiple Values', $this->textdomain),
		);
		
		// add filters
		$fieldtypes = apply_filters('cpac-field-types', $fieldtypes );	
		foreach ( $fieldtypes as $fkey => $fieldtype ) {
			$fieldtype_options .= sprintf
			(
				'<option value="%s"%s>%s</option>',
				$fkey,
				$fkey == $currenttype? ' selected="selected"':'',
				$fieldtype
			);
		}
		
		if ( empty($field_options) )
			return false;
		
		// add remove button
		$remove = '<p class="remove-description description">'.__('This field can not be removed', $this->textdomain).'</p>';
		if ( $key != 'column-meta-1') {
			$remove = "
				<p>
					<a href='javascript:;' class='cpac-delete-custom-field-box'>".__('Remove')."</a>
				</p>
			";
		}
		
		$inside = "
			<label for='cpac_options[columns][{$post_type}][{$key}][field]'>Custom Field: </label>
			<select name='cpac_options[columns][{$post_type}][{$key}][field]'>{$field_options}</select>
			<br/>
			<label for='cpac_options[columns][{$post_type}][{$key}][field_type]'>Field Type: </label>
			<select name='cpac_options[columns][{$post_type}][{$key}][field_type]'>{$fieldtype_options}</select>
			<br/>
			{$remove}
		";
		
		return $inside;
	}

	/**
	 * Get post meta fields by post type
	 *
	 * @since     1.0
	 */
	private function get_postmeta_by_posttype($post_type) 
	{
		global $wpdb;
		// get mata fields
		$sql 	= 'SELECT DISTINCT meta_key	FROM '.$wpdb->postmeta.' pm	JOIN '.$wpdb->posts.' p	ON pm.post_id = p.ID WHERE p.post_type = "' . mysql_real_escape_string($post_type) . '" ORDER BY 1';
		$fields = $wpdb->get_results($sql, ARRAY_N);	
		
		// postmeta	
		if ( $fields ) {
			$meta_fields = array();
			foreach ($fields as $field) {
				// filter out hidden meta fields
				if (substr($field[0],0,1) != "_") {
					$meta_fields[] = $field[0];
				}
			}
			
			return $meta_fields;
		}	
		
		return false;
	}

	/**
	 * Register admin scripts
	 *
	 * @since     1.0
	 */
	public function admin_enqueue_scripts() 
	{
		wp_enqueue_script( 'dashboard' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'cpac-admin', $this->plugin_url('/assets/js/admin-column.js'), array('jquery', 'jquery-ui-sortable'), CPAC_VERSION );		
	}	

	/**
	 * Get post types
	 *
	 * @since     1.0
	 */
	private function get_post_types()
	{
		$post_types = get_post_types(array(
			'_builtin' => false
		));
		$post_types['post'] = 'post';
		$post_types['page'] = 'page';
		
		return $post_types;
	}

	/**
	 * Register admin css
	 *
	 * @since     1.0
	 */
	public function admin_styles()
	{
		wp_enqueue_style( 'cpac-admin', $this->plugin_url('/assets/css/admin-column.css'), array(), CPAC_VERSION, 'all' );	
	}
	
	/**
	 * Register column css
	 *
	 * @since     1.0
	 */
	public function column_styles()
	{
		wp_enqueue_style( 'cpac-columns', $this->plugin_url('/assets/css/column.css'), array(), CPAC_VERSION, 'all' );	
	}

	/**
	 * Register plugin options
	 *
	 * @since     1.0
	 */
	public function register_settings() 
	{
		// If we have no options in the database, let's add them now.
		if ( false === $this->options )
			add_option( 'cpac_options', array(&$this, 'get_default_plugin_options') );
		
		register_setting( 'cpac-settings-group', 'cpac_options', array(&$this, 'options_callback') );
	}	

	/**
	 * Returns the default plugin options.
	 *
	 * @since     1.0
	 */
	public function get_default_plugin_options() 
	{
		$default_plugin_options = array(		
			'post'	=> '',
			'page'	=> ''
		);
		return apply_filters( 'cpac_default_plugin_options', $default_plugin_options );
	}

	/**
	 * Save geocode coordinates of focus location.
	 *
	 * @since     1.0
	 */
	public function options_callback($options)
	{	
		return $options;
	}

	/**
	 * Handle requests.
	 *
	 * @since     1.0
	 */
	private function handle_requests() 
	{	
		// settings updated
		if ( ! empty($_REQUEST['settings-updated']) ) {
			
			// stores the default columns that are set by WP or set in the theme.
			$wp_default_columns = array();
			foreach ( $this->post_types as $post_type ) {
				$wp_default_columns[$post_type] = $this->get_wp_default_columns($post_type);
			}
			update_option( 'cpac_options_default', $wp_default_columns );
		}		
		
		// restore defaults 
		if ( ! empty($_REQUEST['cpac-restore-defaults']) ) {
			$this->restore_defaults();
		}	
	}

	/**
	 * Restore defaults
	 *
	 * @since     1.0
	 */
	private function restore_defaults() 
	{	
		delete_option( 'cpac_options' );
		delete_option( 'cpac_options_default' );
	}

	/**
	 * Returns excerpt
	 *
	 * @since     1.0
	 */
	private function get_post_excerpt($post_id, $charlength = 100) 
	{
		global $post;  
		$save_post 	= $post;
		$post 		= get_post($post_id);
		$excerpt 	= get_the_excerpt();
		$post 		= $save_post;
		
		$output = $this->get_shortened_string($excerpt, $charlength );	
		
		return $output;
	}

	/**
	 * Returns shortened string
	 *
	 * @since     1.0
	 */
	private function get_shortened_string($string = '', $charlength = 100) 
	{
		if (!$string)
			return false;
		
		$output = '';
		if ( strlen($string) > $charlength ) {
			$subex 		 = substr($string,0,$charlength-5);
			$exwords 	 = explode(" ",$subex);
			$excut 		 = -(strlen($exwords[count($exwords)-1]));			
			$output 	.= $excut < 0 ? substr($subex,0,$excut) : $subex;			
			$output 	.= "[...]";
		} else {
			$output = $string;
		}
		return $output;
	}

	/**
	 * Manage custom column for Post Types.
	 *
	 * @since     1.0
	 */
	public function manage_column_value($key, $post_id) 
	{
		$type = $key;

		// Check for taxonomies, such as column-taxonomy-[taxname]	
		if ( strpos($type, 'column-taxonomy-') !== false )
			$type = 'column-taxonomy';
		
		// Check for custom fields, such as column-meta-[customfieldname]
		if ( strpos($type, 'column-meta-') !== false )
			$type = 'column-meta';
		
		// Hook 
		do_action('cpac-manage-column', $type, $key, $post_id);
		
		// Switch Types
		$result = '';
		switch ($type) :			
			
			// Post ID
			case "column-postid" :
				$result = $post_id;
				break;
			
			// Excerpt
			case "column-excerpt" :
				$result = $this->get_post_excerpt($post_id);
				break;
			
			// Featured Image
			case "column-featured_image" :
				$result = get_the_post_thumbnail($post_id, array(80,80));			
				break;
				
			// Sticky Post
			case "column-sticky" :
				if ( is_sticky($post_id) ) {		
					$src 	= $this->plugin_url('assets/images/checkmark.png');
					$result = "<img alt='sticky' src='{$src}' />";
				}
				break;
			
			// Order
			case "column-order" :
				$result = get_post_field('menu_order', $post_id);			
				break;
				
			// Post Formats
			case "column-post_formats" :
				$result = get_post_format($post_id);			
				break;
			
			// Page template
			case "column-page-template" :
				// file name
				$page_template 	= get_post_meta($post_id, '_wp_page_template', true);			
				// all page templates
				$templates 		= get_page_templates();
				// template name
				$result = array_search($page_template, $templates);			
				break;
			
			// Slug
			case "column-page-slug" :
				$result = get_post($post_id)->post_name;			
				break;
			
			// Taxonomy
			case "column-taxonomy" :
				$tax 	= str_replace('column-taxonomy-','',$key);
				$tags 	= get_the_terms($post_id, $tax);
				$tarr 	= array();
				if ( $tax == 'post_format' && empty($tags) ) {
					$result = __('Standard');
				}
				elseif ( !empty($tags) ) {
					foreach($tags as $tag) {				
						$tarr[] = $tag->name;	
					}
					$result = implode(', ', $tarr);
				}			
				break;
			
			// Custom Field
			case "column-meta" :
				$result = $this->get_column_value_custom_field($post_id, $key);		
				break;
			
			// Attachment
			case "column-attachment" :
				$result = $this->get_column_value_attachments($post_id);
				break;
			
			default :
				$result = get_post_meta( $post_id, $key, true );
						
		endswitch;
		
		if ( empty($result) )
			echo '&nbsp;';
		
		echo $result;	
	}		

	/**
	 *	Get column value of post attachments
	 *
	 * 	@since     1.0
	 */
	private function get_column_value_attachments($post_id) 
	{
		$result = '';
		$attachments = get_posts(array(
			'post_type' 	=> 'attachment',
			'numberposts' 	=> -1,
			'post_status' 	=> null,
			'post_parent' 	=> $post_id
		));
		if ( $attachments ) {
			foreach ( $attachments as $attach ) {
				$result .= wp_get_attachment_image( $attach->ID, array(80,80), true );
			}
		}
		return $result;
	}
	
	/**
	 *	Get column value of Custom Field
	 *
	 * 	@since     1.0
	 */
	private function get_column_value_custom_field($post_id, $key) 
	{
		$columns 	= $this->get_db_columns( get_post_type($post_id) );
		$field	 	= isset($columns[$key]['field']) ? $columns[$key]['field'] : '';
		$fieldtype	= isset($columns[$key]['field_type']) ? $columns[$key]['field_type'] : '';
		
		// Get meta field value
		$meta 	 	= get_post_meta($post_id, $field, true);
		
		// multiple meta values
		if ( ( $fieldtype == 'array' && is_array($meta) ) || is_array($meta) ) {			
			$meta 	= get_post_meta($post_id, $field);
			$meta 	= $this->recursive_implode(', ', $meta);
		}
		
		// make sure there are no serialized arrays or empty meta data
		if ( empty($meta) || !is_string($meta) )	
			return false;
					
		// handles each field type differently..
		switch ($fieldtype) :			
		
			// Image
			case "image" :				
				$meta = $this->get_thumbnail($meta);
				break;
				
			// Media Library ID
			case "library_id" :			
				// check if media exists
				$meta = wp_get_attachment_url($meta) ? wp_get_attachment_image( $meta, array(80,80), true ) : '';				
				break;
			
			// Excerpt
			case "excerpt" :
				$meta = $this->get_shortened_string($meta, 100);
				break;
								
		endswitch;		
		
		return $meta;
	}

	/**
	 *	Implode for multi dimensional array
	 *
	 * 	@since     1.0
	 */
	private function recursive_implode( $glue, $pieces ) 
	{
		foreach( $pieces as $r_pieces )	{
			if( is_array( $r_pieces ) ) {
				$retVal[] = $this->recursive_implode( $glue, $r_pieces );
			}
			else {
				$retVal[] = $r_pieces;
			}
		}
		return implode( $glue, $retVal );
	}

	/**
	 *	Set Columns for Registering
	 *
	 * 	@since     1.0
	 */
	private function set_column($columns, $post_type) 
	{
		$db_columns	= $this->get_db_columns($post_type);
		
		if ( !$db_columns )
			return $columns;

		// set already loaded columns by plugins
		$set_columns = $this->filter_preset_columns($columns, $post_type);
				
		// loop through columns
		foreach ( $db_columns as $key => $values ) {
			
			// is active
			if ( isset($values['state']) && $values['state'] == 'on' ){				
				
				// register format
				$set_columns[$key] = $values['label'];				
			}
		}
		return $set_columns;		
	}

	/**
	 * Set columns. These columns apply either for every post or set by a plugin.
	 *
	 * @since     1.0
	 */
	private function filter_preset_columns($columns, $post_type = 'post') 
	{
		$options 	= $this->options_default;
		
		if ( !$options )
			return $columns;
		
		// we use the wp default columns for filtering...
		$db_columns 	= $options[$post_type];		
		
		// ... the ones that are set by plugins, theme functions and such.
		$dif_columns 	= array_diff(array_keys($columns), array_keys($db_columns));
		
		// we add those to the columns
		$pre_columns = array();
		if ( $dif_columns ) {
			foreach ( $dif_columns as $column ) {
				$pre_columns[$column] = $columns[$column];
			}
		}
		
		return $pre_columns;
	}

	/**
	 *	Set sortable columns
	 *
	 * 	@since     1.0
	 */
	private function set_sortable_filter($columns, $post_type) 
	{
		$db_columns	= $this->get_db_columns($post_type);
			
		if ( !$db_columns )
			return $columns;
		
		// loop through columns
		foreach ( $db_columns as $key => $values ) {
			
			// is active
			if ( isset($values['sortorder']) && $values['sortorder'] == 'on' ){				
				
				// register format
				$columns[$key] = $this->sanitize_string($values['label']);			
			}
		}	
		return $columns;
	}		

	/**
	 * 	Get WP default supported admin columns per post type.
	 *
	 * 	@since     1.0
	 */
	private function get_wp_default_columns($post_type = 'post') 
	{
		// load some dependencies
		require_once(ABSPATH . 'wp-admin\includes\template.php');
		require_once(ABSPATH . 'wp-admin\includes\class-wp-list-table.php');
		require_once(ABSPATH . 'wp-admin\includes\class-wp-posts-list-table.php');
		
		// we need to change the current screen
		global $current_screen;
		$org_current_screen = $current_screen;
		
		// overwrite current_screen global with our post type of choose...
		$current_screen->post_type = $post_type;
		
		// ...so we can get its columns
		$columns = WP_Posts_List_Table::get_columns();
		
		// we remove the checkbox column as an option... 
		unset($columns['cb']);
		
		// change to uniform format
		$uniform_columns = array();
		foreach ( $columns as $key => $label ) {
			$hide_options 	= false;
			$type_label 	= $label;
			
			// comment exception				
			if ( strpos( $label, 'comment-grey-bubble.png') ) {
				$type_label 	= __('Comments', $this->textdomain);
				$hide_options 	= true;
			}

			$uniform_colums[$key] = array(
				'label'			=> $label,
				'state'			=> 'on',		
				'options'		=> array(
					'type_label' 	=> $type_label,
					'hide_options'	=> $hide_options,
					'class'			=> 'cpac-box-wp-native',
				)
			);
		}
		
		// reset current screen
		$current_screen = $org_current_screen;
		
		return $uniform_colums;
	}

	/**
	 * Add extra columns
	 *
	 * @since     1.0
	 */
	private function get_custom_columns($post_type) 
	{
		$custom_columns = array();
		
		// default arguments
		$defaults = array(		
			'label'			=> '',
			'sortorder'		=> '',
			'state' 		=> '',
			
			// options are static
			'options'		=> array(
				'type_label' 	=> __('Custom', $this->textdomain),
				'hide_options'	=> false,
				'class'			=> 'cpac-box-custom',
			)
		);	
		
		// Thumbnail support
		if ( post_type_supports($post_type, 'thumbnail') ) {
			$custom_columns['column-featured_image'] = wp_parse_args( array(
				'label'			=> __('Featured Image', $this->textdomain),
				'options'		=> array(
					'type_label' 	=> __('Image', $this->textdomain)
				)
			), $defaults);
		}
		
		// Excerpt support
		if ( post_type_supports($post_type, 'editor') ) {
			$custom_columns['column-excerpt'] = wp_parse_args( array(
				'label'			=> __('Excerpt', $this->textdomain),
				'options'		=> array(
					'type_label' 	=> __('Excerpt', $this->textdomain)
				)
			), $defaults);
		}
		
		// Sticky support
		if ( $post_type == 'post' ) {		
			$custom_columns['column-sticky'] = wp_parse_args( array(
				'label'			=> __('Sticky', $this->textdomain),
				'options'		=> array(
					'type_label' 	=> __('Sticky', $this->textdomain)
				)
			), $defaults);
		}
		
		// Order support
		if ( post_type_supports($post_type, 'page-attributes') ) {
			$custom_columns['column-order'] = wp_parse_args( array(
				'label'			=> __('Page Order', $this->textdomain),
				'sortorder'		=> 'on',
				'options'		=> array(
					'type_label' 	=> __('Order', $this->textdomain)
				)			
			), $defaults);
		}
		
		// Page Template
		if ( $post_type == 'page' ) { 
			$custom_columns['column-page-template'] = wp_parse_args( array(
				'label'			=> __('Page Template', $this->textdomain),
				'sortorder'		=> 'on',
				'options'		=> array(
					'type_label' 	=> __('Page Template', $this->textdomain)
				)
			), $defaults);	
		}
		
		// Post Formats
		if ( post_type_supports($post_type, 'post-formats') ) {
			$custom_columns['column-post_formats'] = wp_parse_args( array(
				'label'			=> __('Post Format', $this->textdomain),
				'options'		=> array(
					'type_label' 	=> __('Post Format', $this->textdomain)
				)
			), $defaults);
		}
		
		// Taxonomy support
		$taxonomies = get_object_taxonomies($post_type, 'objects');
		if ( $taxonomies ) {
			foreach ( $taxonomies as $tax_slug => $tax ) {
				if ( $tax_slug != 'post_tag' && $tax_slug != 'category' && $tax_slug != 'post_format' ) {
					$custom_columns['column-taxonomy-'.$tax->name] = wp_parse_args( array(
						'label'			=> $tax->label,
						'options'		=> array(
							'type_label'	=> __('Taxonomy', $this->textdomain)
						)
					), $defaults);				
				}
			}
		}
		
		// Post ID support
		$custom_columns['column-postid'] = wp_parse_args( array(
			'label'			=> 'ID',		
			'sortorder'		=> 'on',
			'options'		=> array(
				'type_label' 	=> 'ID',
			)
		), $defaults);
		
		// Slug support
		$custom_columns['column-page-slug'] = wp_parse_args( array(
			'label'			=> __('Slug', $this->textdomain),		
			'sortorder'		=> 'on',
			'options'		=> array(
				'type_label' 	=> __('Slug', $this->textdomain),
			)
		), $defaults);
		
		// Attachment support
		$custom_columns['column-attachment'] = wp_parse_args( array(
			'label'			=> __('Attachment', $this->textdomain),
			'sortorder'		=> 'on',
			'options'		=> array(
				'type_label' 	=> __('Attachment', $this->textdomain)
			)
		), $defaults);
		
		// Custom Field support
		if ( $this->get_postmeta_by_posttype($post_type) ) {
			$custom_columns['column-meta-1'] = wp_parse_args( array(
				'label'			=> __('Custom Field', $this->textdomain),
				'field'			=> '',
				'field_type'	=> '',
				'options'		=> array(
					'type_label'	=> __('Field', $this->textdomain),
					'class'			=> 'cpac-box-metafield'
				)			
			), $defaults);
		}	
		
		return apply_filters('cpac-custom-columns', $custom_columns);
	}

	/**
	 * Admin requests for orderby column
	 *
	 * @since     1.0
	 */
	private function get_db_columns($post_type)
	{ 
		// get plugin options
		$options 		= $this->options;

		// get saved columns
		if ( isset($options['columns'][$post_type]) )
			return $options['columns'][$post_type];
		
		return false;
	}

	/**
	 * Post Type Menu
	 *
	 * @since     1.0
	 */
	private function get_post_type_menu() 
	{
		// set
		$menu 	= '';
		$count 	= 1;
		
		// referer
		$referer = '';
		if ( isset($_REQUEST['cpac_type']) && $_REQUEST['cpac_type'] )
			$referer = $_REQUEST['cpac_type'];
			
		// loop
		foreach ( $this->post_types as $post_type ) {
			$label 		 = $this->get_singular_name($post_type);
			$clean_label = $this->sanitize_string($post_type);
			
			// divider
			$divider 	= $count++ == 1 ? '' : ' | ';
			
			// current		
			$current = '';
			if ( $this->is_menu_type_current($post_type) )
				$current = ' class="current"';
			
			// menu list
			$menu .= "
				<li>{$divider}<a{$current} href='#cpac-box-{$clean_label}'>{$label}</a></li>
			";
		}

		return "
		<div class='cpac-menu'>
			<ul class='subsubsub'>
				{$menu}
			</ul>
		</div>
		";
	}

	/**
	 * Checks if menu type is currently viewed
	 *
	 * @since     1.0
	 */
	private function is_menu_type_current( $post_type ) 
	{
		//print_r($post_type);
	
		// referer
		$referer = '';
		if ( ! empty($_REQUEST['cpac_type']) )
			$referer = $_REQUEST['cpac_type'];
		
		// get label
		$label 		 = $this->get_singular_name($post_type);
		$clean_label = $this->sanitize_string($post_type);
		
		// get first element from post-types
		$first 		= array_shift(array_values($this->post_types));
		
		// display the page that was being viewed before saving
		if ( $referer ) {
			if ( $referer == 'cpac-box-'.$clean_label ) {
				return true;
			}
		
		// settings page has not yet been saved
		} elseif ( $first == $post_type  ) {
			return true;
		}
		
		return false;	
	}

	/**
	 * Get singular name of post type
	 *
	 * @since     1.0
	 */
	private function get_singular_name( $post_type ) 
	{
		$posttype_obj 	= get_post_type_object($post_type);
		$label 			= $posttype_obj->labels->singular_name;
		return $label;
	}

	/**
	 * Admin requests for orderby column
	 *
	 * @since     1.0
	 */
	public function handle_requests_orderby_column( $vars ) 
	{
		if ( isset( $vars['orderby'] ) ) {	
			// get saved columns
			$db_columns = $this->get_db_columns($vars['post_type']);
			
			// Column Page Order
			if ( isset($db_columns['column-order']) ) {
				
				// sanitizing label
				$label 		= $this->sanitize_string($db_columns['column-order']['label']);
				
				// Check for Page Order
				if ( $vars['orderby'] == $label ) {
					$vars['orderby'] = 'menu_order';
				}
			}
		} 
		return $vars;
	}	

	/**
	 * Sanitize label
	 *
	 * Uses intern wordpress function esc_url so it matches the label sorting url.
	 *
	 * @since     1.0
	 */
	private function sanitize_string($string) 
	{	
		$string = esc_url($string);
		return str_replace('http://','', $string);
	}
	
	/**
	 * Get a url to a file in this plugin.
	 *
	 * @since     1.0
	 */
	private function plugin_url( $file = '' ) 
	{		
		return plugins_url($file, __FILE__);
	}
	
	/**
	 * Get a thumbnail
	 *
	 * @since     1.0
	 */
	private function get_thumbnail( $image = '' ) 
	{		
		if ( empty($image) )
			return false;
			
		$image_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $image);
		$new		= image_resize( $image_path, 120, 80, true);
		
		if ( ! is_wp_error( $new ) )
			$image  = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $new);
			
		return "<img src='{$image}' alt='' width='120' height='80' />";
	}	
}
?>