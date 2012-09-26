<?php
/**
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
		include('whois.main.php');
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
		$this->expiry_date = date('l jS F Y', $this->expiry_date);
	}
}

// Test
$d = new DomainStatus('bwangila.com');
echo $d->expiry_date

?>