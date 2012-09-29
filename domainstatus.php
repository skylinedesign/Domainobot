<?php
/**
 * @package DomainStatus
 * 
 */

/*
Plugin Name: Domain Status
Plugin URI: http://skylinedesign.co.ke/labs/domain-status
Description: A simple plugin that keeps you informed concerning your domain's status, and alerts you when renewal is due
Author: Huston Malande & Brian Wangila
Version: 1.0
Author URI: http://skylinedesign.co.ke/martians
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
register_activation_hook( __FILE__, 'domain_status_activation' );

function domain_status_activation() {
	wp_schedule_event( current_time( 'timestamp' ), 'daily', 'whois_update' );
}

function update_whois_daily() {
	// run every 24hrs
	$domain_status = new DomainStatus( $domain );
	$domain_expiry = $domain_status->expiry_date;
	update_option( 'domain_current_expiry', $domain_expiry );
}

add_action( 'whois_update', 'update_whois_daily' );


/**	Cleaning up */

//	clean up after deactivation
register_deactivation_hook( __FILE__, 'domain_status_deactivation' );

function domain_status_deactivation() {
	// clear cron
	wp_clear_scheduled_hook( 'whois_update' );
	delete_option( 'domain_show_current_op' );
	delete_option( 'domain_current_expiry' );
}

//	clean up after deletion
register_uninstall_hook( __FILE__, 'domain_status_deletion' );

function domain_status_deletion() {
	// clear db values
	delete_option( 'domain_list_op' );
}


/**	Stylesheets */

//	css file
function domain_status_css() {
	wp_register_style( 'domain-status-style', plugins_url( 'domainstatus.css', __FILE__ ) );
	wp_enqueue_style( 'domain-status-style' );	
}

//	hook up the css
add_action( 'admin_print_styles', 'domain_status_css' );


/**	Current Domain */

/* 	check if we need to display the auto-detected,
	current domain status on the top-right */
$current  = get_option( 'domain_show_current_op' );
if ($current == 1) {
	
	// function for output
	function current_domain_status() {
		// $domain = 'put_your_test_domain_here_and_uncomment';
		$domain_status = new DomainStatus( $domain );
		echo "<p id='domain-status'>Domain renewal: " . $domain_status->expiry_date . "</p>";
	}

	// hook up the output
	add_action( 'admin_notices', 'current_domain_status' );

}


/**	Options */

//	options page
function domain_status_options_page() { ?>

	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Domain Status Settings</h2>
		<?php 
		$domain_list_saved = get_option( 'domain_list_op' );
		if( isset( $_POST['Submit'] ) ) {
			$domain_list_saved = $_POST["domain_list"];
			$domain_show_current_saved = $_POST["domain_show_current"];
			update_option( 'domain_list_op', $domain_list_saved );
			update_option( 'domain_show_current_op', $domain_show_current_saved ); ?>
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
					<p><textarea name="domain_list" rows="5" cols="50"><?php echo $domain_list_saved; ?></textarea></p>
				</td> 
			</tr>
			<tr>
				<th scope="row">Current Domain</th>
				<td>
					<p><input type="checkbox" name="domain_show_current" value="1" <?php checked( true, get_option( 'domain_show_current_op' ) ); ?> /> Show current domain (top right)</p>
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
function domain_status_options() {
	add_options_page( 'Domain Status Settings', 'Domain Status', 'administrator', __FILE__, 'domain_status_options_page' ); 
}

//	hook up options page
add_action( 'admin_menu', 'domain_status_options' );


/**	Dashboard */

//	dashboard widget output
function domain_dashboard_widget() {
	// retrieve option
	$domain_list = get_option( 'domain_list_op' );
	if ( $domain_list != NULL ) {
		
		$domain_array = explode( "\n", $domain_list );
		
		echo '<table id="domain-table" width="100%" class="form-table" cellpadding="1px">';
		foreach ( $domain_array as $domain ) { 
			$domain_status = new DomainStatus( $domain );
			echo '<tr><th scope="row">' . $domain . '</th><td>' . $domain_status->expiry_date . '</td></tr>';
		}		
		
		echo '</table>';
		
	} else {
		echo 'Add your list of domains on the <a href="' . menu_page_url( 'phpwhois/domainstatus.php', false ) . '">settings page</a>.';
	}
	
} 

//	add dashboard widget
function domain_add_dashboard_widgets() {
	wp_add_dashboard_widget( 'domain_dashboard_widget', 'Domain Status', 'domain_dashboard_widget' );	
} 

//	hook up dashboard widget
add_action( 'wp_dashboard_setup', 'domain_add_dashboard_widgets' );
