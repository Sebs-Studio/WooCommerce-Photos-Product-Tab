<?php
/*
Plugin Name: WooCommerce Photos Product Tab
Plugin URI: http://www.sebs-studio.com/wp-plugins/woocommerce-photos-product-tab/
Description: Extends WooCommerce to allow you to display all images attached to a Product in a new tab on the single product page.
Version: 1.0
Author: Sebs Studio (Sebastien)
Author URI: http://www.sebs-studio.com
License: GPL2
*/

/*
	Copyright (C) 2012  Sebastien (email : sebastien@sebs-studio.com)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Plugin Name.
define('wc_photos_tab_plugin_name', 'WooCommerce Photos Product Tab');

// Plugin version.
define('wc_photos_tab_plugin_version', '1.0');

// Exit if accessed directly
if(!defined('ABSPATH')) exit;

// Required minimum version of WordPress.
if(!function_exists('woo_photos_tab_min_required')){
	function woo_photos_tab_min_required(){
		global $wp_version;
		$plugin = plugin_basename(__FILE__);
		$plugin_data = get_plugin_data(__FILE__, false);

		if(version_compare($wp_version, "3.3", "<")){
			if(is_plugin_active($plugin)){
				deactivate_plugins($plugin);
				wp_die("'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress Admin</a>.");
			}
		}
	}
	add_action('admin_init', 'woo_photos_tab_min_required');
}

// Checks if the WooCommerce plugins is installed and active.
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	if(!class_exists('WooCommerce_Photo_Product_Tab')){
		class WooCommerce_Photo_Product_Tab{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			private $tab_data = false;

			/**
			 * Gets things started by adding an action to 
			 * initialize this plugin once WooCommerce is 
			 * known to be active and initialized.
			 */
			public function __construct(){
				WooCommerce_Photo_Product_Tab::$plugin_prefix = 'wc_photos_tab_';
				WooCommerce_Photo_Product_Tab::$plugin_basefile = plugin_basename(__FILE__);
				WooCommerce_Photo_Product_Tab::$plugin_url = plugin_dir_url(WooCommerce_Photo_Product_Tab::$plugin_basefile);
				WooCommerce_Photo_Product_Tab::$plugin_path = trailingslashit(dirname(__FILE__));
				add_action('woocommerce_init', array(&$this, 'init'));

				$this->settings = array(
								array(
									'name' => __( 'Photos Product Tab', 'wc_photos_product_tab' ),
									'type' => 'title',
									'desc' => '',
									'id' => 'photos_product_tab'
								),
								array(  
									'name' => __('Size of Photos', 'wc_photos_product_tab'),
									'desc' 		=> __('What size would you like to display ?', 'wc_photos_product_tab'),
									'id' 		=> 'woocommerce_product_photo_tab_size',
									'type' 		=> 'select',
									'options'	=> array(
														'thumbnail' => __('Thumbnail', 'wc_photos_product_tab'),
														'medium'	=> __('Medium', 'wc_photos_product_tab'),
														'large'	=> __('Large', 'wc_photos_product_tab'),
														'full'	=> __('Full / Original', 'wc_photos_product_tab'),
													),
									'std'		=> 'thumbnail',
								),
								array(  
									'name' => __('Enable Lightbox', 'wc_photos_product_tab'),
									'desc' 		=> __('Enable WooCommerce lightbox for photos in the tab', 'wc_photos_product_tab'),
									'id' 		=> 'woocommerce_product_photo_tab_lightbox',
									'type' 		=> 'checkbox',
									'std'		=> '',
								),
								array(
									'type' => 'sectionend',
									'id' => 'photos_product_tab'
								),
							);

			}

			/**
			 * Init WooCommerce Photo Product Tab extension once we know WooCommerce is active
			 */
			public function init(){
				// backend stuff
				add_filter('plugin_row_meta', array(&$this, 'add_support_link'), 10, 2);
				// frontend stuff
				add_action('woocommerce_product_tabs', array(&$this, 'photos_product_tabs'), 25.5);
				add_action('woocommerce_product_tab_panels', array(&$this, 'photos_product_tabs_panel'), 25.5);
				// Settings
				add_action('woocommerce_settings_catalog_options_after', array(&$this, 'photo_tab_admin_settings'));
				add_action('woocommerce_update_options_catalog', array(&$this, 'save_photo_tab_admin_settings'));
				// Write panel
				add_action('woocommerce_product_options_general_product_data', array(&$this, 'write_photo_tab_panel'));
				add_action('woocommerce_process_product_meta', array(&$this, 'write_photo_tab_panel_save'));
			}

			/**
			 * Add donation link to plugin page.
			 */
			public function add_support_link($links, $file){
				if(!current_user_can('install_plugins')){
					return $links;
				}
				if($file == WooCommerce_Photo_Product_Tab::$plugin_basefile){
					$links[] = '<a href="http://www.sebs-studio.com/wp-plugins/woocommerce-extensions/" target="_blank">'.__('More WooCommerce Extensions', 'wc_photos_product_tab').'</a>';
				}
				return $links;
			}

			/**
			 * Write the photos tab on the product view page.
			 */
			public function photos_product_tabs(){
				global $post, $wpdb;

			/**
			 * Checks if any photos are attached to the product.
			 */
				$countPhotos = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->posts." WHERE `post_type`='attachment' AND `post_parent`='".$post->ID."'");

				if($countPhotos > 0 && get_post_meta($post->ID, 'woocommerce_disable_product_photos', true) != 'yes'){
					echo "<li class=\"gallery\"><a href=\"#photos-tab\">".__('Photos', 'wc_photos_product_tab')."</a></li>";
				}
			}

			/**
			 * Write the photos tab panel on the product view page.
			 */
			public function photos_product_tabs_panel(){
				global $post, $wpdb;

			/**
			 * Checks if any photos are attached to the product.
			 */
				$countPhotos = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->posts." WHERE `post_type`='attachment' AND `post_parent`='".$post->ID."'");

				if($countPhotos > 0 && get_post_meta($post->ID, 'woocommerce_disable_product_photos', true) != 'yes'){
					echo '<div class="panel entry-content" id="photos-tab">';
					echo '<h2>'.__('Photos', 'wc_photos_product_tab').'</h2>';
					$argsThumb = array(
						'order'			 => 'ASC',
						'post_type'		 => 'attachment',
						'numberposts'	 => -1,
						'post_parent'	 => $post->ID,
						'post_mime_type' => 'image',
						'post_status'	 => null
					);
					$attachments = get_posts($argsThumb);
					if($attachments){
					  echo '<ul style="list-style:none;">';
						foreach($attachments as $attachment){
							$photo_attr = array(
											'class'	=> "product-photo photo-attachment-".$attachment->ID."",
											'alt'   => trim( strip_tags( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ) ),
							);
							echo '<li style="float:left;">';
							if(get_option('woocommerce_product_photo_tab_lightbox') == 'yes'){ echo '<a href="'.wp_get_attachment_url($attachment->ID).'" rel="thumbnails" class="zoom">'; }
							echo wp_get_attachment_image($attachment->ID, get_option('woocommerce_product_photo_tab_size'), false, $photo_attr);
						  if(get_option('woocommerce_product_photo_tab_lightbox') == 'yes'){ echo '</a>'; }
							echo '</li>';
						}
					  echo '</ul>';
					}
					echo '</div>';
				}
			}

			// Adds a few settings to control the photos in the tab.
			function photo_tab_admin_settings() {
				global $settings;
				woocommerce_admin_fields( $this->settings );
			}

			function save_photo_tab_admin_settings() {
				woocommerce_update_options( $this->settings );
			}

			// Adds the option to disable the photo tab on the product page.
			function write_photo_tab_panel() {
		    	echo '<div class="options_group">';
		    	woocommerce_wp_checkbox( array( 'id' => 'woocommerce_disable_product_photos', 'label' => __('Disable photos tab?', 'wc_photos_product_tab') ) );
		  		echo '</div>';
		    }
		    
		    function write_photo_tab_panel_save( $post_id ) {
		    	$woocommerce_disable_product_photos = isset($_POST['woocommerce_disable_product_photos']) ? 'yes' : 'no';
		    	update_post_meta($post_id, 'woocommerce_disable_product_photos', $woocommerce_disable_product_photos);
		    }
		}
	}

	/* 
	 * Instantiate plugin class and add it to the set of globals.
	 */
	$woocommerce_photos_tab = new WooCommerce_Photo_Product_Tab();
}
else{
	add_action('admin_notices', 'wc_photos_tab_error_notice');
	function wc_photos_tab_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__(wc_photos_tab_plugin_name.' requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.').'</p></div>';
		}
	}
}
?>