<?php
/*
Plugin Name: Domainobot
Plugin URI: http://domainobot.com
Description: A simple Whois utility plugin that keeps you informed concerning your domain status and alerts you when renewal is due
Author: Skyline Design Ltd
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

	var $domain    	= '';
	var $expiry_date    = '';
	var $days_to_expiry = '';
	var $errors         = array();

	public function __construct( $domain = '' ) {
		$this->set_domain( $domain );
		$this->get_expiry();
	}

	public function set_domain( $domain = '' ) {
		if ( ! empty( $domain ) && $domain != '' ) {

			$domain = strtolower( trim( $domain ));
			$domain = preg_replace( '/^http:\/\//i', '', $domain );
			$domain = preg_replace( '/^www\./i', '', $domain );
			$domain = explode( '/', $domain );
			$domain = trim( $domain[0] );

			if ( preg_match('/^([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $domain )) {
			     $this->domain = $domain;
			} else {
				array_push( $this->errors, 'The domain, <strong>' . $domain . '</strong>, is not valid' );
			}
		}
	}

	public function get_expiry() {		
		// run specific ccTLD's check first
		$custom_cctld_expiry = custom_cctld_check( $this->domain );
		
		if ( $custom_cctld_expiry ) {
			$this->expiry_date = $custom_cctld_expiry;
		} else {
			// use phpwhois class
			$whois = new Whois();
			$result = $whois->Lookup( $this->domain );
			$this->expiry_date = $result['regrinfo']['domain']['expires'];
			$this->status = $result['regrinfo']['domain']['status'];
		}
		
		// format date
		$unix_expiry_date = strtotime( $this->expiry_date );
		$this->expiry_date = date( 'jS F Y', $unix_expiry_date );
		$this->days_to_expiry = intval(( $unix_expiry_date - time() ) / ( 60 * 60 * 24 ));

		// get highlight class
		$this->highlight_class();
	}
	
	private function highlight_class() {
		$days_left_op = get_option( 'domainobot_days_left_op' );
		
		if ( $this->days_to_expiry < 0 ) {
			$this->highlight_class = esc_attr( 'expired' );
		} elseif ( $this->days_to_expiry > 0 && $this->days_to_expiry < $days_left_op ) {
			$this->highlight_class = esc_attr( 'soon' );
		} else {
			$this->highlight_class = esc_attr( 'safe' );
		}
	}
}

/**	Cron jobs */

//	upon activation
register_activation_hook( __FILE__, 'domainobot_activation' );

function domainobot_activation() {
	wp_schedule_event( current_time( 'timestamp' ), 'daily', 'domainobot_whois_update' );
	update_option( 'domainobot_days_left_op', 30 );
}

function domainobot_update_whois_daily() {
	// run every 24hrs
	$domain_status = new DomainStatus();
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
}

//	clean up after deletion
register_uninstall_hook( __FILE__, 'domainobot_deletion' );

function domainobot_deletion() {
	// clear db values
	delete_option( 'domainobot_list_op' );
	delete_option( 'domainobot_show_current_op' );
	delete_option( 'domainobot_current_expiry_op' );
	delete_option( 'domainobot_days_left_op' );
	delete_option( 'domainobot_email_alerts_op' );
}


/**	Global settings */

// 	extract 'days left' option
$days_left_op = get_option( 'domainobot_days_left_op' );

// 	extract 'current domain' option
$show_current  = get_option( 'domainobot_show_current_op' );


/**	Stylesheets */

//	css file
function domainobot_css() {
	wp_register_style( 'domainobot-style', plugins_url( 'assets/domainobot.css', __FILE__ ) );
	wp_enqueue_style( 'domainobot-style' );	
}

//	hook up the css
add_action( 'admin_print_styles', 'domainobot_css' );


/**	Current Domain */

/* 	check if we need to display the auto-detected,
	current domain status on the top-right */
	
