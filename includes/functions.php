<?php
/**
 * Cue API methods and template tags.
 *
 * @package Cue
 * @copyright Copyright (c) 2014, AudioTheme, LLC
 * @license GPL-2.0+
 * @since 1.0.0
 */

/**
 * Display a playlist.
 *
 * @since 1.0.0
 *
 * @param mixed $post A post ID, WP_Post object or post slug.
 * @param array $args
 */
function cue_playlist( $post, $args = array() ) {
	if ( is_string( $post ) && ! is_numeric( $post ) ) {
		// Get a playlist by its slug.
		$post = get_page_by_path( $post, OBJECT, 'cue_playlist' );
	} else {
		$post = get_post( $post );
	}

	if ( ! $post ) {
		return;
	}

	$tracks = get_cue_playlist_tracks( $post );

	$template_names = array(
		"playlist-{$post->ID}.php",
		"playlist-{$post->post_name}.php",
		"playlist.php",
	);

	// Prepend custom templates.
	if ( ! empty( $args['template'] ) ) {
		$add_templates = array_filter( (array) $args['template'] );
		$template_names = array_merge( $add_templates, $template_names );
	}

	$template_loader = new Cue_Template_Loader();
	$template = $template_loader->locate_template( $template_names );

	do_action( 'cue_before_playlist' );

	include( $template );

	do_action( 'cue_after_playlist' );
}

/**
 * Playlist shortcode handler.
 *
 * @since 1.0.0
 *
 * @param array $atts Optional. List of shortcode attributes.
 * @return string HTML output.
 */
function cue_shortcode_handler( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'id'       => 0,
			'template' => '',
		),
		$atts
	);

	$id = $atts['id'];
	unset( $atts['id'] );

	ob_start();
	cue_playlist( $id, $atts );
	return ob_get_clean();
}

/**
 * Retrieve a playlist's tracks.
 *
 * @since 1.0.0
 *
 * @param int|WP_Post $post Playlist ID or post object.
 * @param string $context Optional. Context to retrieve the tracks for. Defaults to display.
 * @return array
 */
function get_cue_playlist_tracks( $post = 0, $context = 'display' ) {
	$playlist = get_post( $post );
	$tracks = array_filter( (array) $playlist->tracks );
	return apply_filters( 'cue_playlist_tracks', $tracks, $playlist, $context );
}

/**
 * Retrieve a default track.
 *
 * Useful for whitelisting allowed keys.
 *
 * @since 1.0.0
 *
 * @return array
 */
function get_cue_default_track() {
	$args = array(
		'artist'     => '',
		'artworkId'  => '',
		'artworkUrl' => '',
		'audioId'    => '',
		'audioUrl'   => '',
		'length'     => '',
		'format'     => '',
		'order'      => '',
		'title'      => '',
	);

	return apply_filters( 'cue_default_track_properties', $args );
}

/**
 * Sanitize a track based on the context.
 *
 * @since 1.0.0
 *
 * @param array $track Track data.
 * @param string $context Optional. Context to sanitize data for. Defaults to display.
 * @return array
 */
function sanitize_cue_track( $track, $context = 'display' ) {
	if ( 'save' == $context ) {
		$valid_props = get_cue_default_track();

		// Remove properties that aren't in the whitelist.
		$track = array_intersect_key( $track, $valid_props );

		// Sanitize valid properties.
		$track['artist']     = sanitize_text_field( $track['artist'] );
		$track['artworkId']  = absint( $track['artworkId'] );
		$track['artworkUrl'] = esc_url_raw( $track['artworkUrl'] );
		$track['audioId']    = absint( $track['audioId'] );
		$track['audioUrl']   = esc_url_raw( $track['audioUrl'] );
		$track['length']     = sanitize_text_field( $track['length'] );
		$track['format']     = sanitize_text_field( $track['format'] );
		$track['title']      = sanitize_text_field( $track['title'] );
		$track['order']      = absint( $track['order'] );
	}

	return apply_filters( 'cue_sanitize_track', $track, $context );
}

/**
 * Display a theme-registered player.
 *
 * @since 1.1.0
 *
 * @param string $player_id Player ID.
 * @param array $args
 */
function cue_player( $player_id, $args = array() ) {
	$playlist_id = get_cue_player_playlist_id( $player_id );
	$tracks = get_cue_playlist_tracks( $playlist_id );

	$template_names = array(
		"player-{$player_id}.php",
		"player.php",
	);

	// Prepend custom templates.
	if ( ! empty( $args['template'] ) ) {
		$add_templates = array_filter( (array) $args['template'] );
		$template_names = array_merge( $add_templates, $template_names );
	}

	$template_loader = new Cue_Template_Loader();
	$template = $template_loader->locate_template( $template_names );

	do_action( 'cue_before_player' );

	include( $template );

	do_action( 'cue_after_player' );
}

/**
 * Retrieve a list of players registered by the current them.
 *
 * Includes the player id, name and associated playlist if one has been saved.
 *
 * @since 1.1.0
 *
 * @return array
 */
function get_cue_players() {
	$players = array();
	$assigned = get_theme_mod( 'cue_players', array() );

	/**
	 * List of registered players.
	 *
	 * Format: array( 'player_id' => 'Player Name' )
	 *
	 * @since 1.1.0
	 *
	 * @param array $players List of players.
	 */
	$registered = apply_filters( 'cue_players', array() );

	if ( ! empty( $registered ) ) {
		asort( $registered );
		foreach ( $registered as $id => $name ) {
			$playlist_id = isset( $assigned[ $id ] ) ? $assigned[ $id ] : 0;

			$players[ $id ] = array(
				'id'          => $id,
				'name'        => $name,
				'playlist_id' => $playlist_id,
			);
		}
	}

	return $players;
}

/**
 * Retreive the ID of a playlist connected to a player.
 *
 * @since 1.1.0
 *
 * @param string $player_id Player ID.
 * @return int
 */
function get_cue_player_playlist_id( $player_id ) {
	$players = get_theme_mod( 'cue_players', array() );
	return isset( $players[ $player_id ] ) ? $players[ $player_id ] : 0;
}

/**
 * Retrieve playlist tracks for a registered player.
 *
 * @since 1.1.0
 *
 * @param string $player_id Player ID.
 * @return array
 */
function get_cue_player_tracks( $player_id ) {
	$playlist_id = get_cue_player_playlist_id( $player_id );
	return get_cue_playlist_tracks( $playlist_id );
}