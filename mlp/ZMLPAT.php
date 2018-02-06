<?php

class ZMLPAT {

	private $gt_client;
	private $key = 'AIzaSyBbNuJFY93HtvkTruBa-cct3G612cMPbf8';

	public function __construct() {
	}

	public function setup() {
		add_filter( 'mlp_process_post_data_for_remote_site', array( $this, 'translate_content_on_copy' ), 10, 4 );
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
	 * @return Array                 Modified array of data.
	 */
	public function translate_content_on_copy( $data, $main_site_id, $post_id, $remote_site_id ) {

		$site_language = mlp_get_blog_language( $data['siteID'] );
		$title         = $this->translate( $data['title']  , $site_language );
		$content       = $this->translate( $data['content'], $site_language );
		$excerpt       = $this->translate( $data['excerpt'], $site_language );
		$slug          = sanitize_title( $title );

		$data['title']          = $title;
		$data['slug']           = $slug;
		$data['tinyMCEContent'] = '';
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
			wp_send_json_error( __( 'The Google Translate API returned malformed JSON that we could not interpret. Try again later!' ) );
		}

		if ( 200 !== $code ) {
			wp_send_json_error( $response->error->message, $response->error->code );
		} else {
			wp_send_json_success( $response->data->translations[0]->translatedText );
		}

	}

}
