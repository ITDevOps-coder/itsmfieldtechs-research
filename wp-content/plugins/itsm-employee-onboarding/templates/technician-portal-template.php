<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

nocache_headers();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'itsm-tech-portal-template' ); ?>>
<?php wp_body_open(); ?>
<main id="primary" class="site-main itsm-tech-portal-template-main">
	<?php echo do_shortcode( '[itsm_technician_portal]' ); ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
