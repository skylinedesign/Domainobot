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

class DomainStatus {
	var $domain = '';
	var $result = '';
	var $expiry_date = '';

	function __construct($domain = '') {
		include('lib/whois.main.php');
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

function domain_status($domain) {
	if ( !isset($domain) || $domain == NULL) {
		// uncomment the line below for live use
		// $domain = str_replace("www.","", $_SERVER['HTTP_HOST']);
		// comment the line below for live use
		$domain = 'bwangila.com';
	}
	
	// output
	$d = new DomainStatus($domain);
	echo "<p id='dstatus'>Domain renewal: " . $d->expiry_date . "</p>";

}

// hook up the output
add_action( 'admin_notices', 'domain_status' );

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


// options page
add_action('admin_menu', function() {

add_options_page('Domain Status Settings', 'Domain Status', 'administrator', __FILE__, function () {
	?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Domain Status Settings</h2>
		<?php 
		$domain_list_saved = get_option('$domain_list_op');
		if(isset($_POST['Submit'])) {
			$domain_list_saved = $_POST["domain_list"];
			update_option( '$domain_list_op', $domain_list_saved ); ?>
			<div class="updated">
				<p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p>
			</div>
		<?php } ?>

		<form method="post" name="options" action="">
		<br />
		<table width="100%" class="form-table">
			<tr>
				<th scope="row"><strong>Domains</strong></th>
				<td>
					<p>List of domains you'd like to monitor (max 10, each on a new line)</p>
					<p><textarea name="domain_list" rows="5" cols="50"><?php echo $domain_list_saved; ?></textarea></p>
				</td> 
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" value="Update" class="button-primary" />
		</p>
		</form>
	</div>
	<?php
}); 


});


?>