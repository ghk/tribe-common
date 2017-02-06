<?php
// Don't load directly
defined( 'WPINC' ) or die;

class Tribe__Admin__Template {
	/**
	 * The folders into we will look for the template
	 * @var array
	 */
	private $folder = array();

	/**
	 * The origin class for the plugin where the template lives
	 * @var object
	 */
	public $origin;

	/**
	 * The local context for templates, muteable on every self::template() call
	 * @var array
	 */
	private $context;

	/**
	 * The global context for this instance of templates
	 * @var array
	 */
	private $global = array();

	/**
	 * Allow chaing if class will extract data from the local context
	 * @var boolean
	 */
	private $extract_context = false;

	/**
	 * Configures the class origin plugin path
	 *
	 * @param  object|string  $origin   The base origin for the templates
	 *
	 * @return self
	 */
	public function set_template_origin( $origin = null ) {
		if ( ! isset( $origin ) ) {
			$origin = $this->origin;
		}

		if ( is_string( $origin ) ) {
			// Origin needs to be a class with a `instance` method
			if ( class_exists( $origin ) && method_exists( $origin, 'instance' ) ) {
				$origin = call_user_func( array( $origin, 'instance' ) );
			}
		}

		if ( empty( $origin->plugin_path ) && empty( $origin->pluginPath ) ) {
			throw new InvalidArgumentException( 'Invalid Origin Class for Admin Template Instance' );
		}

		$this->origin = $origin;

		return $this;
	}

	/**
	 * Configures the class with the base folder in relation to the Origin
	 *
	 * @param  array|string   $folder  Which folder we are going to look for templates
	 *
	 * @return self
	 */
	public function set_template_folder( $folder = null ) {
		// Allows configuring a already set class
		if ( ! isset( $folder ) ) {
			$folder = $this->folder;
		}

		// If Folder is String make it an Array
		if ( is_string( $folder ) ) {
			$folder = (array) explode( '/', $folder );
		}

		// Cast as Array and save
		$this->folder = (array) $folder;

		return $this;
	}

	/**
	 * Configures the class global context
	 *
	 * @param  array  $context  Default global Context
	 *
	 * @return self
	 */
	public function add_template_globals( $context = array() ) {
		// Cast as Array merge and save
		$this->global = wp_parse_args( (array) $context, $this->global );

		return $this;
	}

	/**
	 * Configures if the class will extract context for template
	 *
	 * @param  bool  $value  Should we extract context for templates
	 *
	 * @return self
	 */
	public function set_template_context_extract( $value = false ) {
		// Cast as bool and save
		$this->extract_context = (bool) $value;

		return $this;
	}

	/**
	 * Gets the base path for this Instance of Admin Templates
	 *
	 * @return string
	 */
	public function get_base_path() {
		$origin_path = ! empty( $this->origin->plugin_path ) ? $this->origin->plugin_path : $this->origin->pluginPath;
		// Craft the Base Path
		$path = array_merge( (array) $origin_path, $this->folder );

		// Implode to avoid Window Problems
		$path = implode( DIRECTORY_SEPARATOR, $path );

		/**
		 * Allows filtering of the base path for templates
		 *
		 * @param string $path      Complete path to include the base folder
		 * @param self   $template  Current instance of the Tribe__Admin__Template
		 */
		return apply_filters( 'tribe_admin_template_base_path', $path, $this );
	}

	/**
	 * Sets a Index inside of the global or local context
	 * Final to prevent extending the class when the `get` already exists on the child class
	 *
	 * @see    Tribe__Utils__Array::set
	 *
	 * @param  array    $index     Specify each nested index in order.
	 *                             Example: array( 'lvl1', 'lvl2' );
	 * @param  mixed    $default   Default value if the search finds nothing.
	 * @param  boolean  $is_local  Use the Local or Global context
	 *
	 * @return mixed The value of the specified index or the default if not found.
	 */
	final public function get( $index, $default = null, $is_local = true ) {
		$context = $this->global;

		if ( true === $is_local ) {
			$context = $this->context;
		}

		/**
		 * Allows filtering the the getting of Context variables, also short circuiting
		 * Following the same strucuture as WP Core
		 *
		 * @param  mixed    $value     The value that will be filtered
		 * @param  array    $index     Specify each nested index in order.
		 *                             Example: array( 'lvl1', 'lvl2' );
		 * @param  mixed    $default   Default value if the search finds nothing.
		 * @param  boolean  $is_local  Use the Local or Global context
		 * @param  self     $template  Current instance of the Tribe__Admin__Template
		 */
		$value = apply_filters( 'tribe_admin_template_context_get', null, $index, $default, $is_local, $this );
		if ( null !== $value ) {
			return $value;
		}

		return Tribe__Utils__Array::get( $context, $index, $default );
	}

