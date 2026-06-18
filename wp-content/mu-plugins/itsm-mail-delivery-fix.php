<?php
/**
 * Plugin Name: ITSM Mail Delivery Fix
 * Description: Aligns WordPress mail sender settings with the authenticated SMTP account for onboarding emails.
 * Version: 1.0.1
 * Author: Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_mail_from', static function ( $from ) {
	return 'itdevops@itsmfieldtechs.com';
} );

add_filter( 'wp_mail_from_name', static function ( $name ) {
	return 'ITSM SOLUTIONS LLC';
} );
