<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPG_Template_Renderer {

	/**
	 * The templates used by the plugin.
	 *
	 * @var array
	 */
	protected $templates = [];

	/**
	 * Default templates.
	 *
	 * @return array
	 */
	public function get_default_templates() {
		return [
			// Template for each post item.
			'post_item' => '<a href="{post_permalink}" class="cpg-item">
    <div class="cpg-image-wrapper">
        {post_thumbnail}
    </div>
    {post_icon_html}
    <div class="cpg-content">
        <h3>{post_title}</h3>
        {post_excerpt}
    </div>
</a>',
			// Template for the wrapper that holds all items.
			'wrapper' => '<div class="cpg-wrapper" data-scenario="{scenario}" data-atts="{atts}">
    {content}
</div>',
			// You can add more templates (e.g. pagination, scroll container) as needed.
		];
	}

	/**
	 * Constructor. Loads the saved templates or falls back to defaults.
	 */
	public function __construct() {
		$saved_templates = get_option( 'cpg_templates', [] );
		$this->templates = wp_parse_args( $saved_templates, $this->get_default_templates() );
	}

	/**
	 * Render a given template with placeholder values.
	 *
	 * @param string $template_key The template key to use.
	 * @param array  $placeholders An associative array of placeholder => value.
	 * @return string
	 */
	public function render_template( $template_key, $placeholders = [] ) {
		if ( empty( $this->templates[ $template_key ] ) ) {
			return '';
		}

		$template = $this->templates[ $template_key ];

		foreach ( $placeholders as $placeholder => $value ) {
			$template = str_replace( '{' . $placeholder . '}', $value, $template );
		}

		return $template;
	}
}
