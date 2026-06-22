<?php
/**
 * Plugin Name: ITSM Native Mail Bridge
 * Description: Sends WordPress mail through the server native mail() transport instead of SMTP.
 * Version: 1.0.1
 * Author: Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'pre_wp_mail', function( $null, $atts ) {
	$to = $atts['to'] ?? '';
	if ( empty( $to ) ) {
		return $null;
	}

	$to = is_array( $to ) ? implode( ',', array_map( 'sanitize_email', $to ) ) : sanitize_email( $to );
	$subject = sanitize_text_field( $atts['subject'] ?? '' );
	$message  = (string) ( $atts['message'] ?? '' );
	$headers  = $atts['headers'] ?? [];

	if ( is_string( $headers ) ) {
		$headers = preg_split( '/\r?\n/', $headers );
	}

	$from_email   = 'itdevops@itsmfieldtechs.com';
	$from_name    = 'ITSM SOLUTIONS LLC';
	$content_type = '';
	$header_lines = [];
	$header_lines[] = 'From: ' . $from_name . ' <' . $from_email . '>';
	$header_lines[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
	$header_lines[] = 'MIME-Version: 1.0';

	foreach ( (array) $headers as $header ) {
		$header = trim( (string) $header );
		if ( '' === $header ) {
			continue;
		}
		if ( stripos( $header, 'content-type:' ) === 0 ) {
			$content_type = trim( substr( $header, strlen( 'content-type:' ) ) );
			continue;
		}
		if ( stripos( $header, 'from:' ) === 0 || stripos( $header, 'reply-to:' ) === 0 ) {
			continue;
		}
		$header_lines[] = $header;
	}

	if ( '' === $content_type ) {
		$content_type = (string) apply_filters( 'wp_mail_content_type', 'text/plain' );
	}
	if ( false === stripos( $content_type, 'charset=' ) ) {
		$content_type .= '; charset=UTF-8';
	}
	$header_lines[] = 'Content-Type: ' . $content_type;

	$header_str = implode( "\r\n", $header_lines );
	$subject = preg_replace( '/[\r\n]+/', ' ', $subject );
	$sent = mail( $to, $subject, $message, $header_str, '-f' . $from_email );
	return $sent;
}, 10, 2 );
