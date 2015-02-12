<?php
/**
 * Plugin Name: Slideshare Widget.
 * Plugin URI: http://www.magiclogix.com/contact
 * Description: Show latest slides of a user account from slideshare.net in a widget via its own API.
 * Version: 1.1.0
 * Author: Hassan Bawab
 * Author URI: info@magiclogix.com
 * License: GPL2
 */


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


add_action( 'admin_menu', 'sshw_setup_menu' );

function sshw_setup_menu(){
	add_menu_page( 'Slideshare widget setting', 'Slideshare widget', 'manage_options', 'sshw-settings', 'sshw_admin_page' );
}

function sshw_admin_page(){
	$sshw_api_key = stripslashes( get_option( 'sshw_api_key' ) );
	$sshw_secret  = stripslashes( get_option( 'sshw_secret' ) );
	delete_option('sshw_cahche');
	$html         = '<div class="wrap">
                <form action="options.php" method="post" name="options">
                <h2>Setup Slideshare widget</h2>' . wp_nonce_field( 'update-options' ) . '
                <table  cellpadding="10"><!-- class="form-table" width="100%" -->
                  <tbody>
                     <tr>
                       <td scope="row" align="right" valign="top">
                         <label>Your slideshare API key:</label>
                       </td>
                       <td scope="row" align="left" valign="top" >
                         <input name="sshw_api_key" value="' . $sshw_api_key . '" />
                       </td>
                     </tr>
                     <tr>
                       <td scope="row" align="right" valign="top">
                         <label>Shared secret:</label>
                       </td>
                       <td scope="row" align="left" valign="top" >
                         <input name="sshw_secret" value="' . $sshw_secret . '" />
                       </td>
                     </tr>
                     <tr>
                       <td colspan="2">
                         <i>
                           API key can be obtained from
                           <a href="https://www.slideshare.net/signup?from_source=http%3A%2F%2Fwww.slideshare.net%2Fdevelopers%2Fapplyforapi" target="_blank">here</a>.
                         </i>
                       </td>
                     </tr>
                  </tbody>
                </table>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="sshw_api_key,sshw_secret" />
                <input type="submit" name="Submit" value="Update" />
                </form>
             </div>';

	echo $html;

	return true;
}


function sshw_grab_slides( $num = 3, $uname = "" ){
	$results = array();
	// api_key: Set this to the API Key that SlideShare has provided for you.
	// ts: Set this to the current time in Unix TimeStamp format, to the nearest second(?).
	// hash: Set this to the SHA1 hash of the concatenation of the shared secret and the timestamp (ts). i.e. SHA1 (sharedsecret + timestamp).
	//The order of the terms in the concatenation is important.

	$api_link = "https://www.slideshare.net/api/2/get_slideshows_by_user/";
	$api_key  = stripslashes( get_option( 'sshw_api_key' ) );
	$secret   = stripslashes( get_option( 'sshw_secret' ) );
	$ts       = time();
	$hash     = sha1( $secret . $ts );
	$api_link = trim( $api_link ) . "?api_key=" . $api_key . "&ts=" . $ts . "&hash=" . $hash . "";
	$query    = "&username_for=" . $uname . "&sort=latest&limit=" . $num;

	$return_xml = file_get_contents( $api_link . $query );
	$obj        = simplexml_load_string( $return_xml );
	$i = 0;
	while( $obj->Slideshow[ $i ] ){
		$ss        = $obj->Slideshow[ $i ];
		$results[] = array(
			'ID'         => (string) $ss->ID,
			'title'      => (string) $ss->Title,
			'descrption' => (string) $ss->Description,
			'url'        => (string) $ss->URL,
			'thumbnail'  => (string) $ss->ThumbnailURL,
			'date'       => (string) $ss->Created
		);
		$i ++;
	}

//    }
	return $results;

}

