<?php /**
 * @package PHP_Whois
 */
/*
Plugin Name: Domain Status
Plugin URI: http://skylinedesign.co.ke/labs/domain-status/
Description: A simple plugin that keeps you informed concerning your domain status, and alerts you when renewal is due.
Author: Huston Malande, Brian Wangila
Version: 1.0
Author URI: http://skylinedesign.co.ke/martians/
*/

// include whois class
include('lib/whois.main.php');

// define querying class
class DomainStatus {
	var $domain = '';
	var $result = '';
	var $expiry_date = '';

	function __construct($domain = '') {
		$this->set_domain($domain);
		$this->get_expiry();
	}

	function set_domain($domain = '') {
		if (!empty($domain) && $domain != '') {
			$this->domain = $domain;
		} else {
			// auto-detect domain name
			$this->domain = str_replace("www.","", $_SERVER['HTTP_HOST']);
		}
	}

	function get_expiry() {
		$whois = new Whois();
		$query = $this->domain;
		$this->result = $whois->Lookup($query);
		$this->expiry_date = $this->result['regrinfo']['domain']['expires'];
		$this->format_expiry();
	}

	function format_expiry() {
		$this->expiry_date = strtotime($this->expiry_date);
		// format 'l jS F Y'
		$this->expiry_date = date('jS F Y', $this->expiry_date);
	}
}


// check if we need to display the auto-detected,
// current domain status on the top-right
$current  = get_option('domain_show_current_op');
if ($current == 1) {
	
	// function for output
	function current_domain_status() {
		// $domain = 'put_your_test_domain_here_and_uncomment';
		// output
		$domain_status = new DomainStatus($domain);
		echo "<p id='dstatus'>Domain renewal: " . $domain_status->expiry_date . "</p>";
	}

	// hook up the output
	add_action( 'admin_notices', 'current_domain_status' );

	// CSS to position the paragraph
	function domain_status_css() {
		// makes sure positioning is also good for right-to-left languages
		$x = is_rtl() ? 'left' : 'right';
	
		echo "
		<style type='text/css'>
		#dstatus {
			float: right;
			padding: 3px 15px 2px;
			margin: 0;
			font-size: 11px;
			background: #F8F8F8;
			border-bottom-left-radius: 3px;
			border-bottom-right-radius: 3px;
			border: 1px solid #EEE;
		}
		</style>
		";
	}

	// hook up the CSS
	add_action( 'admin_head', 'domain_status_css' );

}


// options page
function domain_status_options_page() { ?>

	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Domain Status Settings</h2>
		<?php 
		$domain_list_saved = get_option('domain_list_op');
		if(isset($_POST['Submit'])) {
			$domain_list_saved = $_POST["domain_list"];
			$domain_show_current_saved = $_POST["domain_show_current"];
			update_option( 'domain_list_op', $domain_list_saved );
			update_option( 'domain_show_current_op', $domain_show_current_saved ); ?>
			<div class="updated">
				<p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p>
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
					<p><input type="checkbox" name="domain_show_current" value="1" <?php checked(true, get_option('domain_show_current_op')); ?> /> Show current domain (top right)</p>
				</td> 
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" value="Update" class="button-primary" />
		</p>
		</form>
	</div>
	
<?php }

// add options
function domain_status_options() {
	add_options_page('Domain Status Settings', 'Domain Status', 'administrator', __FILE__, 'domain_status_options_page'); 
}

// hook up options page
add_action('admin_menu', 'domain_status_options');


// dashboard widget output
function domain_dashboard_widget() {
	// retrieve option
	$domain_list = get_option("domain_list_op");
	if ($domain_list != NULL) {
		$domain_array = explode("\n", $domain_list);
		foreach ($domain_array as $domain) {
			$domain_status = new DomainStatus($domain);
			echo "<p>" . $domain . ": " . $domain_status->expiry_date . "</p>";
		}
	} else {
		echo "Add your list of domains on the <a href=\"" . menu_page_url( 'phpwhois/domainstatus.php', false ) . "\">settings page</a>.";
	}
	
} 

// add dashboard widget
function domain_add_dashboard_widgets() {
	wp_add_dashboard_widget('domain_dashboard_widget', 'Domain Status', 'domain_dashboard_widget');	
} 

// hook up dashboard widget
add_action('wp_dashboard_setup', 'domain_add_dashboard_widgets' ); 

?>