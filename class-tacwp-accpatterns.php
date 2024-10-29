<?php
/** Accelerate Patterns compiled project class file.
 *
 * @package AMP Publisher
 * @subpackage Accelerate Patterns
 * @since 1.0
 * @version 1.0.2
 */

defined( 'ABSPATH' ) || die();

/**
 * Base class for project (theme or plugin).
 *
 * All project functionality gets compiled into a single class object, allowing for compatibility across projects and method inclusion from core based on project dependencies.
 *
 * @since 1.0
 */
class Tacwp_Accpatterns {

	/**
	 * Basic construct method native to all PHP classes. It sets up all project and class variables then runs the extends_construct() method for the project if it is included.
	 *
	 * @since 1.0
	 *
	 * @param string $prefix .
	 * @param string $dir .
	 */
	public function __construct( $prefix = '', $dir = '' ) {
		if ( '' === $dir ) {
			$dir = dirname( __FILE__ );}
		$this->self['TITLE']        = 'Accelerate Patterns';
		$this->self['VERSION']      = '1.0.1';
		$this->self['DIR']          = $dir . '/';
		$this->self['FOLDER']       = 'accelerate-patterns';
		$this->self['PRODUCT_TYPE'] = 'plugin';
		if ( 'plugin' === $this->self['PRODUCT_TYPE'] ) {
			$this->self['PATH'] = plugins_url( $this->self['FOLDER'] ) . '/';
		} else {
			$this->self['PATH'] = esc_url( get_template_directory_uri() ) . '/';
		}
		$this->self['prefix'] = $prefix;
	}

	/**
	 * Hook into after_setup_theme action to add filters used within the project.
	 *
	 * @since 1.0
	 *
	 * @requires : tacwp_admin_software
	 * @usage : init
	 */
	public function accelerate_admin_filters() {
		add_filter( 'tacwp_admin_software', array( &$this, 'tacwp_admin_software' ), 1, 1 );
	}

	/**
	 * Connect to the auto-update API to check for new versions.
	 *
	 * @since 1.0
	 *
	 * @param string $action .
	 * @param array  $params .
	 * @requires : domain_from_url, is_json
	 * @usage : accelerate_update_plugin_check, accelerate_update_plugin_info
	 */
	public function accelerate_update_api( $action, $params = array() ) {
		$apipath = 'https://ampwptools.com/autoupdateapi/';
		$slug    = $this->self['FOLDER'];
		$version = $this->self['VERSION'];
		$domain  = $this->domain_from_url();
		$body    = array(
			'apiaction' => $action,
			'domain'    => $domain,
			'product'   => $slug,
			'version'   => $version,
		);
		if ( count( $params ) > 0 ) {
			foreach ( $params as $k => $v ) {
				$body[ $k ] = $v;}
		}
		$request  = wp_safe_remote_post(
			$apipath,
			array(
				'body'    => $body,
				'timeout' => 15,
			)
		);
		$response = wp_remote_retrieve_body( $request );
		if ( $this->is_json( $response ) ) {
			return $response;}
		return null;
	}

	/**
	 * Initialize the auto-update system.
	 *
	 * @since 1.0
	 *
	 * @requires : accelerate_update_plugin_setup, accelerate_update_version_check
	 * @usage : init
	 */
	public function accelerate_update_plugin() {
		add_action( 'admin_init', array( &$this, 'accelerate_update_plugin_setup' ) );
		add_action( 'plugins_loaded', array( &$this, 'accelerate_update_version_check' ) );
	}

	/**
	 * Check API for a new version of the software and prepare the transient with the new version data.
	 *
	 * @since 1.0
	 *
	 * @param string $transient .
	 * @requires : accelerate_update_api, get_value, random_key
	 * @usage : accelerate_update_plugin_setup
	 */
	public function accelerate_update_plugin_check( $transient ) {
		$slug     = $this->self['FOLDER'];
		$response = $this->accelerate_update_api( 'update_check' );
		if ( null === $response ) {
			return $transient;}
		$obj     = json_decode( $response );
		$version = $this->get_value( $obj, 'new_version' );
		if ( version_compare( $version, $this->self['VERSION'], '>' ) ) {
			$softslug                         = $slug . '/functions.php';
			$download                         = $obj->package;
			$key                              = $this->random_key();
			$obj->package                     = $download . '?' . $key . time();
			$transient->response[ $softslug ] = $obj;
			$transient->checked[ $softslug ]  = $version;
			unset( $transient->no_update[ $softslug ] );
		}
		return $transient;
	}

