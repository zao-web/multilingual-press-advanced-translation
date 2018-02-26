<?php
namespace Zao\ZMLPAT\MLP;

class Translate {

	private $gt_client;
	private $key = 'AIzaSyD7_f5723Q504rz-4NDA291Wn6UCFK8g74';

	public function __construct() {
	}

	public function setup() {
		add_filter( 'mlp_process_post_data_for_remote_site', [ $this, 'translate_content_on_copy' ]    , 100, 4 );
		add_filter( 'mlp_pre_insert_post_meta'             , [ $this, 'translate_tailor_layout_model' ], 12 , 2 );
		add_filter( 'mlp_pre_save_post_meta'               , [ $this, 'add_tailor_layout_to_meta' ], 10, 2 );
	}

	public function add_tailor_layout_to_meta( $meta, $context ) {

		if ( ! isset( $meta['_tailor_layout'] ) ) {
			$meta['_tailor_layout'] = get_post_meta( $context['real_post_id'], '_tailor_layout', true );
		}

		return $meta;
	}

	/**
	 * When a new post is created, this is hooked in to the pre_filter for inserting remote post meta.
	 *
	 * We use this method on this hook to ensure we're intelligently translating only the relevant
	 * parts of the tailor_layout
	 *
	 * @param  [type] $meta    [description]
	 * @param  [type] $context [description]
	 * @return [type]          [description]
	 */
	public function translate_tailor_layout_model( $meta, $context ) {

		error_log( var_export( $meta, 1 ) );

		// Element Tag => Translatable attributes (keys of `atts` key)
		$translatable_elements = apply_filters( 'mlp_translatable_tailor_elements', array(
			'tailor_custom_header'   => array( 'content' ),
			'tailor_content'         => array( 'content' ),
			'tailor_custom_top_hero' => array( 'tagline', 'text' ),
			'tailor_custom_header'   => array( 'content' ),
			'tailor_button'          => array( 'content' ),
			'tailor_toggle'          => array( 'title' ),
		), $meta, $context );

		// Return early if Tailor Layout isn't set.
		if ( ! isset( $meta['_tailor_layout'] ) ) {
			return $meta;
		}

		// If we have empty elements due to the filter, return early.
		if ( empty( $translatable_elements ) ) {
			return $meta;
		}

		$layout = maybe_unserialize( $meta['_tailor_layout'] );

		if ( empty( $layout ) ) {
			return $meta;
		}

		$tags          = array_keys( $translatable_elements );
		$site_language = mlp_get_blog_language( $context['target_blog_id'] );

		foreach ( $layout as $index => $element ) {

			if ( ! in_array( $element['tag'], $tags, true ) ) {
				continue;
			}

			$tag = $element['tag'];

			foreach ( $translatable_elements[ $tag ] as $attribute ) {
				$layout[ $index ]['atts'][ $attribute ] = $this->translate_content( $layout[ $index ]['atts'][ $attribute ], $site_language );
			}

		}

		$meta['_tailor_layout'] = $layout;

		return $meta;

	}

	/**
	 * Hooks into MultilingualPress to translate data when copying.
	 *
	 * @param  Array $data
	 *  array(
 	 *  'siteID'         => $remote_site_id,
 	 *  'title'          => $title,
 	 *  'slug'           => $slug,
 	 *  'tinyMCEContent' => $tmce_content,
 	 *  'content'        => $content,
 	 *	'excerpt'        => $excerpt,
 	 * )
	 * @param  Integer $main_site_id   Main site ID (Generally the English site)
	 * @param  Integer $post_id        Post ID.
	 * @param  Integer $remote_site_id Remote Site ID, used to get the language target.
	 *
	 * @return Array                 Modified array of data.
	 */
	public function translate_content_on_copy( $data, $main_site_id, $post_id, $remote_site_id ) {

		$site_language = mlp_get_blog_language( $data['siteID'] );

		$title         = $this->translate( $data['title']  , $site_language );
		$content       = $this->translate_content( $data['content'], $site_language );
		$excerpt       = $this->translate( $data['excerpt'], $site_language );

		$data['tinyMCEContent'] = $content;
		$data['title']          = $title;
		$data['content']        = $content;
		$data['excerpt']        = $excerpt;

		return $data;
	}

	public function translate( $text, $to ) {

		$args = [
			'body' => [
					'key'    => $this->key,
					'q'      => $text,
					'target' => $to
				]
			];

		$translation = wp_remote_post( 'https://translation.googleapis.com/language/translate/v2', $args );

		$code     = wp_remote_retrieve_response_code( $translation );
		$response = json_decode( wp_remote_retrieve_body( $translation ) );

		if ( null === $response ) {
			return $text;
		}

		if ( 200 !== $code ) {
			return $text;
		} else {
			return $response->data->translations[0]->translatedText;
		}

	}

	/**
	 * Handles translating content that may have shortcodes that should NOT be translated.
	 *
	 * @param  [type] $text [description]
	 * @param  [type] $to   [description]
	 * @return [type]       [description]
	 */
	public function translate_content( $text, $to ) {

		$pattern = get_shortcode_regex();

		preg_match_all( "/$pattern/", $text, $matches );

		// If there are no shortcodes, just translate the content.
		if ( empty( $matches[0] ) ) {
			return $this->translate( $text, $to );
		}

		$placeholders = array_fill_keys( $matches[0], '%s' );

		// Replace all the shortcodes in the content with placeholders
		$content      = strtr( $text, $placeholders );

		// Translate place-held content
		$translation  = $this->translate( $content, $to );

		// Return the translated content with shortcodes swapped back in.
		return vsprintf( $translation, $matches[0] );
	}

}
