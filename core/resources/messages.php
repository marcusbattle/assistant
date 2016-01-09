<?php

Class Assistant_Messages {

	protected static $single_instance = null;

	static function init() {

		if ( self::$single_instance === null ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;

	}

	public function hooks() {

		add_action( 'init', array( $this, 'init_message_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_filter( 'manage_message_posts_columns', array( $this, 'message_column_headers' ) );
		add_action( 'manage_message_posts_custom_column', array( $this, 'message_columns' ), 10, 2 );

    add_filter( 'ai_parse_sms', array( $this, 'parse_plivo_sms' ), 10, 1 );
    add_action( 'ai_send_sms', array( $this, 'send_plivo_sms' ), 10, 1 );

	}

	/**
	 * Initialize the 'Message' Post Type
	 */
	public function init_message_post_type() {

	    $args = array(
			'public'		=> true,
			'label'		=> 'Messages',
			'menu_icon'	=> 'dashicons-testimonial',
	    );

	    register_post_type( 'message', $args );

	}

	public function register_routes() {

    // GET SMS
    register_rest_route( 'assistant/v1', '/sms', array(
	        'methods' => 'GET',
	        'callback' => array( $this, 'GET_sms' ),
	  ) );

		// POST Message
		register_rest_route( 'assistant/v1', '/sms', array(
	        'methods' => 'POST',
	        'callback' => array( $this, 'POST_sms' ),
	  ) );

	}

	public function message_column_headers( $columns ) {

		unset( $columns['date'] );

		$columns['title'] = 'Message';
		$columns['from'] = 'From';
		$columns['to'] = 'To';
		$columns['date'] = 'Date';

	  return $columns;

	}

	public function message_columns( $column, $message_id ) {

		switch ( $column ) {

			case 'to':
				echo get_post_meta( $message_id, 'message_to', true );
				break;

			case 'from':
				echo get_post_meta( $message_id, 'message_from', true );
				break;

		}

	}

  public function GET_sms( $data ) {

    return array( 'success' => false );

  }

	public function POST_sms( $data ) {

		$params = $data->get_params();

    $params = apply_filters( 'ai_parse_sms', $params );

		$params = wp_parse_args( $params, array(
			'from'	=> '',
			'to'	=> '',
			'text'	=> '',
		) );

		if ( empty( $params['text'] ) ) {
			return array( 'error' => 'empty_message' );
		}

		$args = array(
			'post_type' 	=> 'message',
			'post_status' 	=> 'publish',
			'post_title'	=> wp_trim_words( $params['text'], 30, '' ),
			'post_content'	=> $params['text'],
			'post_author'	=> 1,
		);

		$message_id = wp_insert_post( $args );

		update_post_meta( $message_id, 'message_from', $params['from'] );
		update_post_meta( $message_id, 'message_to', $params['to'] );
		update_post_meta( $message_id, 'params', $params );

		$update_args = array(
			'ID'		=> $message_id,
			'post_name'	=> $message_id,
		);

		wp_update_post( $update_args );

    // Now we figure out what Assistant needs to say back
    do_action( 'ai_reply', $message_id );

		return array( 'message_id' => $message_id );

	}

  public function parse_plivo_sms( $params = array() ) {

    // Settings for Plivo SMS
    $plivo_sms_params = array(
      'to'    => isset( $params['To'] ) ? $params['To'] : '',
      'from'  => isset( $params['From'] ) ? $params['From'] : '',
      'text'  => isset( $params['Text'] ) ? $params['Text'] : '',
    );

    if ( ! array_search( '', array_values( $plivo_sms_params ) ) ) {
      return $plivo_sms_params;
    }

    return $params;

  }

  public function send_plivo_sms( $message_reply_args = array() ) {

    $plivo_auth_id = get_option( 'plivo_auth_id', 'MANMM0OWE5MTFLYJC3OG' );
    $plivo_auth_token = get_option( 'plivo_auth_id', 'ZGI1MmY3NzMyZGVmMzMyZWU0NzQ2OTlmZWE2ODll' );

    $sms_args = array(
      'headers' => array(
        // 'Authorization' => 'Basic ' . base64_encode( $plivo_auth_id . ':' . $plivo_auth_token )
      ),
      'body' => array(
        'src'   => get_post_meta( $message_reply_args['id'], 'message_to', true ),
        'dst'   => get_post_meta( $message_reply_args['id'], 'message_from', true ),
        'text'  => $message_reply_args['text'],
      )
    );

    $response = wp_remote_post( 'https://api.plivo.com/v1/Account/{$plivo_auth_id}/Message/', $sms_args );

    print_r( $response ); exit;

  }

}

add_action( 'plugins_loaded', array( Assistant_Messages::init(), 'hooks' ) );
