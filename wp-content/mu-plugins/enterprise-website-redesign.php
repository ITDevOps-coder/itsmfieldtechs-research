<?php
/**
 * Plugin Name: ITSM Enterprise Website Redesign
 * Description: Dev-safe website redesign layer for the public site, homepage, and careers page.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'itsm_enterprise_redesign_is_public_site' ) ) {
	function itsm_enterprise_redesign_is_public_site() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		$portal_page_id = 23866;
		if ( is_page( $portal_page_id ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'itsm_enterprise_redesign_body_class' ) ) {
	function itsm_enterprise_redesign_body_class( $classes ) {
		if ( itsm_enterprise_redesign_is_public_site() ) {
			$classes[] = 'enterprise-site';
		}

		if ( is_front_page() ) {
			$classes[] = 'enterprise-home-shell';
		}

		if ( is_page( 21582 ) || is_page( 'careers' ) ) {
			$classes[] = 'enterprise-careers-shell';
		}

		return $classes;
	}
	add_filter( 'body_class', 'itsm_enterprise_redesign_body_class' );
}

if ( ! function_exists( 'itsm_enterprise_redesign_enqueue_assets' ) ) {
	function itsm_enterprise_redesign_enqueue_assets() {
		if ( ! itsm_enterprise_redesign_is_public_site() ) {
			return;
		}

		$asset_url = plugin_dir_url( __FILE__ ) . 'enterprise-website-redesign/assets/enterprise-redesign.css';
		wp_enqueue_style( 'itsm-enterprise-redesign', $asset_url, [], '1.0.0' );
	}
	add_action( 'wp_enqueue_scripts', 'itsm_enterprise_redesign_enqueue_assets', 20 );
}

if ( ! function_exists( 'itsm_enterprise_redesign_menu_items' ) ) {
	function itsm_enterprise_redesign_menu_items( $items, $args ) {
		if ( is_admin() || ! itsm_enterprise_redesign_is_public_site() ) {
			return $items;
		}

		$locations = array( 'menu-1', 'menu-2', 'menu-4', 'menu-5' );
		if ( empty( $args->theme_location ) || ! in_array( $args->theme_location, $locations, true ) ) {
			return $items;
		}

		$contact_url = get_permalink( 21593 );
		if ( ! $contact_url ) {
			$contact_url = home_url( '/contact/' );
		}

		$cta = sprintf(
			'<li class="menu-item menu-item-enterprise-cta"><a class="enterprise-nav-cta" href="%s">%s</a></li>',
			esc_url( $contact_url ),
			esc_html__( 'Contact ITSM', 'itsm-enterprise-redesign' )
		);

		return $items . $cta;
	}
	add_filter( 'wp_nav_menu_items', 'itsm_enterprise_redesign_menu_items', 20, 2 );
}

if ( ! function_exists( 'itsm_enterprise_redesign_template_include' ) ) {
	function itsm_enterprise_redesign_template_include( $template ) {
		if ( is_admin() ) {
			return $template;
		}

		if ( is_front_page() ) {
			$front = plugin_dir_path( __FILE__ ) . 'enterprise-website-redesign/templates/front-page.php';
			if ( file_exists( $front ) ) {
				return $front;
			}
		}

		if ( is_page( 21582 ) || is_page( 'careers' ) ) {
			$careers = plugin_dir_path( __FILE__ ) . 'enterprise-website-redesign/templates/page-careers.php';
			if ( file_exists( $careers ) ) {
				return $careers;
			}
		}

		return $template;
	}
	add_filter( 'template_include', 'itsm_enterprise_redesign_template_include', 99 );
}