if ($show_current == 1) {
	
	// function for output
	function domainobot_current_domain() {
		
		$current_expiry_cached = get_option( 'domainobot_current_expiry_op' );

		// $domain = 'put_your_test_domain_here_and_uncomment';
		if ( $current_expiry_cached == '' || $current_expiry_cached == '1st January 1970' ) { 
			$domain_status = new DomainStatus( $domain );
			$domain_expiry = $domain_status->expiry_date;
			update_option( 'domainobot_current_expiry_op', $domain_expiry );
			$current_expiry_cached = $domain_expiry;
		}

		echo '<p id="domainobot-bar" class="' . $domain_status->highlight_class . '">Domain renewal: ' . esc_html( $current_expiry_cached ) . '</p>';
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
		$domainobot_days_left_saved = get_option( 'domainobot_days_left_op' );
		if( isset( $_POST['Submit'] ) ) {
			$domainobot_list_saved = $_POST["domainobot_list"];
			$domainobot_show_current_saved = $_POST["domainobot_show_current"];
			$domainobot_days_left_saved = $_POST["domainobot_days_left"];
			intval( $domainobot_days_left_saved );
			$domainobot_email_alerts_saved = $_POST["domainobot_email_alerts"];
			update_option( 'domainobot_list_op', $domainobot_list_saved );
			update_option( 'domainobot_show_current_op', $domainobot_show_current_saved );
			update_option( 'domainobot_days_left_op', $domainobot_days_left_saved );
			update_option( 'domainobot_email_alerts_op', $domainobot_email_alerts_saved ); ?>
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
					<p><textarea id="domainobot_list" name="domainobot_list" rows="5" cols="50"><?php echo esc_textarea( $domainobot_list_saved ); ?></textarea></p>
				</td> 
			</tr>
			<tr>
				<th scope="row">Current domain</th>
				<td>
					<p>
						<input type="checkbox" id="domainobot_show_current" name="domainobot_show_current" value="1" <?php checked( true, get_option( 'domainobot_show_current_op' ) ); ?> />
						<label for="domainobot_show_current">Show current domain (top right)</label>
					</p>
				</td> 
			</tr>
			<tr>
				<th scope="row">Days left</th>
				<td>
					<p>Highlight domains that have the following number of days left before they expire.</p>
					<p><input type="number" id="domainobot_days_left" name="domainobot_days_left" value="<?php echo esc_html( $domainobot_days_left_saved ); ?>" min="0" max="90" /></p>
				</td> 
			</tr>
			<tr>
				<th scope="row">Email notifications</th>
				<td>
					<input type="checkbox" id="domainobot_email_alerts" name="domainobot_email_alerts" value="1" <?php checked( true, get_option( 'domainobot_email_alerts_op' ) ); ?> />
					<label for="domainobot_email_alerts">Notify <a href="<?php echo admin_url( 'users.php?role=administrator' ); ?>" class="no-underline">admins</a> <?php if ( $domainobot_days_left_saved > 0 ) echo esc_html( $domainobot_days_left_saved ) . ' days'; ?> before a domain expires</label>
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

		// VALIDATE DOMAINS
		$domains = array();
		foreach ( $domain_array as $domain ) {
			if (validate_domain($domain)) {
				$domains[] = $domain;
			}
		}

		foreach ($domains as $domain) {
			$domain_status = new DomainStatus( $domain );
			
			if ( $domain_status->highlight_class == 'expired' ) {
				$info = '<span>' . $domain_status->status . '</span>';
			} elseif ( $domain_status->highlight_class == 'soon' ) {
				$info = '<span>' . $domain_status->days_to_expiry . ' days</span>';
			} else {
				$info= '';
			}
			
			echo	'<tr class="'. $domain_status->highlight_class .'">
						<th scope="row"><a href="'. esc_url( $domain_status->domain ) .'">' . esc_html( $domain_status->domain ) . '</a></th>
						<td>' . esc_html( $domain_status->expiry_date ) . ' ' . $info . '</td>
					</tr>';
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


	function validate_domain( $domain ) {
		if ( ! empty( $domain ) && $domain != '' ) {

			$domain = strtolower( trim( $domain ));
			$domain = preg_replace( '/^http:\/\//i', '', $domain );
			$domain = preg_replace( '/^www\./i', '', $domain );
			$domain = explode( '/', $domain );
			$domain = trim( $domain[0] );

			if ( preg_match('/^([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $domain )) {
			     return $domain;
			} else {
				return FALSE;
			}
		}
	}
