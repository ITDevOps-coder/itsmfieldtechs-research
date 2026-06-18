<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$contact_url = get_permalink( 21593 );
if ( ! $contact_url ) {
	$contact_url = home_url( '/contact/' );
}

$portal_url = home_url( '/technician-portal/' );
?>
<main class="itsm-enterprise-page">
	<section class="itsm-enterprise-hero">
		<div class="itsm-enterprise-hero__wrap">
			<div class="itsm-enterprise-hero__content">
				<div>
					<span class="itsm-enterprise-kicker"><?php esc_html_e( 'Careers', 'itsm-enterprise-redesign' ); ?></span>
					<h1 class="itsm-enterprise-title"><?php esc_html_e( 'Build your technician career with a clearer, modern application flow.', 'itsm-enterprise-redesign' ); ?></h1>
					<p class="itsm-enterprise-subtitle"><?php esc_html_e( 'Explore opportunities, review expectations, and complete onboarding in a structured process designed for field technicians.', 'itsm-enterprise-redesign' ); ?></p>
					<div class="itsm-enterprise-actions">
						<a class="itsm-enterprise-button" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact Recruiting', 'itsm-enterprise-redesign' ); ?></a>
						<a class="itsm-enterprise-button-secondary" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Technician Portal', 'itsm-enterprise-redesign' ); ?></a>
					</div>
				</div>
				<div class="itsm-enterprise-hero__panel">
					<div class="itsm-enterprise-hero__stats">
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Step 1', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Apply', 'itsm-enterprise-redesign' ); ?></span>
						</div>
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Step 2', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Review', 'itsm-enterprise-redesign' ); ?></span>
						</div>
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Step 3', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Onboard', 'itsm-enterprise-redesign' ); ?></span>
						</div>
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Step 4', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Portal access', 'itsm-enterprise-redesign' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section">
		<div class="itsm-enterprise-section__wrap">
			<div class="itsm-enterprise-section__heading">
				<h2 class="itsm-enterprise-section__title"><?php esc_html_e( 'What technicians can expect', 'itsm-enterprise-redesign' ); ?></h2>
				<p class="itsm-enterprise-section__subtitle"><?php esc_html_e( 'A tidy application and onboarding journey, clear expectations, and a private dashboard once approved.', 'itsm-enterprise-redesign' ); ?></p>
			</div>
			<div class="itsm-enterprise-grid">
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Professional process', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Structured steps keep the application, onboarding, and approval flow easy to understand.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Secure records', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Approved technicians get access to a private portal for documents, attendance, and account actions.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Responsive experience', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'The page is optimized for desktop and mobile so technicians can apply from anywhere.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Clear next step', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'When applicants are selected, the onboarding email guides them through the next action.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section itsm-enterprise-section--soft enterprise-content-bridge">
		<div class="itsm-enterprise-section__wrap">
			<?php
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					the_content();
				endwhile;
			endif;
			?>
		</div>
	</section>
</main>
<?php
get_footer();