	/**
	 * Sets a Index inside of the global or local context
	 * Final to prevent extending the class when the `set` already exists on the child class
	 *
	 * @see    Tribe__Utils__Array::set
	 *
	 * @param  string|array  $index     To set a key nested multiple levels deep pass an array
	 *                                  specifying each key in order as a value.
	 *                                  Example: array( 'lvl1', 'lvl2', 'lvl3' );
	 * @param  mixed         $value     The value.
	 * @param  boolean       $is_local  Use the Local or Global context
	 *
	 * @return array Full array with the key set to the specified value.
	 */
	final public function set( $index, $value = null, $is_local = true ) {
		if ( true === $is_local ) {
			return Tribe__Utils__Array::set( $this->context, $index, $value );
		} else {
			return Tribe__Utils__Array::set( $this->global, $index, $value );
		}
	}

	/**
	 * Merges local and global context, and saves it locally
	 *
	 * @param  array  $context  Local Context array of data
	 * @param  string $file     Complete path to include the PHP File
	 * @param  array  $name     Template name
	 *
	 * @return array
	 */
	public function merge( $context = array(), $file = null, $name = null ) {
		// Applies local context on top of Global one
		$context = wp_parse_args( (array) $context, $this->global );

		/**
		 * Allows filtering the Local context
		 *
		 * @param array  $context   Local Context array of data
		 * @param string $file      Complete path to include the PHP File
		 * @param array  $name      Template name
		 * @param self   $template  Current instance of the Tribe__Admin__Template
		 */
		$this->context = apply_filters( 'tribe_admin_template_context', $context, $file, $name, $this );

		return $this->context;
	}

	/**
	 * A very simple method to include a Aggregator Template, allowing filtering and additions using hooks.
	 *
	 * @param  string  $name     Which file we are talking about including
	 * @param  array   $context  Any context data you need to expose to this file
	 * @param  boolean $echo     If we should also print the Template
	 * @return string            Final Content HTML
	 */
	public function template( $name, $context = array(), $echo = true ) {
		// If name is String make it an Array
		if ( is_string( $name ) ) {
			$name = (array) explode( '/', $name );
		}

		// Clean this Variable
		$name = array_map( 'sanitize_title_with_dashes', $name );

		// Apply the .php to the last item on the name
		$name[ count( $name ) - 1 ] .= '.php';

		// Build the File Path
		$file = implode( DIRECTORY_SEPARATOR, array_merge( (array) $this->get_base_path(), $name ) );

		/**
		 * A more Specific Filter that will include the template name
		 *
		 * @param string $file      Complete path to include the PHP File
		 * @param array  $name      Template name
		 * @param self   $template  Current instance of the Tribe__Admin__Template
		 */
		$file = apply_filters( 'tribe_admin_template_file', $file, $name, $this );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		ob_start();

		// Merges the local data passed to template to the global scope
		$this->merge( $context, $file, $name );

		/**
		 * Fires an Action before including the template file
		 *
		 * @param string $file      Complete path to include the PHP File
		 * @param array  $name      Template name
		 * @param self   $template  Current instance of the Tribe__Admin__Template
		 */
		do_action( 'tribe_admin_template_before_include', $file, $name, $this );

		// Only do this if really needed (by default it wont)
		if ( true === $this->extract_context && ! empty( $this->context ) ) {
			// Make any provided variables available in the template variable scope
			extract( $this->context );
		}

		include $file;

		/**
		 * Fires an Action After including the template file
		 *
		 * @param string $file      Complete path to include the PHP File
		 * @param array  $name      Template name
		 * @param self   $template  Current instance of the Tribe__Admin__Template
		 */
		do_action( 'tribe_admin_template_after_include', $file, $name, $this );
		$html = ob_get_clean();

		/**
		 * Allow users to filter the final HTML
		 *
		 * @param string $html      The final HTML
		 * @param string $file      Complete path to include the PHP File
		 * @param array  $name      Template name
		 * @param self   $template  Current instance of the Tribe__Admin__Template
		 */
		$html = apply_filters( 'tribe_admin_template_html', $html, $file, $name, $this );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}
}
