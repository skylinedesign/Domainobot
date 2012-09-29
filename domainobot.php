<?php
/*
Plugin Name: Domainobot
Plugin URI: http://domainobot.com
Description: A simple plugin that keeps you informed concerning your domain status, and alerts you when renewal is due
Author: Huston Malande, Brian Wangila
Version: 1.0
Author URI: http://skylinedesign.co.ke/martians
License: GPL2
*/


/**	Classes */

//	include lookup classes
include( 'lib/whois.main.php' );
include( 'lib/whois.custom.php' );

//	define querying class
class DomainStatus {
	var $domain = '';
	var $result = '';
	var $expiry_date = '';

	function __construct( $domain = '' ) {
		$this->set_domain( $domain );
		$this->get_expiry();
	}

	function set_domain( $domain = '' ) {
		if ( ! empty( $domain ) && $domain != '' ) {
			$this->domain = $domain;
		} else {
			// auto-detect domain name
			$this->domain = str_replace( "www.", "", $_SERVER['HTTP_HOST'] );
		}
	}

	function get_expiry() {
		
		// run specific ccTLD's check first
		$custom_cctld_expiry = custom_cctld_check( $this->domain );
		
		if ( $custom_cctld_expiry ) {
			$this->expiry_date = $custom_cctld_expiry;
		} else {
			// use phpwhois class
			$whois = new Whois();
			$query = $this->domain;
			$this->result = $whois->Lookup( $query );
			$this->expiry_date = $this->result['regrinfo']['domain']['expires'];
		}
		
		// format date
		$this->format_expiry();
	}

	function format_expiry() {
		$this->expiry_date = strtotime( $this->expiry_date );
		$this->expiry_date = date( 'jS F Y', $this->expiry_date );
	}
}


/**	Cron jobs */

//	upon activation
register_activation_hook( __FILE__, 'domainobot_activation' );

function domainobot_activation() {
	wp_schedule_event( current_time( 'timestamp' ), 'daily', 'domainobot_whois_update' );
}

function domainobot_update_whois_daily() {
	// run every 24hrs
	$domain_status = new DomainStatus( $domain );
	$domain_expiry = $domain_status->expiry_date;
	update_option( 'domainobot_current_expiry_op', $domain_expiry );
}

add_action( 'domainobot_whois_update', 'domainobot_update_whois_daily' );


/**	Cleaning up */

//	clean up after deactivation
register_deactivation_hook( __FILE__, 'domainobot_deactivation' );

function domainobot_deactivation() {
	// clear cron
	wp_clear_scheduled_hook( 'domainobot_whois_update' );
	delete_option( 'domainobot_show_current_op' );
	delete_option( 'domainobot_current_expiry_op' );
}

//	clean up after deletion
register_uninstall_hook( __FILE__, 'domainobot_deletion' );

function domainobot_deletion() {
	// clear db values
	delete_option( 'domainobot_list_op' );
}


/**	Stylesheets */

//	css file
function domainobot_css() {
	wp_register_style( 'domainobot-style', plugins_url( 'domainobot.css', __FILE__ ) );
	wp_enqueue_style( 'domainobot-style' );	
}

//	hook up the css
add_action( 'admin_print_styles', 'domainobot_css' );


/**	Current Domain */

/* 	check if we need to display the auto-detected,
	current domain status on the top-right */
$current  = get_option( 'domainobot_show_current_op' );
if ($current == 1) {
	
	// function for output
	function domainobot_current_domain() {
		// $domain = 'put_your_test_domain_here_and_uncomment';
		$domain_status = new DomainStatus( $domain );
		echo "<p id='domainobot-bar'>Domain renewal: " . esc_html( $domain_status->expiry_date ) . "</p>";
	}

	// hook up the output
	add_action( 'admin_notices', 'domainobot_current_domain' );

}


/**	Options */

//	options page
function domainobot_options_page() { ?>

	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Domainobot Settings</h2>
		<?php 
		$domainobot_list_saved = get_option( 'domainobot_list_op' );
		if( isset( $_POST['Submit'] ) ) {
			$domainobot_list_saved = $_POST["domainobot_list"];
			$domainobot_show_current_saved = $_POST["domainobot_show_current"];
			update_option( 'domainobot_list_op', $domainobot_list_saved );
			update_option( 'domainobot_show_current_op', $domainobot_show_current_saved ); ?>
			<div class="updated">
				<p><strong><?php _e( 'Options saved.', 'mt_trans_domain' ); ?></strong></p>
			</div>
		<?php } ?>

		<form method="post" name="options" action="">
		<br />
		<table width="100%" class="form-table">
			<tr>
				<th scope="row">Domains</th>
				<td>
					<p>List of domains you'd like to monitor on the Dashboard. Place each on a new line.</p>
					<p><textarea name="domainobot_list" rows="5" cols="50"><?php echo esc_textarea( $domainobot_list_saved ); ?></textarea></p>
				</td> 
			</tr>
			<tr>
				<th scope="row">Current Domain</th>
				<td>
					<p><input type="checkbox" name="domainobot_show_current" value="1" <?php checked( true, get_option( 'domainobot_show_current_op' ) ); ?> /> Show current domain (top right)</p>
				</td> 
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" value="Update" class="button-primary" />
		</p>
		</form>
	</div>
	
<?php }

//	add options
function domainobot_options() {
	add_options_page( 'Domainobot Settings', 'Domainobot', 'administrator', __FILE__, 'domainobot_options_page' ); 
}

//	hook up options page
add_action( 'admin_menu', 'domainobot_options' );


/**	Dashboard */

//	dashboard widget output
function domainobot_dashboard_widget() {
	// retrieve option
	$domainobot_list = get_option( 'domainobot_list_op' );
	if ( $domainobot_list != NULL ) {
		
		$domain_array = explode( "\n", $domainobot_list );
		
		echo '<table id="domainobot-table" width="100%" class="form-table" cellpadding="1px">';
		foreach ( $domain_array as $domain ) { 
			$domain_status = new DomainStatus( $domain );
			echo '<tr><th scope="row"><a href="'. esc_url( $domain ) .'">' . esc_html( $domain ) . '</a></th><td>' . esc_html( $domain_status->expiry_date ) . '</td></tr>';
		}		
		
		echo '</table>';
		
	} else {
		echo 'Add your list of domains on the <a href="' . menu_page_url( 'domainobot/domainobot.php', false ) . '">settings page</a>.';
	}
	
} 

//	add dashboard widget
function domainobot_add_dashboard_widget() {
	wp_add_dashboard_widget( 'domainobot_dashboard_widget', 'Renewal Tracker <small>- Domainobot &trade;</small>', 'domainobot_dashboard_widget' );	
} 

//	hook up dashboard widget
add_action( 'wp_dashboard_setup', 'domainobot_add_dashboard_widget' );
