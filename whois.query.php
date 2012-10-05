<?php

//	include lookup classes
include( 'lib/whois.main.php' );
include( 'lib/whois.custom.php' );

//	define querying class
class DomainStatus {

	var $domain         = '';
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
		$this->days_to_expiry = intval( ( $unix_expiry_date - time() ) / ( 60 * 60 * 24 ) );

		// get highlight class
		$this->highlight_class();
	}

	private function highlight_class() {
		$options = get_option( 'domainobot_options' );
		$days_left = $options['countdown'];

		if ( $this->days_to_expiry < 0 ) {
			$this->highlight_class = esc_attr( 'expired' );
		} elseif ( $this->days_to_expiry > 0 && $this->days_to_expiry < $days_left ) {
			$this->highlight_class = esc_attr( 'soon' );
		} else {
			$this->highlight_class = esc_attr( 'safe' );
		}
	}
}
