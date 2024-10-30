<?php

/**
 * Plugin Name: JSON Post Type
 * Plugin URI:  https://github.com/wpcomvip/metro/
 * Description: Register a post type for managing arbitrary JSON configurations with an easy WYSIWYG editor.
 * Version:     1.0.0
 * Author:      Metro.co.uk
 * Author URI:  https://github.com/wpcomvip/metro/graphs/contributors
 * Text Domain: json-post-type
 */

class JSON_Post_Type {

	/**
	 * Custom post type name
	 *
	 * @var string
	 */
	const POST_TYPE = 'json';
	/**
	 * Initialize.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'admin_init', [ __CLASS__, 'add_caps' ] );
		add_filter( sprintf( 'rest_prepare_%s', self::POST_TYPE ), [ __CLASS__, 'prepare_post' ], 99, 3 );
	}

	/**
	 * Register the post type.
	 */
	public static function register_post_type() {

		/**
		 * Allow filtering of post type arguments before registering.
		 *
		 * @param array $args The default post type arguments.
		 */
		$args = apply_filters( 'json_post_type_args', [
			'label'                => 'JSON',
			'labels'               => [
				'add_new_item' => 'Add New JSON'
			],
			'description'          => 'A post type that stores arbitrary JSON configurations in its post content',
			'public'               => false,
			'exclude_from_search'  => true,
			'publicly_queryable'   => false,
			'show_ui'              => true,
			'show_in_nav_menus'    => false,
			'show_in_menu'         => true,
			'show_in_admin_bar'    => false,
			'menu_position'        => 50,
			'menu_icon'            => 'dashicons-code-standards',
			'capability_type'      => [ 'json', 'json' ],
			'hierarchical'         => false,
			'supports'             => [
				'title',
				'revisions'
			],
			'register_meta_box_cb' => [ __CLASS__, 'register_editor' ],
			'show_in_rest'         => true,
			'rest_base'            => 'json'
		] );

		// Register the post type.
		register_post_type(
			self::POST_TYPE,
			$args
		);
	}

	/**
	 * Add capabilties to user roles.
	 */
	public static function add_caps() {

		/**
		 * Allow roles to be filterable.
		 *
		 * Default to administrator only!.
		 *
		 * @param array $roles The default roles to add caps to.
		 */
		$roles = apply_filters( 'json_post_type_roles', [ 'administrator' ] );

		// Define the capabilities we're using.
		$capabilities = [
			'edit_json',
			'edit_others_json',
			'publish_json',
			'read_private_json'
		];

		// Add capabilities to permitted roles.
		foreach ( $roles as $role ) {
			$role = get_role( $role );
			if ( count( array_intersect( array_keys( $role->capabilities ), $capabilities ) ) !== count( $capabilities ) ) {
				foreach ( $capabilities as $capability ) {
					$role->add_cap( $capability );
				}
			}
		}
	}

	/**
	 * Register the JSON editor meta box and add JSONEditor assets.
	 */
	public static function register_editor() {

		// JSON Editor scripts.
		wp_enqueue_script(
			'jsoneditor',
			plugin_dir_url( __FILE__ ) . 'assets/jsoneditor/jsoneditor.min.js'
		);

		// JSON editor styles.
		wp_enqueue_style(
			'jsoneditor',
			plugin_dir_url( __FILE__ ) . 'assets/jsoneditor/jsoneditor.min.css'
		);

		// Add the meta box that will actually hold the JSON editor.
		add_meta_box(
			'json_editor',
			'JSON Editor',
			[ __CLASS__, 'editor_callback' ],
			null,
			'normal',
			'high'
		);

		// Add a link to the WP-JSON endpoint in the permalink area.
		add_action( 'edit_form_before_permalink', [ __CLASS__, 'add_rest_url' ] );
	}

	/**
	 * Callback for the editor meta box function.
	 *
	 * @param WP_Post $post The post object.
	 */
	public static function editor_callback( $post ) {

		// JSON is stored in post_content.
		$content = ( ! empty( $post->post_content ) ) ? $post->post_content : "{}";
		?>
		<div id="jsoneditor" style="height: 400px;"></div>
		<textarea class="hidden" id="content" name="content"><?php echo wp_kses_post( $content ); ?></textarea>
		<script>
			const container = document.getElementById('jsoneditor');
			const content   = document.getElementById('content');
			const options   = {
				mode: 'code',
				onChange: JSONPostTypeSetJSON
			}

			// Initialize the editor and set the initial content.
			const editor = new JSONEditor(container, options);
			editor.set(<?php echo wp_kses_post( $content ); ?>);

			// Set JSON on editor updates.
			function JSONPostTypeSetJSON() {
				var json = editor.get();
				json = JSON.stringify(json, null, 2);
				content.innerHTML = json;
			}
		</script> 
		<?php
	}

	/**
	 * Add the REST URL to the editor.
	 *
	 * @param WP_Post $post The post object.
	 */
	public static function add_rest_url( $post ) {

		if ( empty( $post->ID ) || $post->post_status === 'auto-draft' ) {
			return;
		}

		// Get the REST URL for the current post.
		$rest_url = sprintf(
			get_rest_url( null, 'wp/v2/json/%d/' ),
			$post->ID
		);

		// Output the REST URL link.
		printf( 
			'<div class="inside">
				<div id="edit-slug-box">
					<span id="sample-permalink">
						<strong>%s</strong>
						<a href="%s" target="_blank">%s</a>
					</span>
				</div>
			</div>',
			__( 'REST API URL:' ),
			esc_url( $rest_url ),
			esc_url( $rest_url )
		);
	}

	/**
	 * Prepare the post for the REST API response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post     Post object.
	 * @param WP_REST_Request  $request  Request object.
	 */
	public static function prepare_post( $response, $post, $request ) {

		// Replace response with JSON string in post_content.
		if ( ! empty( $post->post_content ) ) {

			// Decode the JSON so we can set the response data.
			$json = (array) json_decode( $post->post_content );
			if ( ! empty( $json ) ) {
				$response->data = $json;
			}
		}

		// Remove "_links" from the response.
		foreach ( $response->get_links() as $key => $value ) {
			$response->remove_link( $key );
		}
		
		return $response;
	}
}

JSON_Post_Type::init();