	/**
	 * Check API for software info.
	 *
	 * @since 1.0
	 *
	 * @param string $data .
	 * @param string $action .
	 * @param string $args .
	 * @requires : accelerate_update_api, get_value
	 * @usage : accelerate_update_plugin_setup
	 */
	public function accelerate_update_plugin_info( $data, $action, $args ) {
		$slug = $this->self['FOLDER'];
		if ( 'plugin_information' !== $action ) {
			return $data;}
		if ( ! isset( $args->slug ) ) {
			return $data;}
		if ( $args->slug === $slug ) {
			$trans = $slug . '_plugin_info';
			$obj   = get_transient( $trans );
			if ( $obj ) {
				return $obj;
			} else {
				$request = $this->accelerate_update_api( 'update_info' );
				if ( null !== $request ) {
					$obj           = json_decode( $request );
					$arr           = json_decode( $request, true );
					$obj->sections = $this->get_value( $arr, 'sections', array() );
					$obj->banners  = $this->get_value( $arr, 'banners', array() );
					set_transient( $trans, $obj, 1 * DAY_IN_SECONDS );
					return $obj;
				}
			}
		}
		return $data;
	}

	/**
	 * Setup auto-update info and transient filters.
	 *
	 * @since 1.0
	 *
	 * @requires : accelerate_update_plugin_check, accelerate_update_plugin_info
	 * @usage : accelerate_update_plugin
	 */
	public function accelerate_update_plugin_setup() {
		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'accelerate_update_plugin_check' ) );
		add_filter( 'plugins_api', array( &$this, 'accelerate_update_plugin_info' ), 20, 3 );
	}

	/**
	 * Check and store values for software version upon first install, and current version.
	 *
	 * @since 1.0
	 *
	 * @usage : accelerate_update_plugin
	 */
	public function accelerate_update_version_check() {
		$slug    = $this->self['FOLDER'];
		$version = $this->self['VERSION'];
		$initial = get_option( $slug . '_initial_version' );
		$current = get_option( $slug . '_current_version' );
		if ( empty( $initial ) ) {
			update_option( $slug . '_initial_version', $version );}
		if ( $current !== $version ) {
			update_option( $slug . '_current_version', $version );}
	}

	/**
	 * AJAX handler for admin functionality within the project.
	 *
	 * @since 1.0
	 *
	 * @requires : get_value
	 * @usage : after_setup_theme
	 */
	public function admin_ajax_handler() {
		check_ajax_referer( 'accpHsgHKJdiJdjkjswpo', 'security' );
		$how  = '';
		$pass = array();
		if ( isset( $_POST['how'] ) && isset( $_POST['pass'] ) ) {
			$how  = sanitize_text_field( wp_unslash( $_POST['how'] ) );
			$pass = wp_kses_post( wp_unslash( $_POST['pass'] ) );
		}
		if ( 'createpattern' === $how ) {
			if ( isset( $_POST['selection'] ) ) {
				$selection = sanitize_text_field( wp_unslash( $_POST['selection'] ) );
				$html      = $pass;
				$html      = json_decode( $html, true );
				$html      = html_entity_decode( $html );
				$blocks    = parse_blocks( $html );
				if ( count( $blocks ) > 0 ) {
					$valid = array();
					foreach ( $blocks as $data ) {
						$name = $this->get_value( $data, 'blockName' );
						if ( '' !== $name ) {
							$valid[] = $data;}
					}
					if ( count( $valid ) > 0 ) {
						$package = array();
						$exp     = explode( '_', $selection );
						$start   = $this->get_value( $exp, 0, 'N' );
						$end     = $this->get_value( $exp, 1, 'N' );
						if ( 'N' !== $start && 'N' !== $end ) {
							$st = intval( $start );
							$en = intval( $end );
							foreach ( $valid as $dex => $data ) {
								if ( $dex >= $st && $dex <= $en ) {
									$package[] = $data;}
								$dex++;
							}
						}
						$unparsed             = serialize_blocks( $package );
						$args                 = array();
						$args['post_type']    = 'accpattern';
						$args['post_name']    = 'newpattern';
						$args['post_title']   = 'NEW Pattern';
						$args['post_status']  = 'publish';
						$args['post_content'] = $unparsed;
						$post_id              = wp_insert_post( $args, true );
						die( esc_attr( 'post=' . $post_id ) );
					}
				}
			}
		}
		die();
	}

	/**
	 * Enqueue scripts use within the project.
	 *
	 * @since 1.0
	 *
	 * @requires : admin_inline_script
	 * @usage : init
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'accpatterns-admin-stylesheet', plugins_url( 'script/acceleratePatternsStyle.css', __FILE__ ), array(), time() );
		wp_enqueue_script( 'accpatterns-admin', plugins_url( 'script/acceleratePatternsClass.js', __FILE__ ), array( 'jquery' ), time(), true );
		wp_add_inline_script( 'accpatterns-admin', $this->admin_inline_script(), 'before' );
		$prefix  = $this->self['prefix'];
		$ajaxobj = $prefix . '_ajax_handler';
		$params  = array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( 'accpHsgHKJdiJdjkjswpo' ),
		);
		wp_localize_script( 'accpatterns-admin', $ajaxobj, $params );
	}

	/**
	 * Declare the admin post url for use in javascript.
	 *
	 * @since 1.0
	 *
	 * @usage : admin_enqueue_scripts
	 */
	public function admin_inline_script() {
		$rat  = '';
		$rat .= 'var adminPostURL = "' . admin_url( 'post.php', '' ) . '";';
		return $rat;
	}

	/**
	 * Hook into after_setup_theme action to add filters used within the project.
	 *
	 * @since 1.0
	 *
	 * @requires : admin_ajax_handler
	 * @usage : init
	 */
	public function after_setup_theme() {
		if ( is_admin() ) {
			$prefix = $this->self['prefix'];
			add_action( 'wp_ajax_' . $prefix . '_ajax_handler', array( &$this, 'admin_ajax_handler' ) );
		}
	}

	/**
	 * Extract the domain from a string or from home_url().
	 *
	 * @since 1.0
	 *
	 * @param string $url .
	 * @usage : accelerate_update_api
	 */
	public function domain_from_url( $url = null ) {
		if ( null === $url ) {
			$url = home_url();}
		return wp_parse_url( $url, PHP_URL_HOST );
	}

	/**
	 * Get a value from an array or object across nested levels of depth.
	 *
	 * @since 1.0
	 *
	 * @param string $incoming .
	 * @param string $var .
	 * @param string $def .
	 * @requires : object_to_array
	 * @usage : admin_ajax_handler, pattern_preview, patterns_restrict_manage_posts, patterns_setup, accelerate_update_plugin_check, accelerate_update_plugin_info, tacwp_admin_section_software
	 */
	public function get_value( $incoming, $var, $def = '' ) {
		if ( is_object( $incoming ) && is_array( $var ) ) {
			$incoming = $this->object_to_array( $incoming );}
		if ( is_object( $incoming ) ) {
			if ( isset( $incoming->$var ) ) {
				return $incoming->$var;
			}
		} else {
			if ( is_array( $var ) ) {
				if ( count( $var ) > 0 ) {
					$tar = $incoming;
					foreach ( $var as $far ) {
						if ( isset( $tar[ $far ] ) ) {
							$tar = $tar[ $far ];
						} else {
							return $def;
						}
					}
					return $tar;
				}
			} else {
				if ( isset( $incoming[ $var ] ) ) {
					return $incoming[ $var ];
				}
			}
		}
		return $def;
	}

	/**
	 * Initialize actions, filters, hooks and scripts needed within the project.
	 *
	 * @since 1.0
	 *
	 * @requires : accelerate_update_plugin, patterns_editor_add_meta_box, patterns_editor_save_post, patterns_posts_columns, patterns_posts_column, patterns_restrict_manage_posts, patterns_setup, after_setup_theme, admin_enqueue_scripts, pattern_preview, accelerate_admin_filters
	 */
	public function init() {
		$this->accelerate_update_plugin();
		add_action( 'add_meta_boxes', array( &$this, 'patterns_editor_add_meta_box' ) );
		add_action( 'save_post', array( &$this, 'patterns_editor_save_post' ), 10, 2 );
		add_filter( 'manage_accpattern_posts_columns', array( &$this, 'patterns_posts_columns' ) );
		add_action( 'manage_accpattern_posts_custom_column', array( &$this, 'patterns_posts_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( &$this, 'patterns_restrict_manage_posts' ) );
		add_action( 'init', array( &$this, 'patterns_setup' ) );
		add_action( 'after_setup_theme', array( &$this, 'after_setup_theme' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
		add_action( 'template_redirect', array( &$this, 'pattern_preview' ), 1 );
		add_action( 'after_setup_theme', array( &$this, 'accelerate_admin_filters' ) );
	}

	/**
	 * Check a string for JSON structure.
	 *
	 * @since 1.0
	 *
	 * @param string $input .
	 * @usage : accelerate_update_api
	 */
	public function is_json( $input ) {
		if ( is_string( $input ) ) {
			if ( is_array( json_decode( $input, true ) ) ) {
				return true;}
		}
		return false;
	}

	/**
	 * Convert a PHP object to an array.
	 *
	 * @since 1.0
	 *
	 * @param string $d .
	 * @usage : get_value
	 */
	public function object_to_array( $d ) {
		if ( is_object( $d ) ) {
			$d = get_object_vars( $d );
		}if ( is_array( $d ) ) {
			return array_map( array( $this, 'object_to_array' ), $d );
		} else {
			return $d;}
	}

	/**
	 * Create a preview for the accpattern post type that is requested in an iframe from the posts list.
	 *
	 * @since 1.0
	 *
	 * @requires : get_value
	 * @usage : init
	 */
	public function pattern_preview() {
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri   = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$catch = '?pattern=';
			if ( strstr( $uri, $catch ) ) {
				$exp  = explode( $catch, $uri );
				$plan = $this->get_value( $exp, 1 );
				$id   = '';
				if ( '' !== $plan ) {
					$plex = explode( '&v=', $plan );
					$id   = $plex[0];
				}
				$id = $this->get_value( $exp, 1 );
				if ( '' !== $id ) {
					$post_id = intval( $id );
					$post    = get_post( $post_id );
					$type    = $this->get_value( $post, 'post_type' );
					if ( 'accpattern' === $type ) {
						add_filter( 'show_admin_bar', '__return_false' );
						echo '<html style="margin-top:0 !important;"><head>';
						wp_head();
						echo '</head><body>';
						echo wp_kses_post( do_blocks( $post->post_content ) );
						wp_footer();
						echo '</body></html>';
						die();
					}
				}
			}
		}
	}

	/**
	 * Add a meta box to the post editor for patterns custom post type.
	 *
	 * @since 1.0
	 *
	 * @requires : patterns_editor_meta_box
	 * @usage : init
	 */
	public function patterns_editor_add_meta_box() {
		add_meta_box(
			'accpattern',
			'My Patterns',
			array( &$this, 'patterns_editor_meta_box' ),
			array( 'accpattern' ),
			'side',
			'default'
		);
	}

	/**
	 * Generate the patterns custom post type meta box.
	 *
	 * @since 1.0
	 *
	 * @param string $post .
	 * @usage : patterns_editor_add_meta_box
	 */
	public function patterns_editor_meta_box( $post ) {
		wp_nonce_field( 'accpatterns_meta_box_nonce', 'pattern_description_nonce' );
		echo '<div class="components-base-control editor-post-excerpt__textarea apbpanel metapanel">';
			$post_id   = $post->ID;
			$post_type = get_post_type();
		if ( 'accpattern' === $post_type ) {
			$val = get_post_meta( $post_id, 'pattern_description', true );
			echo '<label for="pattern_description" class="components-base-control__label">' . esc_html( __( 'Pattern Description', 'accelerate-patterns' ) ) . '</label>';
			echo '<textarea name="pattern_description" id="pattern_description" autocomplete="off">' . esc_html( $val ) . '</textarea>';
		}
		echo '</div>';
	}

	/**
	 * Hook into the post update to handle custom meta values.
	 *
	 * @since 1.0
	 *
	 * @param string $post_id .
	 * @usage : init
	 */
	public function patterns_editor_save_post( $post_id ) {
		if ( isset( $_POST['pattern_description_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['pattern_description_nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'accpatterns_meta_box_nonce' ) ) {
				return;}
		} else {
			return;
		}
		$post          = get_post( $post_id );
		$post_type     = $post->post_type;
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
			return $post_id;}
		if ( 'accpattern' === $post_type ) {
			if ( isset( $_POST['pattern_description'] ) ) {
				$desc = sanitize_text_field( wp_unslash( $_POST['pattern_description'] ) );
				update_post_meta( $post_id, 'pattern_description', $desc );
			}
		}
	}

	/**
	 * Generate pattern content for the patterns posts list.
	 *
	 * @since 1.0
	 *
	 * @param string $column .
	 * @param string $post_id .
	 * @usage : init
	 */
	public function patterns_posts_column( $column, $post_id ) {
		if ( 'pattern_description' === $column ) {
			$verified = get_post_meta( $post_id, 'pattern_description', true );
			echo '<span>' . esc_html( $verified ) . '</span>';
		}
		if ( 'pattern_preview' === $column ) {
			echo '<div class="pattern-preview">';
				echo '<div class="pwrap">';
					$url = get_bloginfo( 'url' ) . '?pattern=' . esc_attr( $post_id );
					echo '<object class="preview-object" data="' . esc_url_raw( $url ) . '"><embed src="' . esc_url_raw( $url ) . '"> </embed></object>';
				echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Handle columns for the patterns posts list.
	 *
	 * @since 1.0
	 *
	 * @param string $columns .
	 * @usage : init
	 */
	public function patterns_posts_columns( $columns ) {
		unset( $columns['author'] );
		unset( $columns['date'] );
		$columns['pattern_description'] = 'Description';
		$columns['pattern_preview']     = 'Preview';
		return $columns;
	}

	/**
	 * Filter My Patterns list by Pattern Category.
	 *
	 * @since 1.0
	 *
	 * @requires : get_value
	 * @usage : init
	 */
	public function patterns_restrict_manage_posts() {
		global $typenow;
		global $wp_query;
		if ( 'accpattern' === $typenow ) {
			$taxonomy = 'mypatterns';
			$tax      = get_taxonomy( $taxonomy );
			wp_dropdown_categories(
				array(
					'show_option_all' => __( 'Show All Categories' ),
					'taxonomy'        => $taxonomy,
					'name'            => 'mypatterns',
					'orderby'         => 'name',
					'value_field'     => 'slug',
					'selected'        => $this->get_value( $wp_query->query, 'term' ),
					'hierarchical'    => true,
					'depth'           => 3,
					'show_count'      => true,
					'hide_empty'      => true,
				)
			);
		}
	}

	/**
	 * Register custom post type and taxonomy.
	 *
	 * @since 1.0
	 *
	 * @requires : get_value
	 * @usage : init
	 */
	public function patterns_setup() {
		$labels = array(
			'name'              => _x( 'Pattern Categories', 'taxonomy general name', 'accelerate-patterns' ),
			'singular_name'     => _x( 'Pattern Category', 'taxonomy singular name', 'accelerate-patterns' ),
			'search_items'      => __( 'Search My Patterns', 'accelerate-patterns' ),
			'all_items'         => __( 'All Patterns', 'accelerate-patterns' ),
			'parent_item'       => __( 'Parent Pattern Category', 'accelerate-patterns' ),
			'parent_item_colon' => __( 'Parent Pattern Category:', 'accelerate-patterns' ),
			'edit_item'         => __( 'Edit Pattern Categories', 'accelerate-patterns' ),
			'update_item'       => __( 'Update Pattern Category', 'accelerate-patterns' ),
			'add_new_item'      => __( 'Add New Pattern Category', 'accelerate-patterns' ),
			'new_item_name'     => __( 'New Pattern Category Name', 'accelerate-patterns' ),
			'menu_name'         => __( 'Pattern Categories', 'accelerate-patterns' ),
		);
		$args   = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'public'            => true,
			'rewrite'           => false,
			'show_in_rest'      => true,
		);
		register_taxonomy( 'mypatterns', array( 'accpattern' ), $args );
		unset( $labels );
		unset( $args );
		$labels = array(
			'name'          => _x( 'My Patterns', 'accelerate-patterns' ),
			'menu_name'     => __( 'My Patterns', 'accelerate-patterns' ),
			'singular_name' => _x( 'Pattern', 'taxonomy singular name', 'accelerate-patterns' ),
			'search_items'  => __( 'Search My Patterns', 'accelerate-patterns' ),
			'all_items'     => __( 'All Patterns', 'accelerate-patterns' ),
			'edit_item'     => __( 'Edit Pattern', 'accelerate-patterns' ),
			'update_item'   => __( 'Update Pattern', 'accelerate-patterns' ),
			'add_new'       => __( 'Add New Pattern', 'accelerate-patterns' ),
			'add_new_item'  => __( 'Add New Pattern', 'accelerate-patterns' ),
			'new_item_name' => __( 'New Pattern Name', 'accelerate-patterns' ),
		);
		$args   = array(
			'labels'              => $labels,
			'show_in_rest'        => true,
			'public'              => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'query_var'           => false,
			'capability_type'     => 'post',
			'can_export'          => false,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'menu_icon'           => 'dashicons-superhero',
			'supports'            => array( 'editor', 'title' ),
			'show_ui'             => true,
			'taxonomies'          => array( 'mypatterns' ),
		);
		register_post_type( 'accpattern', $args );
			register_block_pattern_category( 'accpattern', array( 'label' => __( 'My Patterns', 'accelerate-patterns' ) ) );
			$arr = get_posts(
				array(
					'post_type'   => 'accpattern',
					'numberposts' => -1,
				)
			);
		if ( count( $arr ) > 0 ) {
			foreach ( $arr as $dex => $data ) {
				$cats    = array( 'accpattern' );
				$post_id = $this->get_value( $data, 'ID' );
				$name    = $this->get_value( $data, 'post_title' );
				$content = $this->get_value( $data, 'post_content' );
				$desc    = get_post_meta( $post_id, 'pattern_description', true );
				register_block_pattern(
					'tacwp/pattern_' . $post_id,
					array(
						'title'         => $name,
						'description'   => $desc,
						'categories'    => $cats,
						'content'       => $content,
						'viewportWidth' => 1200,
					)
				);
			}
		}
	}

	/**
	 * Generate a random string of specified length.
	 *
	 * @since 1.0
	 *
	 * @param string $length .
	 * @usage : accelerate_update_plugin_check
	 */
	public function random_key( $length = 10 ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charlength = strlen( $characters );
		$rand       = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$rand .= $characters[ wp_rand( 0, $charlength - 1 ) ];
		}
		return $rand;
	}

	/**
	 * Generate software title, info and links for this project.
	 *
	 * @since 1.0
	 *
	 * @param array $data .
	 * @requires : get_value
	 * @usage : tacwp_admin_software
	 */
	public function tacwp_admin_section_software( $data = array() ) {
		$rat      = '';
		$software = $this->get_value( $data, 'software', $this->self['FOLDER'] );
		$title    = $this->get_value( $data, 'title', $this->self['TITLE'] );
		$version  = $this->get_value( $data, 'version', $this->self['VERSION'] );
		$type     = $this->get_value( $data, 'type', $this->self['PRODUCT_TYPE'] );
		$def      = '';
		if ( isset( $this->self['INSTRUCTIONS'] ) ) {
			$def = $this->self['INSTRUCTIONS'];}
		$inst     = $this->get_value( $data, 'instructions', $def );
		$rat     .= '<div class="ampwp-swsec">';
			$rat .= '<span class="ampwp-swttl">' . $title . ' V' . $version . '</span>';
			$rat .= '<span class=""> (' . $type . ')</span>';
		if ( '' !== $inst ) {
			if ( 'http' === substr( $inst, 0, 4 ) ) {
				$rat .= '<span class=""> <a href="' . $inst . '" target="_blank">User Manual</a></span>';
			} else {
				$rat .= '<span class=""> ' . $inst . '</span>';
			}
		}
		$rat .= '</div>';
		return $rat;
	}

	/**
	 * Hook the software information into the Accelerate admin home.
	 *
	 * @since 1.0
	 *
	 * @param string $content .
	 * @requires : tacwp_admin_section_software
	 * @usage : accelerate_admin_filters
	 */
	public function tacwp_admin_software( $content = '' ) {
		$content .= $this->tacwp_admin_section_software();
		return $content;
	}
}

