<?php

function custom_cctld_check( $domain ) {

	// define special ccTLD servers
	$cctld_servers = array(
		"ke" => "whois.kenic.or.ke",
		"mc" => "whois.ripe.net",
		"nl" => "whois.domain-registry.nl"
	);
	
	$results = '';

	// run check
	foreach ( $cctld_servers as $cctld_ext => $cctld_server ) {
		$ext_string = "." . $cctld_ext;
		$pos = strpos($domain, $ext_string);
    	if ($pos != NULL) {
			$results = extract_cctld_expiry( $domain, $cctld_server );
			break;
		} else {
			$results = false;
		}
	}
	
	return $results;

}

function extract_cctld_expiry( $domain, $cctld_server ) {

	// fix domain name:
	$domain = strtolower( trim( $domain ));
	$domain = preg_replace( '/^http:\/\//i', '', $domain );
	$domain = preg_replace( '/^www\./i', '', $domain );
	$domain = explode( '/', $domain );
	$domain = trim( $domain[0] );
	
	// split TLD from domain name
	$_domain = explode( '.', $domain );
	$lst = count( $_domain )-1;
				
	$output = '';
	
	// connect to whois server:
	if ( $conn = @fsockopen ( $cctld_server, 43 ) ) {
		fputs( $conn, $domain."\r\n" );
		while( ! feof( $conn ) ) {
			$output .= fgets( $conn, 128 );
		}
		fclose( $conn );
	}
	else { echo '<p class="domainobot_error">server connection error: <em> ' . $cctld_server . '</em></p>'; }
	
	// extract expiry date
	$pos = strpos( $output, 'Expires: ' );
	$expiry_date = substr( $output, $pos + 9, 11 );
	
	return $expiry_date;
	
}