function sshw_create_html( $num = 3, $uname ){
	$html = "";
	$chached_array=get_option('sshw_cahche');
	if(is_array($chached_array) && count($chached_array)>0){
		if(count($chached_array[$uname])>0 && intval($chached_array[$uname]['time'])>0){
			if(time()-intval($chached_array[$uname]['time'])<60*60 && time()-intval($chached_array[$uname]['time']>0)){
	           $html=trim($chached_array[$uname]['content']);
			}
		}
	}
	if($html!=''){
		return $html;
	}
	$res  = sshw_grab_slides( $num, $uname );
	if( is_array( $res ) && count( $res ) > 0 ){
		$html .= "<ul class='sshw'>\n";
		foreach( $res as $r ){
			$img  = "<img src='" . $r['thumbnail'] . "' title=\"" . str_replace( '"', "", $r['title'] ) . "\" />\n";
			$time = strtotime( $r['date'] );
			$datelocal = "Date: " . date( "D M j G:i", $time );
			$titlelink = "<a href='" . $r['url'] . "' target='_blank' >" . $r['title'] . "</a>\n";
			$html .= "<li>$img $titlelink <br><span>$datelocal </span></li>\n<div style='clear:both;'></div>\n";
		}
		$html .= "</ul>";
		$chached_array[$uname]=array('time'=>time(),'content'=>$html);
		update_option('sshw_cahche',$chached_array);
	}else{
		$html .= "<b>Sorry, no slides found!</b>";
	}

	return $html;
}

// creating the widget

class sshw_widget extends WP_Widget{
	function __construct(){
		parent::__construct(
			'sshw_widget',
			__( 'SlideShare Widget', 'sshw_widget_domain' ),
			array( 'description' => __( 'Show lates slides from SlideShare.net', 'sshw_widget_domain' ), )
		);
	}

	public function widget( $args, $instance ){
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'];
		if( ! empty( $title ) ){
			echo $args['before_title'] . $title . $args['after_title'];
		}
		$num   = $instance['num'];
		$uname = $instance['uname'];
		echo sshw_create_html( $num, $uname );
		echo $args['after_widget'];
	}

	public function form( $instance ){
		if( isset( $instance['title'] ) ){
			$title = $instance['title'];
		}else{
			$title = "";
		}
		if( isset( $instance['num'] ) && intval( $instance['num'] ) > 0 ){
			$num = intval( $instance['num'] );
		}else{
			$num = 3;
		}
		if( isset( $instance['uname'] ) ){
			$uname = $instance['uname'];
		}else{
			$uname = '';
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
			       value="<?php echo esc_attr( $title ); ?>"/>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'uname' ); ?>"><?php _e( 'Slideshare user:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'uname' ); ?>"
			       name="<?php echo $this->get_field_name( 'uname' ); ?>" style="" type="text"
			       value="<?php echo esc_attr( $uname ); ?>"/>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'num' ); ?>"><?php _e( 'Number of slides:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'num' ); ?>"
			       name="<?php echo $this->get_field_name( 'num' ); ?>" style="width:60px" type="text"
			       value="<?php echo esc_attr( $num ); ?>"/>
		</p>

	<?php
	}

	public function update( $new_instance, $old_instance ){
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['num']   = ( ! empty( $new_instance['num'] ) && intval( $new_instance['num'] ) > 0 ) ? strip_tags( intval( $new_instance['num'] ) ) : '3';
		$instance['uname'] = ( ! empty( $new_instance['uname'] ) ) ? strip_tags( $new_instance['uname'] ) : '';

		return $instance;
	}
}

function sshw_load_widget(){
	register_widget( 'sshw_widget' );
}

add_action( 'widgets_init', 'sshw_load_widget' );

function sshw_add_css(){
	wp_enqueue_style( 'sshw-style', plugins_url( 'sshw_styles.css', __FILE__ ) );

}

add_action( 'wp_enqueue_scripts', 'sshw_add_css' );

?>