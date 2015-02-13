<?php
/*
Plugin Name: Product Support for WooCommerce 
Plugin URI: http://themology.net/product/woocommerce-product-support
Description: Easily manage product support or build paid communities. Connect your products to BuddyPress Groups and bbPress Forums.
Author: Themology
Version: 1.0.0
Author URI: http://themology.net
*/

//Functions used by plugins
if ( ! class_exists( 'WC_Dependencies' ) )
	require_once 'wc-dependencies.php';

//WC Detection
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		return WC_Dependencies::woocommerce_active_check();
	}
}

// We need to wait until all plugins are loaded before we can continue so everything works right
add_action( 'plugins_loaded', 'themo_wcps_init' );

function themo_wcps_init() {

	// Stop here if WooCommerce isn't present
	if ( ! class_exists( 'WC_Settings_API' ) )
		return false;

	/**
	 * Integrate our plugin with the WooCommerce Settings API
	 *
	 * @class 		WC_Product_Support
	 * @extends		WC_Settings_API
	 */
	class WC_Product_Support extends WC_Settings_API {

		/**
		 * Initialize all our checks and integration points
		 *
		 * @access public
		 * @return void
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			// Define plugin constants
			$this->basename       = plugin_basename( __FILE__ );
			$this->directory_path = plugin_dir_path( __FILE__ );
			$this->directory_url  = plugins_url( dirname( $this->basename ) );

			// Load translations
			load_plugin_textdomain( 'wcps', false, dirname( $this->basename ) . '/languages' );

			// Setup our extension name and description
			$this->id                 = 'proudct_support';
			$this->method_title       = __( 'WooCommerce Product Support', 'wcps' );
			$this->method_description = __( 'This Plugin allows you to associate Products with either BuddyPress or bbPress forums.<br/>Below you can specify the default title and content for an optional first topic.', 'wcps' );
			$this->settings_link      = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=' . $this->id );
			$this->plugin             = plugin_basename( __FILE__ );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->bp_topic_title = $this->settings['bp_topic_title'];
			$this->bp_topic_text  = $this->settings['bp_topic_text'];

			// Hook in all our components
			$this->includes();

		} /* __construct() */

		/**
		 * Include additional dependencies and hook all methods.
		 *
		 * @since  2.0.0
		 */
		public function includes() {

			// If dependencies are unavailable, display error message and deactivate
			add_action( 'admin_notices', array( $this, 'maybe_disable_plugin' ) );

			// If we pass requirements, perform all our other actions
			if ( $this->meets_requirements() ) {

				// Include our bbPress content restriction
				if ( ! defined( 'EDD_CR_PLUGIN_DIR' ) )
					include_once( trailingslashit( $this->directory_path ) . 'bbp-content-restriction.php' );

				// Hook everything where it belongs
				add_action( 'admin_init', array( $this, 'register_metabox' ) );
				add_action( 'publish_product', array( $this, 'publish_product' ) );

				add_action( 'plugin_action_links', array( $this, 'add_plugin_settings_link' ), 10, 2 );
				add_action( 'woocommerce_order_status_completed', array( $this, 'wc_process_order' ) );
				add_action( 'woocommerce_update_options_integration_buddypress', array( $this, 'process_admin_options' ) );

			}

		} /* includes() */

		/**
		 * Check for required plugin dependencies
		 *
		 * @return bool  True if either bbPress or BuddyPress are active, otherwise false
		 */
		private function meets_requirements() {

			// Check if either BuddyPress Groups or bbPress are available
			$this->use_buddypress = class_exists( 'BP_Groups_Group' ) ? true : false;
			$this->use_bbpress = class_exists( 'BBP_Component' ) ? true : false;

			// If neither BuddyPress nor bbPress are available, return false
			if ( ! $this->use_buddypress && ! $this->use_bbpress )
				return false;
			else
				return true;

		} /* meets_requirements() */

		/**
		 * Disable plugin if requirements are not met.
		 *
		 * Will also output admin warning message.
		 *
		 * @since 1.0.0
		 */
		public function maybe_disable_plugin() {

			if ( ! $this->meets_requirements() ) {

				// Include thickbox support
				add_thickbox();

				// Generate our error message
				$output = '<div id="message" class="error">';
				$output .= '<p>';
				$output .= sprintf(
					__( '%1$s requires either %2$s <em>OR</em> %3$s with %4$s. Please install and activate at least one of these plugins.', 'wcps' ),
					$this->method_title,
					'<a href="' . admin_url( '/plugin-install.php?tab=plugin-information&plugin=bbpress&TB_iframe=true&width=600&height=550' ) . '" target="_blank" class="thickbox onclick">bbPress</a>',
					'<a href="' . admin_url( '/plugin-install.php?tab=plugin-information&plugin=BuddyPress&TB_iframe=true&width=600&height=550' ) . '" target="_blank" class="thickbox onclick">BuddyPress</a>',
					'<a href="' . admin_url( '/admin.php?page=bp-components' ) . '">User Groups</a>'
				);
				$output .= '</p>';
				$output .= '</div>';
				echo $output;

				// Deactivate our plugin
				deactivate_plugins( $this->basename );
			}

		} /* maybe_disable_plugin() */

		/**
		 * Add Settings link to plugins output
		 *
		 * @since  1.0.0
		 *
		 * @param  array  $links Plugin links array
		 * @param  string $file  Plugin file
		 * @return array         Updated links array
		 */
		public function add_plugin_settings_link( $links, $file ) {

			// If the filter is for this plugin
			if ( $file == $this->basename ) {
				// Write a settings link and add it to the front of the array
				$settings_link = sprintf(
					'<a href="%1$s">%2$s</a>',
					$this->settings_link,
					__( 'Settings', 'wcps' )
				);
				array_unshift( $links, $settings_link );
			}

			// Return the pluin action links
			return $links;

		} /* add_plugin_settings_link() */

		/**
		 * Adds metabox to product editor so a group/forum can be created or existing selected
		 *
		 * @since 1.0.0
		 */
		public function register_metabox() {
			add_meta_box(
				'support_metabox',
				__( 'Product Support', 'wcps' ),
				array( $this, 'render_metabox'),
				'product',
				'side',
				'default'
			);
		} /* register_metabox() */

		/**
		 * Output the Product Support metabox
		 *
		 * @since 1.0.0
		 *
		 * @param object $post Post object
		 */
		public function render_metabox( $post ) {

			// Concatenate our output
			$output = '';

			if ( $this->use_buddypress )
				$output .= $this->render_metabox_buddypress_settings( $post );

			if ( $this->use_bbpress )
				$output .= $this->render_metabox_bbpress_settings( $post );

			// Echo our output
			echo $output;

		} /* render_metabox() */

		/**
		 * Render BP settings for product metabox
		 *
		 * @since  2.0.0
		 *
		 * @param  object $post Post object
		 * @return string       Concatenated HTML markup
		 */
		private function render_metabox_buddypress_settings( $post ) {

			// Grab our current selections
			$product_group = get_post_meta( $post->ID, '_product_group', true );

			// Initialize our output
			$output = '';

			// Render our label
			$output .= '<p><label for="product_group">' . __( 'Conected BuddyPress Group:', 'wcps' ) . '</label> ';

			// Setup our select input
			$output .= '<select name="product_group" id="support_group">';
			$output .= '<option value="">' . __( 'None', 'wcps' ) . '</option>';
			$output .= '<option value="new">' . __( 'Create new group', 'wcps' ) . '</option>';

			// Loop through all existing BP groups and include them here
			$bp_groups = groups_get_groups( array( 'show_hidden' => true ) );
			if ( $bp_groups ) {
				foreach ( $bp_groups['groups'] as $group ) {
					$output .= '<option' . selected( $product_group, $group->id, false ) . ' value="' . $group->id . '">' . $group->name . '</option>';
				}
			}

			$output .= '</select></p>';

			return $output;

		} /* render_metabox_buddypress_settings() */

		/**
		 * Render bbPress settings for product metabox
		 *
		 * @since  2.0.0
		 *
		 * @param  object $post Post Object
		 * @return string       Concatenated HTML markup
		 */
		private function render_metabox_bbpress_settings( $post ) {

			// Setup our variables
			$product_forum = get_post_meta( $post->ID, '_product_forum', true );
			$limit_access  = get_post_meta( $post->ID, '_product_limit_access', true );

			// Initialize our output
			$output = '';

			// Render our label
			$output .= '<p><label for="product_forum">' . __( 'Conected bbPress Forum:', 'wcps' ) . '</label> ';

			// Build an entire select input for our forums
			$output .= '<select name="product_forum" id="product_forum">' . "\n";
			$output .= '<option value="" class="level-0">' . __( 'None', 'wcps' ) . '</option>' . "\n";
			$output .= '<option value="new" class="level-0">' . __( 'Create new forum', 'wcps' ) . '</option>' . "\n";
			$output .= bbp_get_dropdown( array(
				'selected'           => $product_forum,
				'select_id'          => 'product_forum',
				'show_none'          => false,
				'disable_categories' => false,
				'options_only'       => true,
			) );
			$output .= '</select>';

			// Create first topic
			$output .= '<p class="enable-first-post"><label for="create_first_post"><input type="checkbox" id="create_first_post" name="create_first_post" value="true"> '. sprintf( __( 'Create first topic using <a href="%s" target="_blank">default setings</a>.', 'wcps' ), admin_url('admin.php?page=woocommerce_settings&tab=integration&section=buddypress') ) . '</label></p>';

			// Restrict access to product owners
			if ( ! defined( 'EDD_CR_PLUGIN_DIR' ) )
				$output .= '<p class="limit-access"><label for="limit_access"><input type="checkbox" id="limit_access" name="limit_access" value="true" ' . checked( $limit_access, true, false ) . '> '. __( 'Limit forum access to product owners.', 'wcps' ) . '</label></p>';

			// JS to conditionally show checkbox for "insert first post"
			$output .= '
				<script type="text/javascript">
					jQuery("#product_forum").change( function() {

						// Setup our variables
						$this               = jQuery(this);
						$create_topic_wrap  = jQuery(".enable-first-post");
						$create_topic_input = jQuery("#create_first_post");
						$limit_access_wrap  = jQuery(".limit-access").show();
						$limit_access_input = jQuery("#limit_access");

						// If no forum is selected
						if ( "" === $this.val() ) {
							// Hide and uncheck first post checkbox
							$create_topic_wrap.hide(); // Hide the checkbox container
							$create_topic_input.attr("checked", false); // Uncheck the checkbox (for good measure)

							// Hide and uncheck access limiter
							$limit_access_wrap.hide(); // Hide the checkbox container
							$limit_access_input.attr("checked", false); // Uncheck the checkbox (for good measure)

						// If a new forum is selected
						} else if ( "new" === $this.val()) {
							$limit_access_wrap.show();
							$create_topic_wrap.show();

						// If any forum is selected
						} else {
							// Show access limiter
							$limit_access_wrap.show();

							// Hide and uncheck first post checkbox
							$create_topic_wrap.hide(); // Hide the checkbox container
							$create_topic_input.attr("checked", false); // Uncheck the checkbox (for good measure)
						}

					}).change();
				</script>
			';

			return $output;

		} /* render_metabox_bbpress_settings() */

		/**
		 * Action that fires when a product is published
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id Product post ID
		 */
		public function publish_product( $product_id ) {

			// If this is just an autosave, bail here
			if ( wp_is_post_autosave( $product_id ) )
				return;

			// Grab our support variables
			$product_group     = ! empty( $_POST['product_group'] ) ? $_POST['product_group'] : false;
			$product_forum     = ! empty( $_POST['product_forum'] ) ? $_POST['product_forum'] : false;
			$create_first_post = isset( $_POST['create_first_post'] ) ? true : false;
			$limit_access      = isset( $_POST['limit_access'] ) ? true : false;

			// If BP is enabled, and we have a group, create the group
			if ( $this->use_buddypress && 'new' == $product_group )
				$product_group = $this->bp_create_group( $product_id );

			// If bbP is enabled, and we have a forum, create the forum
			if ( $this->use_bbpress && 'new' == $product_forum )
				$product_forum = $this->bbp_create_forum( $product_id, $create_first_post );

			// Update product meta
			update_post_meta( $product_id, '_product_group', absint( $product_group ) );
			update_post_meta( $product_id, '_product_forum', absint( $product_forum ) );
			update_post_meta( $product_id, '_product_limit_access', $limit_access );

			// Update forum meta
			if ( absint( $product_forum ) )
				update_post_meta( $product_forum, '_themo_wcps_connected_product', $product_id );

		} /* publish_product() */

		/**
		 * Create a BuddyPress group on product creation and adds all admins as group members
		 *
		 * @since  1.0.0
		 *
		 * @return int The created group ID
		 */
		private function bp_create_group( $product_id = 0 ) {

			// Get the product details
			$product_title = get_the_title( $product_id );
			$product_slug = basename( get_permalink( $product_id ) );

			// See if we already have a corresponding BP Group
			$group_id = BP_Groups_Group::group_exists( $product_slug );

			// If a group doesn't already exist
			if ( ! $group_id ) {

				// Create our group
				$group_id = groups_create_group( array(
					'creator_id'   => bp_loggedin_user_id(),
					'name'         => $product_title,
					'slug'         => $product_slug,
					'description'  => sprintf( __( 'This is the support group for %s', 'wcps' ), $product_title ),
					'status'       => 'hidden',
					'date_created' => bp_core_current_time(),
					'enable_forum' => true
				) );

				// Update the member count and last activity
				groups_update_groupmeta( absint( $group_id ), 'total_member_count', 1 );
				groups_update_groupmeta( absint( $group_id ), 'last_activity', bp_core_current_time() );
				groups_update_groupmeta( absint( $group_id ), 'invite_status', 'admins' );

				// Add all admins as group members
				$this->bp_add_admins_to_group( $group_id );

			}

			return $group_id;

		} /* bp_create_group() */

		/**
		 * Add all admins to a BP Group
		 *
		 * @since 2.0.0
		 *
		 * @param integer $group_id Group ID
		 */
		private function bp_add_admins_to_group( $group_id = 0 ) {

			// Get all our current admins
			$wp_user_search = new WP_User_Query( array( 'role' => 'administrator' ) );
			$admins = $wp_user_search->get_results();

			// Add our admin users as members to this group
			if ( is_array( $admins ) && ! empty( $admins ) ) {
				foreach ( $admins as $admin ) {
					groups_join_group( $group_id, $admin->ID );
					groups_promote_member( $admin->ID, $group_id, 'admin' );
				}
			}

		} /* bp_add_admins_to_group() */

		/**
		 * Create a bbPress forum on product creation
		 *
		 * @since  1.0.0
		 *
		 * @return int The created forum ID
		 */
		private function bbp_create_forum( $product_id = 0, $create_first_post = false ) {

			// Create our forum and grab its ID
			$forum_id = bbp_insert_forum( array(
				'post_parent'    => 0, // forum ID
				'post_status'    => bbp_get_public_status_id(),
				'post_type'      => bbp_get_forum_post_type(),
				'post_author'    => bbp_get_current_user_id(),
				'post_password'  => '',
				'post_content'   => '',
				'post_title'     => get_the_title( $product_id ),
				'menu_order'     => 0,
				'comment_status' => 'closed'
			) );

			// If the option is set to auto-create the first topic, let's create it
			if ( $forum_id && $create_first_post )
				$this->bbp_create_first_topic( $product_id, $forum_id );

			// Return our new forum ID
			return $forum_id;

		} /* bbp_create_forum() */

		/**
		 * Create the first topic for a forum
		 *
		 * @since  2.0.0
		 *
		 * @param  integer $product_id Product post ID
		 * @param  integer $forum_id   Forum ID
		 * @return integer             Created topic ID
		 */
		private function bbp_create_first_topic( $product_id = 0, $forum_id = 0 ) {

			// Get the product title
			$product_title = get_the_title( $product_id );

			// Create a closed topic with our title and text settings
			$topic_id = bbp_insert_topic(
				array(
					'post_parent'    => $forum_id,
					'post_status'    => bbp_get_closed_status_id(),
					'post_type'      => bbp_get_topic_post_type(),
					'post_author'    => bbp_get_current_user_id(),
					'post_password'  => '',
					'post_content'   => preg_replace( '/%product_title%/', $product_title, $this->bp_topic_text ),
					'post_title'     => preg_replace( '/%product_title%/', $product_title, $this->bp_topic_title ),
					'comment_status' => 'closed',
					'menu_order'     => 0,
				),
				array(
					'forum_id'       => $forum_id
				)
			);

			// Make our new topic sticky
			if ( is_wp_error( $topic_id ) )
				bbp_stick_topic( $topic_id );

			return $topic_id;

		} /* bbp_create_topic() */

		/**
		 * Update legacy product support metadata
		 *
		 * @since 2.0.0
		 *
		 * @param integer $post_id Post ID
		 */
		public function update_legacy_meta( $post_id = 0 ) {
			global $wpdb;

			// Pull back all legacy meta entries
			$results = $wpdb->get_results(
				"
				SELECT *
				FROM   $wpdb->postmeta
				WHERE  meta_key = '_support_group'
				"
			);

			// Loop through each found result
			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {

					// Save updated metadata
					if ( 'forum' == get_post_type( $result->meta_value ) ) {
						update_post_meta( $result->post_id, '_product_forum', $result->meta_value);
						update_post_meta( $result->meta_value, '_themo_wcps_connected_product', $result->post_id );
					} else {
						update_post_meta( $result->post_id, '_product_group', $result->meta_value);
					}

					// Delete legacy metadata
					delete_post_meta( $result->post_id, '_support_group' );

				}
			}

		} /* update_legacy_meta() */


		/* WooCommerce Specific **********************************************/


		/**
		 * Initialise our Settings Form Fields
		 *
		 * @since 1.0.0
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'bp_topic_title'  => array(
					'title'       => __( 'Topic Title', 'wcps' ),
					'description' => '<br/>' . __( 'Note: you can use %product_title% to output the product title.', 'wcps' ),
					'type'        => 'text',
					'css'         => 'width:450px;',
					'default'     => __( '[IMPORTANT] %product_title% Support Guidelines', 'wcps' ),
					),
				'bp_topic_text'   => array(
					'title'       => __( 'Topic Content', 'wcps' ),
					'description' => '<br/>' . __( 'Note: you can use %product_title% to output the product title.', 'wcps' ),
					'type'        => 'textarea',
					'css'         => 'width:450px; height:250px;',
					'default'     => sprintf(
						__('Welcome to the %%product_title%% support forum!

						<strong>To expedite your help requests,</strong> please include the version numbers you\'re currently running for %%product_title%% and for WordPress, along with the URL of the website in question. This helps us to research and test and provide faster support.

						<strong>Please do <em>not</em> post</strong> your username, password, licenses or any other personal or sensitive information.

						Thank you!
						-The %s Team', 'wcps' ),
						get_bloginfo('name')
						),
					)
			);
		} /* init_form_fields() */

		/**
		 * Admin Options within WooCommerce Integration settings
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {

			echo '<h3>' . $this->method_title . '</h3>';
			echo wpautop( $this->method_description );

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';

			echo '<input type="hidden" name="section" value="' . $this->id .'" />';

		} /* admin_options() */

		/**
		 * Add user to BP group when order is completed
		 *
		 * @since  2.0.0
		 */
		public function wc_process_order( $order_id ) {

			// If we're not using BuddyPress, we can skip the rest
			if ( ! $this->use_buddypress )
				return;

			// Build the order object
			$order = new WC_Order( $order_id );

			// Get the user's ID
			$user_id = $order->user_id;

			// Get the purchased product ID(s)
			$products = $order->get_items();

			// Loop through each found product and add user to corresponding BP group
			if ( ! empty( $products ) ) {
				foreach ( $products as $product ) {

					// Get the ID of the support forum associated with the product
					$group_id = ( $group = get_post_meta( $product['product_id'], '_product_group', true ) ) ? $group : false;

					// If the product has enabled support, add the user to it's group
					if ( $group_id )
						groups_join_group( $group_id, $user_id );

				}
			}

		} /* bp_add_user_to_group() */

	} /* WC_Product_Support */

} /* themo_wcps_init() */

/**
 * Add the integration to WooCommerce.
 *
 * @param  array $integrations Current WC integrations
 * @return array               Updated WC integrations
 *
 * @since  1.0.0
 */
function themo_wcps_woocommerce_integrations( $integrations ) {

	$integrations[] = 'WC_Product_Support';

	return $integrations;

}
add_filter( 'woocommerce_integrations', 'themo_wcps_woocommerce_integrations' );