<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$careers_url = get_permalink( 21582 );
if ( ! $careers_url ) {
	$careers_url = home_url( '/careers/' );
}

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
					<span class="itsm-enterprise-kicker"><?php esc_html_e( 'Enterprise IT field operations', 'itsm-enterprise-redesign' ); ?></span>
					<h1 class="itsm-enterprise-title"><?php esc_html_e( 'Nationwide field support built for dependable execution.', 'itsm-enterprise-redesign' ); ?></h1>
					<p class="itsm-enterprise-subtitle"><?php esc_html_e( 'ITSM Field Techs helps organizations coordinate onsite technicians, onboarding, dispatch, and secure employee workflows with a clean, professional experience.', 'itsm-enterprise-redesign' ); ?></p>
					<div class="itsm-enterprise-actions">
						<a class="itsm-enterprise-button" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact ITSM', 'itsm-enterprise-redesign' ); ?></a>
						<a class="itsm-enterprise-button-secondary" href="<?php echo esc_url( $careers_url ); ?>"><?php esc_html_e( 'Apply as a Technician', 'itsm-enterprise-redesign' ); ?></a>
					</div>
				</div>
				<div class="itsm-enterprise-hero__panel">
					<div class="itsm-enterprise-hero__stats">
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Coverage', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Nationwide support', 'itsm-enterprise-redesign' ); ?></span>
						</div>
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Operations', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( '24/7 dispatch-ready', 'itsm-enterprise-redesign' ); ?></span>
						</div>
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Workflow', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Secure onboarding', 'itsm-enterprise-redesign' ); ?></span>
						</div>
						<div class="itsm-enterprise-stat">
							<span class="itsm-enterprise-stat__label"><?php esc_html_e( 'Access', 'itsm-enterprise-redesign' ); ?></span>
							<span class="itsm-enterprise-stat__value"><?php esc_html_e( 'Technician portal', 'itsm-enterprise-redesign' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section">
		<div class="itsm-enterprise-section__wrap">
			<div class="itsm-enterprise-section__heading">
				<h2 class="itsm-enterprise-section__title"><?php esc_html_e( 'Enterprise services that stay organized from request to resolution.', 'itsm-enterprise-redesign' ); ?></h2>
				<p class="itsm-enterprise-section__subtitle"><?php esc_html_e( 'A modern service model for multi-location organizations that need reliable onsite execution and a clear technician experience.', 'itsm-enterprise-redesign' ); ?></p>
			</div>
			<div class="itsm-enterprise-grid">
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Field services', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Onsite support, installations, and troubleshooting for distributed environments that need dependable technician coverage.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Onboarding workflow', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'A secure, step-by-step onboarding flow that keeps applicant data, approvals, and technician access organized.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Dispatch coordination', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Clear process handoffs that help teams move from request intake to technician assignment without confusion.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Technician portal', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Approved technicians get private portal access for profile details, documents, attendance, and secure account actions.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section itsm-enterprise-section--soft">
		<div class="itsm-enterprise-section__wrap">
			<div class="itsm-enterprise-section__heading">
				<h2 class="itsm-enterprise-section__title"><?php esc_html_e( 'Operational confidence, without overstatement.', 'itsm-enterprise-redesign' ); ?></h2>
				<p class="itsm-enterprise-section__subtitle"><?php esc_html_e( 'The redesign keeps the messaging clear and professional while avoiding unverified claims or inflated metrics.', 'itsm-enterprise-redesign' ); ?></p>
			</div>
			<div class="itsm-enterprise-proof">
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Private workflow', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Onboarding and technician records remain protected and approval-gated.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Readable process', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'The interface uses strong contrast, clear hierarchy, and responsive spacing.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card">
					<h3><?php esc_html_e( 'Accountability built in', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'From applicant acknowledgement to portal login, the journey stays traceable and organized.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section">
		<div class="itsm-enterprise-section__wrap">
			<div class="itsm-enterprise-section__heading">
				<h2 class="itsm-enterprise-section__title"><?php esc_html_e( 'How it works', 'itsm-enterprise-redesign' ); ?></h2>
				<p class="itsm-enterprise-section__subtitle"><?php esc_html_e( 'A simple flow that guides applicants, reviewers, and approved technicians through the right steps.', 'itsm-enterprise-redesign' ); ?></p>
			</div>
			<div class="itsm-enterprise-process">
				<div class="itsm-enterprise-card itsm-enterprise-step">
					<h3><?php esc_html_e( 'Request and review', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Capture the request, review the job fit, and move the right applicant into onboarding.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card itsm-enterprise-step">
					<h3><?php esc_html_e( 'Secure onboarding', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Collect the required profile, tax confirmation, and identity upload without exposing sensitive data.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
				<div class="itsm-enterprise-card itsm-enterprise-step">
					<h3><?php esc_html_e( 'Approved technician access', 'itsm-enterprise-redesign' ); ?></h3>
					<p><?php esc_html_e( 'Once approved, the technician receives private portal access for documents, attendance, and account actions.', 'itsm-enterprise-redesign' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section itsm-enterprise-cta-band">
		<div class="itsm-enterprise-section__wrap">
			<div class="itsm-enterprise-section__heading">
				<h2 class="itsm-enterprise-section__title"><?php esc_html_e( 'Need support, service coverage, or technician onboarding?', 'itsm-enterprise-redesign' ); ?></h2>
				<p class="itsm-enterprise-section__subtitle"><?php esc_html_e( 'Choose the path that fits your needs and keep the workflow moving in the right direction.', 'itsm-enterprise-redesign' ); ?></p>
			</div>
			<div class="itsm-enterprise-actions" style="justify-content:center;">
				<a class="itsm-enterprise-button" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact ITSM', 'itsm-enterprise-redesign' ); ?></a>
				<a class="itsm-enterprise-button-secondary" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Technician Portal', 'itsm-enterprise-redesign' ); ?></a>
			</div>
		</div>
	</section>

	<section class="itsm-enterprise-section enterprise-content-bridge">
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
