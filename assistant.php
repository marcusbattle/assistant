<?php
/*
Plugin Name: Assistant API
Version: 0.1.0
Author: Marcus Battle
Description: Personal Assistant API
*/

class Assistant_API {

	protected static $single_instance = null;

	static function init() {

		if ( self::$single_instance === null ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;

	}

	public function __construct() {
    require_once plugin_dir_path( __FILE__ ) . 'core/resources/messages.php';
	}

  public function hooks() {
    add_action( 'ai_reply', array( $this, 'ai_reply' ), 10, 1 );
  }

  /**
  * Figure out what to do with what ever request we recieved
  */
  public function ai_reply( $object_id ) {

    switch ( get_post_type( $object_id ) ) {

      case 'message':
        $this->parse_message( $object_id );
        break;

      default:
        # code...
        break;
    }

  }

  public function parse_message( $message_id ) {

    $message = get_post( $message_id );

    $message_reply_args = array(
      'id' => $message_id, 
      'text' => 'Hey how can I help you?'
    );

    do_action( 'ai_send_sms', $message_reply_args );

  }

}

add_action( 'plugins_loaded', array( Assistant_API::init(), 'hooks' ) );
