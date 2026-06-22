<?php
/**
 * Plugin Name: ITSM Employee Onboarding
 * Description: Dev-safe HireZoot/WP Job Openings onboarding workflow, document review, employee dashboard, and attendance.
 * Version: 0.1.0
 * Author: ITSM / Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ITSM_Employee_Onboarding {
	const OPTION_KEY = 'itsm_employee_onboarding_settings';
	const TOKEN_HASH_META = 'itsm_onboarding_token_hash';
	const TOKEN_CREATED_META = 'itsm_onboarding_token_created_at';
	const TOKEN_EXPIRES_META = 'itsm_onboarding_token_expires_at';
	const TOKEN_USED_META = 'itsm_onboarding_token_used_at';
	const STATE_META = 'itsm_onboarding_state';
	const RESPONSE_META = 'itsm_candidate_onboarding_response';
	const STATUS_LOG_META = 'itsm_status_email_log';
	const PROFILE_CPT = 'employee_profile';
	const EMPLOYEE_ROLE = 'itsm_employee';
	const SHORTCODE_FORM = 'itsm_employee_onboarding_form';
	const SHORTCODE_DASHBOARD = 'itsm_employee_dashboard';
	const SHORTCODE_PORTAL = 'itsm_technician_portal';
	const TAXBANDITS_URL = 'https://itsmso438336.w9request.com';
	const IRS_W9_URL = 'https://www.irs.gov/pub/irs-pdf/fw9.pdf';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_role' ] );
		add_action( 'init', [ $this, 'register_employee_profile_cpt' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_application_meta_boxes' ] );
		add_action( 'transition_post_status', [ $this, 'handle_status_transition' ], 30, 3 );
		add_action( 'template_redirect', [ $this, 'maybe_render_token_form' ] );
		add_filter( 'template_include', [ $this, 'filter_technician_portal_template' ], 99 );
		add_action( 'admin_post_itsm_onboarding_review', [ $this, 'handle_review_action' ] );
		add_action( 'admin_post_itsm_reset_status_email', [ $this, 'handle_reset_status_email' ] );
		add_action( 'admin_post_itsm_onboarding_download', [ $this, 'handle_document_download' ] );
		add_action( 'admin_post_itsm_attendance_action', [ $this, 'handle_attendance_action' ] );
		add_action( 'admin_post_itsm_portal_additional_document', [ $this, 'handle_portal_additional_document_upload' ] );
		add_action( 'admin_post_nopriv_itsm_onboarding_step', [ $this, 'handle_onboarding_step_post' ] );
		add_action( 'admin_post_itsm_onboarding_step', [ $this, 'handle_onboarding_step_post' ] );
		add_action( 'admin_post_nopriv_itsm_onboarding_upload_document', [ $this, 'handle_onboarding_upload_post' ] );
		add_action( 'admin_post_itsm_onboarding_upload_document', [ $this, 'handle_onboarding_upload_post' ] );
		add_action( 'wp_ajax_nopriv_itsm_onboarding_ajax_upload_document', [ $this, 'handle_onboarding_ajax_upload_document' ] );
		add_action( 'wp_ajax_itsm_onboarding_ajax_upload_document', [ $this, 'handle_onboarding_ajax_upload_document' ] );
		add_action( 'admin_post_nopriv_itsm_onboarding_final_submit', [ $this, 'handle_onboarding_final_post' ] );
		add_action( 'admin_post_itsm_onboarding_final_submit', [ $this, 'handle_onboarding_final_post' ] );
		add_action( 'admin_init', [ $this, 'block_employee_admin' ] );
		add_filter( 'login_redirect', [ $this, 'filter_login_redirect' ], 10, 3 );
		add_shortcode( self::SHORTCODE_FORM, [ $this, 'render_shortcode_form' ] );
		add_shortcode( self::SHORTCODE_DASHBOARD, [ $this, 'render_employee_dashboard' ] );
		add_shortcode( self::SHORTCODE_PORTAL, [ $this, 'render_technician_portal' ] );
	}

	public static function activate() {
		self::instance()->register_role();
		self::instance()->register_employee_profile_cpt();
		self::instance()->create_attendance_table();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private function defaults() {
		return [
			'enabled'               => 'yes',
			'test_mode'             => 'yes',
			'test_recipient'        => get_option( 'admin_email' ),
			'from_email'            => '',
			'admin_email'           => get_option( 'admin_email' ),
			'token_expiration_days' => 14,
			'max_file_size_mb'      => 10,
			'taxbandits_url'        => self::TAXBANDITS_URL,
			'irs_w9_url'            => self::IRS_W9_URL,
			'status_workflows'      => [
				'select'    => [
					'label'    => 'Selected',
					'enabled'  => 'yes',
					'subject'  => 'Next step in your ITSM application',
					'body'     => "Hello {candidate_name},\n\nYou have been selected for the next step. Please complete your secure onboarding package here:\n{onboarding_url}\n\nThank you,\nITSM Solutions",
					'template' => 'selected',
				],
				'reject'    => [
					'label'    => 'Rejected',
					'enabled'  => 'yes',
					'subject'  => 'Update on your ITSM application',
					'body'     => "Hello {candidate_name},\n\nThank you for applying. At this time, we are moving forward with other candidates.\n\nThank you,\nITSM Solutions",
					'template' => 'rejected',
				],
				'progress'  => [
					'label'    => 'In Progress',
					'enabled'  => 'yes',
					'subject'  => 'Your ITSM application is under review',
					'body'     => "Hello {candidate_name},\n\nYour application is currently under review. We will contact you when there is an update.\n\nThank you,\nITSM Solutions",
					'template' => 'in_progress',
				],
				'shortlist' => [
					'label'    => 'Shortlisted',
					'enabled'  => 'yes',
					'subject'  => 'Your ITSM application has been shortlisted',
					'body'     => "Hello {candidate_name},\n\nYour application has been shortlisted for further review. We will contact you about next steps.\n\nThank you,\nITSM Solutions",
					'template' => 'shortlisted',
				],
			],
		];
	}

	private function get_settings() {
		$settings = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( is_array( $settings ) ? $settings : [], $this->defaults() );
	}

	private function bool_value( $value ) {
		return in_array( strtolower( (string) $value ), [ '1', 'yes', 'true', 'on' ], true );
	}

	private function get_employee_dashboard_url() {
		$page = get_page_by_path( 'itsm-employee-dashboard' );
		if ( $page instanceof WP_Post ) {
			$url = get_permalink( $page );
			if ( $url ) {
				return $url;
			}
		}
		$pages = get_posts(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'suppress_filters' => true,
				's'              => '[' . self::SHORTCODE_DASHBOARD . ']',
			]
		);
		if ( ! empty( $pages ) && $pages[0] instanceof WP_Post ) {
			$url = get_permalink( $pages[0] );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/itsm-employee-dashboard/' );
	}

	private function has_employee_access( $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		return in_array( self::EMPLOYEE_ROLE, (array) $user->roles, true ) && ! user_can( $user, 'manage_options' );
	}

	private function render_portal_login_form() {
		$portal_url = $this->get_technician_portal_url();
		ob_start();
		?>
		<div class="itsm-tech-portal">
			<div class="itsm-tech-shell">
				<div class="itsm-tech-hero">
					<div>
						<div class="itsm-tech-badge"><?php esc_html_e( 'Technician Portal', 'itsm-employee-onboarding' ); ?></div>
						<h1><?php esc_html_e( 'Secure access for approved technicians', 'itsm-employee-onboarding' ); ?></h1>
						<p><?php esc_html_e( 'Log in to view your onboarding status, documents, attendance, and account settings.', 'itsm-employee-onboarding' ); ?></p>
					</div>
				</div>
				<div class="itsm-tech-grid">
					<section class="itsm-tech-card itsm-tech-card--full">
						<?php wp_login_form( [ 'redirect' => $portal_url, 'remember' => true ] ); ?>
						<p class="itsm-tech-actions" style="margin-top:12px;">
							<a class="button button-primary" href="<?php echo esc_url( wp_lostpassword_url( $portal_url ) ); ?>"><?php esc_html_e( 'Forgot password?', 'itsm-employee-onboarding' ); ?></a>
						</p>
					</section>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_technician_portal() {
		if ( ! is_user_logged_in() ) {
			return $this->render_portal_login_form();
		}
		if ( ! $this->has_employee_access() ) {
			return '<div class="itsm-tech-portal"><div class="itsm-tech-shell"><div class="itsm-tech-grid"><section class="itsm-tech-card itsm-tech-card--full"><h1>' . esc_html__( 'Access denied', 'itsm-employee-onboarding' ) . '</h1><p>' . esc_html__( 'This portal is reserved for approved technicians only.', 'itsm-employee-onboarding' ) . '</p></section></div></div></div>';
		}
		$attendance_action_result = $this->maybe_handle_portal_attendance_action();
		if ( is_wp_error( $attendance_action_result ) ) {
			$portal_url = $this->get_technician_portal_url();
			$message    = $attendance_action_result->get_error_message();
			wp_safe_redirect( add_query_arg( [ 'section' => 'attendance', 'itsm_attendance_notice' => 'error', 'itsm_attendance_message' => rawurlencode( $message ) ], $portal_url ) );
			exit;
		}
		return $this->render_technician_portal_dashboard();
	}

	public function filter_technician_portal_template( $template ) {
		if ( is_admin() ) {
			return $template;
		}
		$portal_page = get_page_by_path( 'technician-portal' );
		if ( ! ( $portal_page instanceof WP_Post ) || ! is_page( $portal_page->ID ) ) {
			return $template;
		}
		$custom_template = plugin_dir_path( __FILE__ ) . 'templates/technician-portal-template.php';
		return file_exists( $custom_template ) ? $custom_template : $template;
	}

	private function render_technician_portal_dashboard() {
		$user_id    = get_current_user_id();
		$profile_id = $this->get_employee_profile_for_user( $user_id );
		if ( ! $profile_id ) {
			return '<div class="itsm-tech-portal"><div class="itsm-tech-shell"><div class="itsm-tech-grid"><section class="itsm-tech-card itsm-tech-card--full"><h1>' . esc_html__( 'Technician Portal', 'itsm-employee-onboarding' ) . '</h1><p>' . esc_html__( 'No employee profile is linked to this account yet.', 'itsm-employee-onboarding' ) . '</p></section></div></div></div>';
		}
		$user       = wp_get_current_user();
		$portal_url = $this->get_technician_portal_url();
		$dashboard_url = $this->get_employee_dashboard_url();
		$application_id = (int) get_post_meta( $profile_id, 'itsm_hirezoot_application_id', true );
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'dashboard';
		if ( ! in_array( $section, [ 'dashboard', 'profile', 'documents', 'attendance', 'notifications', 'settings' ], true ) ) {
			$section = 'dashboard';
		}
		$attendance_period = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'today';
		if ( ! in_array( $attendance_period, [ 'today', 'week', 'month', 'range' ], true ) ) {
			$attendance_period = 'today';
		}
		$attendance_from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$attendance_to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$attendance_data = $this->get_attendance_dashboard_data( $user_id, $attendance_period, $attendance_from, $attendance_to );
		ob_start();
		?>
		<div class="itsm-tech-portal itsm-tech-app">
			<style>
				body.page-id-23866 #masthead,
				body.page-id-23866 #colophon,
				body.page-id-23866 header.site-header,
				body.page-id-23866 footer.site-footer,
				body.page-id-23866 .site-header,
				body.page-id-23866 .site-footer,
				body.page-id-23866 .site-footer-wrap{display:none !important;}
				body.page-id-23866 .site-content,
				body.page-id-23866 #content,
				body.page-id-23866 .content-area{padding-top:0 !important;margin-top:0 !important;}
				.itsm-tech-portal{max-width:1420px;margin:0 auto;padding:0 16px 28px;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#0f172a}
				.itsm-tech-app{min-height:100vh;background:
					radial-gradient(circle at top left, rgba(29,78,216,.12), transparent 32%),
					linear-gradient(180deg,#edf4fb 0%,#f9fbfe 36%,#ffffff 100%)}
				.itsm-tech-shell{background:rgba(255,255,255,.95);border:1px solid #d8e1ec;border-radius:28px;box-shadow:0 20px 54px rgba(15,23,42,.12);overflow:hidden}
				.itsm-tech-topbar{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:18px 22px;background:linear-gradient(135deg,#0f172a 0%,#14324d 55%,#1d4ed8 100%);color:#fff}
				.itsm-tech-topbar h1{margin:0 0 4px;font-size:24px;line-height:1.08;color:#ffffff !important;text-shadow:0 1px 2px rgba(15,23,42,.2)}
				.itsm-tech-topbar p{margin:0;max-width:68ch;color:rgba(255,255,255,.92) !important;font-size:14px;line-height:1.55}
				.itsm-tech-topbar__identity{display:flex;flex-direction:column;gap:6px}
				.itsm-tech-badge{display:inline-flex;align-items:center;border-radius:999px;background:rgba(255,255,255,.14);padding:8px 12px;font-size:13px;font-weight:800;margin-bottom:10px;backdrop-filter:blur(6px);color:#ffffff !important}
				.itsm-tech-status-chip{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:rgba(255,255,255,.14);padding:8px 12px;font-size:13px;font-weight:800;backdrop-filter:blur(6px);color:#ffffff !important}
				.itsm-tech-main{display:grid;grid-template-columns:300px minmax(0,1fr);gap:22px;padding:22px 24px 30px}
				.itsm-tech-content{display:block}
				.itsm-tech-sidebar{display:flex;flex-direction:column;gap:16px;position:sticky;top:90px;align-self:start}
				.itsm-tech-widget{background:#fff;border:1px solid #d8e1ec;border-radius:20px;padding:18px;box-shadow:0 10px 30px rgba(15,23,42,.05)}
				.itsm-tech-widget h2{margin-top:0;margin-bottom:14px;font-size:18px;line-height:1.2;color:#0f172a !important}
				.itsm-tech-widget--nav{padding:0;overflow:hidden}.itsm-tech-widget--nav h2{padding:16px 16px 0;margin-bottom:8px}
				.itsm-tech-portal-menu{display:grid;gap:8px;padding:0 16px 16px}
				.itsm-tech-portal-menu a{display:flex;align-items:center;gap:10px;justify-content:space-between;text-decoration:none;border:1px solid #e1e7ef;background:#f8fafc;color:#0f172a !important;border-radius:14px;padding:12px 14px;font-weight:800;line-height:1.2;transition:background .18s ease,border-color .18s ease,transform .18s ease,box-shadow .18s ease,color .18s ease}
				.itsm-tech-portal-menu a:hover{transform:translateX(2px);border-color:#b7c6dd;background:#eef4ff;box-shadow:0 8px 16px rgba(29,78,216,.08);color:#0f172a !important}
				.itsm-tech-portal-menu a.is-active{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff !important;border-color:#1d4ed8}
				.itsm-tech-nav-icon{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:rgba(29,78,216,.12);color:#1d4ed8 !important;font-size:12px;font-weight:800;flex:0 0 24px}
				.itsm-tech-portal-menu a.is-active .itsm-tech-nav-icon{background:rgba(255,255,255,.18);color:#fff !important}
				.itsm-tech-nav-text{flex:1 1 auto;min-width:0}
				.itsm-tech-nav-chev{opacity:.7;font-weight:900}
				.itsm-tech-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
				.itsm-tech-card{grid-column:span 6;background:#fff;border:1px solid #d8e1ec;border-radius:18px;padding:18px;box-shadow:0 8px 24px rgba(15,23,42,.04)}
				.itsm-tech-card--full{grid-column:1/-1}
				.itsm-tech-card h2,.itsm-tech-card h3{margin-top:0;color:#0f172a !important;line-height:1.25}
				.itsm-tech-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
				.itsm-tech-stat{background:#f8fafc;border:1px solid #d8e1ec;border-radius:16px;padding:14px}
				.itsm-tech-stat .label{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#64748b !important}
				.itsm-tech-stat .value{font-size:18px;font-weight:900;margin-top:6px;color:#0f172a !important}
				.itsm-tech-actions{display:flex;flex-wrap:wrap;gap:10px}
				.itsm-tech-button,.itsm-tech-action{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;border:1px solid #1d4ed8;background:#1d4ed8;color:#fff !important;padding:10px 16px;text-decoration:none;font-weight:800;min-height:44px;transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease,color .18s ease}
				.itsm-tech-button:hover,.itsm-tech-action:hover{transform:translateY(-1px);box-shadow:0 10px 18px rgba(29,78,216,.16);color:#fff !important}
				.itsm-tech-button--secondary{background:#fff;color:#1d4ed8 !important}
				.itsm-tech-button--secondary:hover{background:#eff6ff;color:#1d4ed8 !important}
				.itsm-tech-button--attendance{font-size:15px;min-width:180px}
				.itsm-tech-empty{padding:14px;border:1px dashed #cdd8e5;border-radius:12px;background:#fbfdff;color:#475569 !important;line-height:1.6}
				.itsm-tech-list{margin:0;padding-left:18px}
				.itsm-tech-attendance-panel{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
				.itsm-tech-attendance-summary{grid-column:span 4;display:grid;gap:12px}
				.itsm-tech-attendance-actions{grid-column:span 8}
				.itsm-tech-metric{background:#f8fafc;border:1px solid #d8e1ec;border-radius:16px;padding:16px}
				.itsm-tech-metric .label{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#5f6b7a}
				.itsm-tech-metric .value{font-size:20px;font-weight:800;margin-top:6px}
				.itsm-tech-table{width:100%;border-collapse:collapse;color:#0f172a !important}
				.itsm-tech-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #e5ebf2;border-radius:14px;margin-top:10px;background:#fff}
				.itsm-tech-table th,.itsm-tech-table td{padding:11px 10px;border-bottom:1px solid #e5ebf2;text-align:left;vertical-align:top;color:#0f172a !important}
				.itsm-tech-table th{font-size:13px;letter-spacing:.02em;text-transform:uppercase;color:#475569 !important;background:#f8fafc}
				.itsm-tech-table tr:last-child td{border-bottom:0}
				.itsm-tech-filterbar{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin:0 0 14px}.itsm-tech-filterbar label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:#334155 !important}.itsm-tech-filterbar input,.itsm-tech-filterbar select{min-height:42px;border-radius:10px;border:1px solid #cbd5e1;padding:8px 10px;background:#fff;color:#0f172a !important}
				.itsm-tech-filterbar label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:#334155}
				.itsm-tech-filterbar input,.itsm-tech-filterbar select{min-height:42px;border-radius:10px;border:1px solid #cbd5e1;padding:8px 10px}
				.itsm-tech-status-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;background:#eef4ff;color:#1d4ed8 !important;font-weight:800;margin-bottom:12px}
				.itsm-tech-status-badge.is-clocked-in{background:#def7e8;color:#0f766e !important}
				.itsm-tech-status-badge.is-clocked-out{background:#f1f5f9;color:#475569 !important}
				.itsm-tech-clock-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
				.itsm-tech-clock-grid .itsm-tech-widget{padding:14px}
				.itsm-tech-clock-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
				.itsm-tech-notice{margin:0 0 18px;padding:14px 16px;border-radius:14px;border:1px solid #d8e1ec;background:#f8fafc;color:#334155 !important;box-shadow:0 8px 20px rgba(15,23,42,.04);line-height:1.55}
				.itsm-tech-notice--success{background:#ecfdf3;border-color:#bbf7d0;color:#166534 !important}
				.itsm-tech-notice--error{background:#fef2f2;border-color:#fecaca;color:#991b1b !important}
				.itsm-tech-section-title{margin:0 0 12px;font-size:22px;line-height:1.2;color:#0f172a !important}
				.itsm-tech-section-subtitle{margin:0 0 18px;color:#64748b !important;line-height:1.6}
				.itsm-tech-topbar .itsm-tech-badge,.itsm-tech-topbar .itsm-tech-status-chip{color:#fff !important}
				.itsm-tech-topbar .itsm-tech-badge{background:rgba(255,255,255,.16) !important}
				.itsm-tech-topbar .itsm-tech-status-chip{background:rgba(255,255,255,.18) !important}
				.itsm-tech-sidebar h2,.itsm-tech-card h2,.itsm-tech-card h3,.itsm-tech-widget h2,.itsm-tech-widget h3,.itsm-tech-section-title{color:#0f172a !important}
				.itsm-tech-sidebar,.itsm-tech-sidebar p,.itsm-tech-sidebar li{color:#334155 !important}
				.itsm-tech-section,.itsm-tech-card,.itsm-tech-widget{color:#0f172a}
				.itsm-tech-breadcrumb{display:flex;flex-wrap:wrap;gap:8px;color:#64748b;font-size:13px;margin:0 0 14px}
				.itsm-tech-breadcrumb span{color:#94a3b8}
				@media (max-width: 1180px){.itsm-tech-main{grid-template-columns:1fr}.itsm-tech-sidebar{position:static}.itsm-tech-card,.itsm-tech-card--full,.itsm-tech-attendance-summary,.itsm-tech-attendance-actions{grid-column:1/-1}.itsm-tech-stats,.itsm-tech-clock-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.itsm-tech-topbar{flex-direction:column;align-items:flex-start}.itsm-tech-topbar__actions{justify-content:flex-start}}
				@media (max-width: 640px){.itsm-tech-portal,.itsm-tech-topbar,.itsm-tech-main{padding-left:14px;padding-right:14px}.itsm-tech-main{padding-top:14px;padding-bottom:18px}.itsm-tech-shell{border-radius:18px}.itsm-tech-widget,.itsm-tech-card{padding:14px}.itsm-tech-stats,.itsm-tech-attendance-panel,.itsm-tech-clock-grid{grid-template-columns:1fr}.itsm-tech-topbar h1{font-size:22px}.itsm-tech-topbar p{font-size:13px}.itsm-tech-sidebar{gap:12px}.itsm-tech-table th,.itsm-tech-table td{padding:10px 8px}.itsm-tech-button--attendance{min-width:0;width:100%}.itsm-tech-actions{width:100%}.itsm-tech-actions .itsm-tech-button,.itsm-tech-actions .itsm-tech-action{width:100%}}
			</style>
			<div class="itsm-tech-shell">
				<div class="itsm-tech-topbar">
					<div class="itsm-tech-topbar__identity">
						<div class="itsm-tech-badge"><?php esc_html_e( 'Approved Technician', 'itsm-employee-onboarding' ); ?></div>
						<h1><?php echo esc_html( sprintf( __( 'Welcome, %s', 'itsm-employee-onboarding' ), $user->display_name ? $user->display_name : $user->user_login ) ); ?></h1>
						<p><?php esc_html_e( 'Secure portal for onboarding, attendance, documents, and account settings.', 'itsm-employee-onboarding' ); ?></p>
					</div>
					<div class="itsm-tech-actions">
						<span class="itsm-tech-status-chip"><?php esc_html_e( 'Portal Active', 'itsm-employee-onboarding' ); ?></span>
						<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( wp_logout_url( $portal_url ) ); ?>"><?php esc_html_e( 'Logout', 'itsm-employee-onboarding' ); ?></a>
					</div>
				</div>
				<div class="itsm-tech-main">
					<div class="itsm-tech-sidebar">
						<div class="itsm-tech-widget itsm-tech-widget--nav">
							<h2 style="padding:18px 18px 0;"><?php esc_html_e( 'Navigation', 'itsm-employee-onboarding' ); ?></h2>
							<div class="itsm-tech-portal-menu" style="padding:18px;">
								<a class="<?php echo esc_attr( 'dashboard' === $section ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'section' => 'dashboard' ], $portal_url ) ); ?>"><span class="itsm-tech-nav-icon">D</span><span class="itsm-tech-nav-text"><?php esc_html_e( 'Dashboard', 'itsm-employee-onboarding' ); ?></span><span class="itsm-tech-nav-chev">&gt;</span></a>
								<a class="<?php echo esc_attr( 'profile' === $section ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'section' => 'profile' ], $portal_url ) ); ?>"><span class="itsm-tech-nav-icon">P</span><span class="itsm-tech-nav-text"><?php esc_html_e( 'Profile', 'itsm-employee-onboarding' ); ?></span><span class="itsm-tech-nav-chev">&gt;</span></a>
								<a class="<?php echo esc_attr( 'attendance' === $section ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'section' => 'attendance' ], $portal_url ) ); ?>"><span class="itsm-tech-nav-icon">A</span><span class="itsm-tech-nav-text"><?php esc_html_e( 'Attendance', 'itsm-employee-onboarding' ); ?></span><span class="itsm-tech-nav-chev">&gt;</span></a>
								<a class="<?php echo esc_attr( 'documents' === $section ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'section' => 'documents' ], $portal_url ) ); ?>"><span class="itsm-tech-nav-icon">D</span><span class="itsm-tech-nav-text"><?php esc_html_e( 'Documents', 'itsm-employee-onboarding' ); ?></span><span class="itsm-tech-nav-chev">&gt;</span></a>
								<a class="<?php echo esc_attr( 'notifications' === $section ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'section' => 'notifications' ], $portal_url ) ); ?>"><span class="itsm-tech-nav-icon">N</span><span class="itsm-tech-nav-text"><?php esc_html_e( 'Notifications', 'itsm-employee-onboarding' ); ?></span><span class="itsm-tech-nav-chev">&gt;</span></a>
								<a class="<?php echo esc_attr( 'settings' === $section ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'section' => 'settings' ], $portal_url ) ); ?>"><span class="itsm-tech-nav-icon">S</span><span class="itsm-tech-nav-text"><?php esc_html_e( 'Account Settings', 'itsm-employee-onboarding' ); ?></span><span class="itsm-tech-nav-chev">&gt;</span></a>
							</div>
						</div>
						<div class="itsm-tech-widget">
							<h2><?php esc_html_e( 'Quick Actions', 'itsm-employee-onboarding' ); ?></h2>
							<p class="itsm-tech-actions">
								<a class="itsm-tech-button" href="<?php echo esc_url( add_query_arg( [ 'section' => 'profile' ], $portal_url ) ); ?>"><?php esc_html_e( 'View Profile', 'itsm-employee-onboarding' ); ?></a>
								<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( add_query_arg( [ 'section' => 'documents' ], $portal_url ) ); ?>"><?php esc_html_e( 'View Documents', 'itsm-employee-onboarding' ); ?></a>
								<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( add_query_arg( [ 'section' => 'attendance' ], $portal_url ) ); ?>"><?php esc_html_e( 'Attendance', 'itsm-employee-onboarding' ); ?></a>
							</p>
						</div>
						<div class="itsm-tech-widget">
							<h2><?php esc_html_e( 'Notifications', 'itsm-employee-onboarding' ); ?></h2>
							<ul class="itsm-tech-list">
								<li><?php esc_html_e( 'Onboarding approved', 'itsm-employee-onboarding' ); ?></li>
								<li><?php esc_html_e( 'Documents received', 'itsm-employee-onboarding' ); ?></li>
								<li><?php esc_html_e( 'Dashboard access active', 'itsm-employee-onboarding' ); ?></li>
							</ul>
						</div>
					</div>
					<div class="itsm-tech-content">
						<?php echo $this->render_technician_portal_section( $section, $user, $user_id, $profile_id, $portal_url, $dashboard_url, $application_id, $attendance_data, $attendance_period, $attendance_from, $attendance_to ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_technician_portal_section( $section, $user, $user_id, $profile_id, $portal_url, $dashboard_url, $application_id, $attendance_data, $attendance_period, $attendance_from, $attendance_to ) {
		ob_start();
		?>
		<div class="itsm-tech-section">
			<div class="itsm-tech-breadcrumb">
				<span><?php esc_html_e( 'Technician Portal', 'itsm-employee-onboarding' ); ?></span>
				<span>›</span>
				<strong><?php echo esc_html( ucfirst( $section ) ); ?></strong>
			</div>
			<?php
			switch ( $section ) {
				case 'profile':
					$this->render_portal_profile_section( $profile_id );
					break;
				case 'attendance':
					$this->render_portal_attendance_section( $user_id, $profile_id, $attendance_data, $attendance_period, $attendance_from, $attendance_to );
					break;
				case 'documents':
					$this->render_portal_documents_section( $application_id );
					break;
				case 'notifications':
					$this->render_portal_notifications_section( $profile_id );
					break;
				case 'settings':
					$this->render_portal_settings_section( $user, $portal_url );
					break;
				case 'dashboard':
				default:
					$this->render_portal_dashboard_section( $user, $user_id, $profile_id, $portal_url, $dashboard_url, $application_id, $attendance_data );
					break;
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_portal_dashboard_section( $user, $user_id, $profile_id, $portal_url, $dashboard_url, $application_id, $attendance_data ) {
		$current_status = $attendance_data['current_status_label'] ?? ( $this->has_open_attendance_session( $user_id ) ? __( 'Clocked In', 'itsm-employee-onboarding' ) : __( 'Clocked Out', 'itsm-employee-onboarding' ) );
		$current_class  = $this->has_open_attendance_session( $user_id ) ? 'is-clocked-in' : 'is-clocked-out';
		?>
		<h2 class="itsm-tech-section-title"><?php echo esc_html( sprintf( __( 'Welcome, %s', 'itsm-employee-onboarding' ), $user->display_name ? $user->display_name : $user->user_login ) ); ?></h2>
		<p class="itsm-tech-section-subtitle"><?php esc_html_e( 'Use this secure portal to manage your onboarding, documents, attendance, and account settings.', 'itsm-employee-onboarding' ); ?></p>
		<div class="itsm-tech-grid">
			<section class="itsm-tech-card itsm-tech-card--full">
				<div class="itsm-tech-status-badge <?php echo esc_attr( $current_class ); ?>"><?php echo esc_html( $current_status ); ?></div>
				<div class="itsm-tech-clock-grid">
					<div class="itsm-tech-widget"><div class="itsm-tech-stat"><div class="label"><?php esc_html_e( 'Profile', 'itsm-employee-onboarding' ); ?></div><div class="value"><?php echo esc_html( $profile_id ? __( 'Complete', 'itsm-employee-onboarding' ) : __( 'Pending', 'itsm-employee-onboarding' ) ); ?></div></div></div>
					<div class="itsm-tech-widget"><div class="itsm-tech-stat"><div class="label"><?php esc_html_e( 'Documents', 'itsm-employee-onboarding' ); ?></div><div class="value"><?php echo esc_html( $this->has_any_documents_for_application( $application_id ) ? __( 'Received', 'itsm-employee-onboarding' ) : __( 'None yet', 'itsm-employee-onboarding' ) ); ?></div></div></div>
					<div class="itsm-tech-widget"><div class="itsm-tech-stat"><div class="label"><?php esc_html_e( 'Today', 'itsm-employee-onboarding' ); ?></div><div class="value"><?php echo esc_html( $attendance_data['today_hours_label'] ?? '0m' ); ?></div></div></div>
				</div>
				<div class="itsm-tech-clock-row" style="margin-top:14px;">
					<a class="itsm-tech-button" href="<?php echo esc_url( add_query_arg( [ 'section' => 'attendance' ], $portal_url ) ); ?>"><?php esc_html_e( 'View Attendance', 'itsm-employee-onboarding' ); ?></a>
					<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( add_query_arg( [ 'section' => 'documents' ], $portal_url ) ); ?>"><?php esc_html_e( 'View Documents', 'itsm-employee-onboarding' ); ?></a>
					<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( add_query_arg( [ 'section' => 'settings' ], $portal_url ) ); ?>"><?php esc_html_e( 'Update Settings', 'itsm-employee-onboarding' ); ?></a>
					<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( self::TAXBANDITS_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'TaxBandits', 'itsm-employee-onboarding' ); ?></a>
				</div>
			</section>
			<section class="itsm-tech-card">
				<h3><?php esc_html_e( 'Profile Summary', 'itsm-employee-onboarding' ); ?></h3>
				<p><strong><?php esc_html_e( 'Name:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_name', true ) ); ?></p>
				<p><strong><?php esc_html_e( 'Email:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_email', true ) ); ?></p>
				<p><strong><?php esc_html_e( 'Onboarding Status:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_onboarding_status', true ) ); ?></p>
			</section>
			<section class="itsm-tech-card">
				<h3><?php esc_html_e( 'Attendance Summary', 'itsm-employee-onboarding' ); ?></h3>
				<p><?php echo esc_html( $attendance_data['current_session_label'] ?? __( 'Not currently clocked in.', 'itsm-employee-onboarding' ) ); ?></p>
				<p><strong><?php esc_html_e( 'Weekly:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( $attendance_data['week_hours_label'] ?? '0m' ); ?></p>
				<p><strong><?php esc_html_e( 'Monthly:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( $attendance_data['month_hours_label'] ?? '0m' ); ?></p>
			</section>
		</div>
		<?php
	}

	private function render_portal_profile_section( $profile_id ) {
		?>
		<h2 class="itsm-tech-section-title"><?php esc_html_e( 'Profile', 'itsm-employee-onboarding' ); ?></h2>
		<div class="itsm-tech-grid">
			<section class="itsm-tech-card itsm-tech-card--full">
				<p><strong><?php esc_html_e( 'Name:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_name', true ) ); ?></p>
				<p><strong><?php esc_html_e( 'Email:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_email', true ) ); ?></p>
				<p><strong><?php esc_html_e( 'Phone:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_phone', true ) ); ?></p>
				<p><strong><?php esc_html_e( 'Address:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_address', true ) ); ?></p>
				<p><strong><?php esc_html_e( 'Employment Type:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_employment_type', true ) ? get_post_meta( $profile_id, 'itsm_employment_type', true ) : __( 'contract', 'itsm-employee-onboarding' ) ); ?></p>
			</section>
		</div>
		<?php
	}

	private function render_portal_documents_section( $application_id ) {
		?>
		<h2 class="itsm-tech-section-title"><?php esc_html_e( 'Documents', 'itsm-employee-onboarding' ); ?></h2>
		<div class="itsm-tech-grid">
			<section class="itsm-tech-card itsm-tech-card--full">
				<p class="itsm-tech-section-subtitle"><?php esc_html_e( 'Documents are private and available only to you and authorized reviewers.', 'itsm-employee-onboarding' ); ?></p>
				<?php $this->render_document_links( $application_id ); ?>
			</section>
		</div>
		<?php
	}

	private function render_portal_notifications_section( $profile_id ) {
		?>
		<h2 class="itsm-tech-section-title"><?php esc_html_e( 'Notifications', 'itsm-employee-onboarding' ); ?></h2>
		<div class="itsm-tech-grid">
			<section class="itsm-tech-card itsm-tech-card--full">
				<ul class="itsm-tech-list">
					<li><?php esc_html_e( 'Onboarding approved', 'itsm-employee-onboarding' ); ?></li>
					<li><?php esc_html_e( 'Documents received', 'itsm-employee-onboarding' ); ?></li>
					<li><?php esc_html_e( 'Dashboard access active', 'itsm-employee-onboarding' ); ?></li>
				</ul>
			</section>
		</div>
		<?php
	}

	private function render_portal_settings_section( $user, $portal_url ) {
		?>
		<h2 class="itsm-tech-section-title"><?php esc_html_e( 'Account Settings', 'itsm-employee-onboarding' ); ?></h2>
		<div class="itsm-tech-grid">
			<section class="itsm-tech-card itsm-tech-card--full">
				<p><strong><?php esc_html_e( 'Account Email:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( $user->user_email ); ?></p>
				<p class="itsm-tech-actions">
					<a class="itsm-tech-button" href="<?php echo esc_url( wp_lostpassword_url( $portal_url ) ); ?>"><?php esc_html_e( 'Change Password', 'itsm-employee-onboarding' ); ?></a>
					<a class="itsm-tech-button itsm-tech-button--secondary" href="<?php echo esc_url( wp_logout_url( $portal_url ) ); ?>"><?php esc_html_e( 'Logout', 'itsm-employee-onboarding' ); ?></a>
				</p>
			</section>
		</div>
		<?php
	}

	private function render_portal_attendance_section( $user_id, $profile_id, $attendance_data, $attendance_period, $attendance_from, $attendance_to ) {
		echo '<h2 class="itsm-tech-section-title">' . esc_html__( 'Attendance', 'itsm-employee-onboarding' ) . '</h2>';
		echo '<p class="itsm-tech-section-subtitle">' . esc_html__( 'Track clock in, clock out, and attendance history securely inside the portal.', 'itsm-employee-onboarding' ) . '</p>';
		$this->render_attendance_widget( $user_id, $profile_id, $attendance_data, $attendance_period, $attendance_from, $attendance_to );
	}

	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}
		if ( user_can( $user, 'manage_options' ) || in_array( 'administrator', (array) $user->roles, true ) ) {
			return $redirect_to;
		}
		if ( ! in_array( self::EMPLOYEE_ROLE, (array) $user->roles, true ) ) {
			return $redirect_to;
		}
		return $this->get_technician_portal_url();
	}

	private function get_technician_portal_url() {
		$page = get_page_by_path( 'technician-portal' );
		if ( $page instanceof WP_Post ) {
			$url = get_permalink( $page );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/technician-portal/' );
	}

	private function get_technician_portal_redirect_url( $fallback = '' ) {
		$portal_url = $this->get_technician_portal_url();
		$fallback   = is_string( $fallback ) ? $fallback : '';
		if ( $fallback && false !== strpos( $fallback, 'technician-portal' ) ) {
			return $fallback;
		}
		return $portal_url;
	}

	public function register_role() {
		if ( ! get_role( self::EMPLOYEE_ROLE ) ) {
			add_role(
				self::EMPLOYEE_ROLE,
				__( 'ITSM Employee', 'itsm-employee-onboarding' ),
				[
					'read' => true,
				]
			);
		}
	}

	public function register_employee_profile_cpt() {
		register_post_type(
			self::PROFILE_CPT,
			[
				'labels'       => [
					'name'          => __( 'Employee Profiles', 'itsm-employee-onboarding' ),
					'singular_name' => __( 'Employee Profile', 'itsm-employee-onboarding' ),
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'supports'     => [ 'title' ],
				'capability_type' => 'post',
			]
		);
	}

	public function register_admin_pages() {
		add_options_page(
			__( 'ITSM Employee Onboarding', 'itsm-employee-onboarding' ),
			__( 'ITSM Employee Onboarding', 'itsm-employee-onboarding' ),
			'manage_options',
			'itsm-employee-onboarding',
			[ $this, 'render_settings_page' ]
		);
		add_submenu_page(
			'edit.php?post_type=' . self::PROFILE_CPT,
			__( 'Onboarding Review Queue', 'itsm-employee-onboarding' ),
			__( 'Onboarding Review Queue', 'itsm-employee-onboarding' ),
			'edit_posts',
			'itsm-onboarding-review-queue',
			[ $this, 'render_review_queue' ]
		);
		add_submenu_page(
			'edit.php?post_type=' . self::PROFILE_CPT,
			__( 'Attendance', 'itsm-employee-onboarding' ),
			__( 'Attendance', 'itsm-employee-onboarding' ),
			'edit_posts',
			'itsm-attendance',
			[ $this, 'render_attendance_admin' ]
		);
	}

	public function register_settings() {
		register_setting(
			'itsm_employee_onboarding',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->defaults(),
			]
		);
	}

	public function register_application_meta_boxes() {
		add_meta_box(
			'itsm-status-email-controls',
			__( 'ITSM Status Email Controls', 'itsm-employee-onboarding' ),
			[ $this, 'render_status_email_meta_box' ],
			'awsm_job_application',
			'side',
			'default'
		);
	}

	public function render_status_email_meta_box( $post ) {
		$s   = $this->get_settings();
		$log = get_post_meta( $post->ID, self::STATUS_LOG_META, true );
		$log = is_array( $log ) ? array_reverse( $log ) : [];
		foreach ( $s['status_workflows'] as $status => $workflow ) {
			$sent_at = (string) get_post_meta( $post->ID, $this->status_sent_meta_key( $status ), true );
			echo '<p><strong>' . esc_html( $workflow['label'] ) . '</strong><br />';
			echo $sent_at ? esc_html( 'Sent: ' . $sent_at ) : esc_html__( 'Not sent', 'itsm-employee-onboarding' );
			if ( $sent_at ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=itsm_reset_status_email&application_id=' . $post->ID . '&status=' . rawurlencode( $status ) ), 'itsm_reset_status_' . $post->ID . '_' . $status );
				echo '<br /><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Reset Guard', 'itsm-employee-onboarding' ) . '</a>';
			}
			echo '</p>';
		}
		if ( $log ) {
			echo '<hr /><p><strong>' . esc_html__( 'Recent Log', 'itsm-employee-onboarding' ) . '</strong></p>';
			echo '<ul style="margin-left:16px;">';
			foreach ( array_slice( $log, 0, 5 ) as $entry ) {
				echo '<li>' . esc_html( $entry['time'] . ' - ' . $entry['status'] . ' - ' . $entry['event'] ) . '</li>';
			}
			echo '</ul>';
		}
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->defaults();
		$output   = $defaults;
		$output['enabled']               = ! empty( $input['enabled'] ) ? 'yes' : 'no';
		$output['test_mode']             = ! empty( $input['test_mode'] ) ? 'yes' : 'no';
		$output['test_recipient']        = isset( $input['test_recipient'] ) ? sanitize_email( $input['test_recipient'] ) : $defaults['test_recipient'];
		$output['from_email']            = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '';
		$output['admin_email']           = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'];
		$output['token_expiration_days'] = isset( $input['token_expiration_days'] ) ? max( 1, absint( $input['token_expiration_days'] ) ) : 14;
		$output['max_file_size_mb']      = isset( $input['max_file_size_mb'] ) ? max( 1, absint( $input['max_file_size_mb'] ) ) : 10;
		$output['taxbandits_url']        = isset( $input['taxbandits_url'] ) ? esc_url_raw( trim( (string) $input['taxbandits_url'] ) ) : self::TAXBANDITS_URL;
		$output['irs_w9_url']            = isset( $input['irs_w9_url'] ) ? esc_url_raw( trim( (string) $input['irs_w9_url'] ) ) : self::IRS_W9_URL;

		foreach ( $defaults['status_workflows'] as $status => $workflow ) {
			$posted = isset( $input['status_workflows'][ $status ] ) && is_array( $input['status_workflows'][ $status ] ) ? $input['status_workflows'][ $status ] : [];
			$output['status_workflows'][ $status ]['enabled'] = ! empty( $posted['enabled'] ) ? 'yes' : 'no';
			$output['status_workflows'][ $status ]['subject'] = isset( $posted['subject'] ) ? sanitize_text_field( (string) $posted['subject'] ) : $workflow['subject'];
			$output['status_workflows'][ $status ]['body']    = isset( $posted['body'] ) ? wp_kses_post( (string) $posted['body'] ) : $workflow['body'];
		}

		return $output;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ITSM Employee Onboarding', 'itsm-employee-onboarding' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'itsm_employee_onboarding' ); ?>
				<h2><?php esc_html_e( 'General', 'itsm-employee-onboarding' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Enable Automation', 'itsm-employee-onboarding' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 'yes' ); ?> /> <?php esc_html_e( 'Enabled', 'itsm-employee-onboarding' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Test Mode', 'itsm-employee-onboarding' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[test_mode]" value="1" <?php checked( $s['test_mode'], 'yes' ); ?> /> <?php esc_html_e( 'Route candidate emails to test recipient and prefix subjects with [DEV TEST]', 'itsm-employee-onboarding' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Test Recipient', 'itsm-employee-onboarding' ); ?></th><td><input class="regular-text" type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[test_recipient]" value="<?php echo esc_attr( $s['test_recipient'] ); ?>" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Admin/HR Notification Email', 'itsm-employee-onboarding' ); ?></th><td><input class="regular-text" type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email]" value="<?php echo esc_attr( $s['admin_email'] ); ?>" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'From Email', 'itsm-employee-onboarding' ); ?></th><td><input class="regular-text" type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Token Expiration Days', 'itsm-employee-onboarding' ); ?></th><td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[token_expiration_days]" value="<?php echo esc_attr( $s['token_expiration_days'] ); ?>" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Max File Size MB', 'itsm-employee-onboarding' ); ?></th><td><input type="number" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_file_size_mb]" value="<?php echo esc_attr( $s['max_file_size_mb'] ); ?>" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'TaxBandits URL', 'itsm-employee-onboarding' ); ?></th><td><input class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[taxbandits_url]" value="<?php echo esc_attr( $s['taxbandits_url'] ); ?>" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'IRS W-9 PDF URL', 'itsm-employee-onboarding' ); ?></th><td><input class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[irs_w9_url]" value="<?php echo esc_attr( $s['irs_w9_url'] ); ?>" /></td></tr>
				</table>
				<h2><?php esc_html_e( 'Status Emails', 'itsm-employee-onboarding' ); ?></h2>
				<?php foreach ( $s['status_workflows'] as $status => $workflow ) : ?>
					<h3><?php echo esc_html( $workflow['label'] . ' (' . $status . ')' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Enabled', 'itsm-employee-onboarding' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[status_workflows][<?php echo esc_attr( $status ); ?>][enabled]" value="1" <?php checked( $workflow['enabled'], 'yes' ); ?> /> <?php esc_html_e( 'Send this email once per application/status', 'itsm-employee-onboarding' ); ?></label></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Subject', 'itsm-employee-onboarding' ); ?></th><td><input class="large-text" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[status_workflows][<?php echo esc_attr( $status ); ?>][subject]" value="<?php echo esc_attr( $workflow['subject'] ); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Body', 'itsm-employee-onboarding' ); ?></th><td><textarea class="large-text" rows="6" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[status_workflows][<?php echo esc_attr( $status ); ?>][body]"><?php echo esc_textarea( $workflow['body'] ); ?></textarea><p class="description"><?php esc_html_e( 'Placeholders: {candidate_name}, {candidate_email}, {job_title}, {onboarding_url}', 'itsm-employee-onboarding' ); ?></p></td></tr>
					</table>
				<?php endforeach; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function create_attendance_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $wpdb->prefix . 'itsm_attendance';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			employee_profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			clock_in_time datetime DEFAULT NULL,
			clock_out_time datetime DEFAULT NULL,
			timezone varchar(100) NOT NULL DEFAULT '',
			ip_address varchar(100) NOT NULL DEFAULT '',
			device_info text NULL,
			status varchar(40) NOT NULL DEFAULT 'clocked_in',
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY employee_profile_id (employee_profile_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	public function handle_status_transition( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || 'awsm_job_application' !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		$s = $this->get_settings();
		if ( ! $this->bool_value( $s['enabled'] ) || empty( $s['status_workflows'][ $new_status ] ) || ! $this->bool_value( $s['status_workflows'][ $new_status ]['enabled'] ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, $this->status_sent_meta_key( $new_status ), true ) ) {
			$this->append_status_log( $post->ID, $new_status, 'duplicate_guard_skipped' );
			return;
		}
		$this->send_status_email( $post->ID, $new_status );
	}

	public function handle_reset_status_email() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'itsm-employee-onboarding' ) );
		}
		$application_id = isset( $_GET['application_id'] ) ? absint( $_GET['application_id'] ) : 0;
		$status         = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! $application_id || ! $status || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'itsm_reset_status_' . $application_id . '_' . $status ) ) {
			wp_die( esc_html__( 'Invalid reset request.', 'itsm-employee-onboarding' ) );
		}
		delete_post_meta( $application_id, $this->status_sent_meta_key( $status ) );
		$this->append_status_log( $application_id, $status, 'manual_guard_reset', [ 'user_id' => get_current_user_id() ] );
		wp_safe_redirect( get_edit_post_link( $application_id, 'raw' ) );
		exit;
	}

	private function status_sent_meta_key( $status ) {
		return 'itsm_status_email_' . sanitize_key( $status ) . '_sent_at';
	}

	private function send_status_email( $application_id, $status ) {
		$s        = $this->get_settings();
		$workflow = $s['status_workflows'][ $status ];
		$email    = (string) get_post_meta( $application_id, 'awsm_applicant_email', true );
		$name     = (string) get_post_meta( $application_id, 'awsm_applicant_name', true );
		if ( ! is_email( $email ) ) {
			$this->append_status_log( $application_id, $status, 'missing_candidate_email' );
			return;
		}
		$job_id = (int) get_post_field( 'post_parent', $application_id );
		$token  = '';
		if ( 'select' === $status ) {
			$token = $this->create_onboarding_token( $application_id );
		}
		$onboarding_url = $token ? add_query_arg( 'itsm_onboarding_token', rawurlencode( $token ), home_url( '/' ) ) : '';
		$recipient      = $email;
		$subject        = $workflow['subject'];
		$body           = $workflow['body'];
		if ( $this->bool_value( $s['test_mode'] ) ) {
			$recipient = is_email( $s['test_recipient'] ) ? $s['test_recipient'] : get_option( 'admin_email' );
			$subject   = '[DEV TEST] ' . $subject;
			$body      = "DEV TEST: Original recipient would have been {$email}\n\n" . $body;
		}
		$message = strtr(
			$body,
			[
				'{candidate_name}'  => $name,
				'{candidate_email}' => $email,
				'{job_title}'       => $job_id ? get_the_title( $job_id ) : '',
				'{onboarding_url}'  => $onboarding_url,
			]
		);
		$headers = [];
		if ( is_email( $s['from_email'] ) ) {
			$headers[] = 'From: ITSM Solutions <' . $s['from_email'] . '>';
		}
		wp_mail( $recipient, $subject, $message, $headers );
		update_post_meta( $application_id, $this->status_sent_meta_key( $status ), current_time( 'mysql', true ) );
		update_post_meta( $application_id, self::STATE_META, 'select' === $status ? 'candidate_email_sent' : 'status_email_' . sanitize_key( $status ) . '_sent' );
		$this->append_status_log( $application_id, $status, 'sent', [ 'recipient' => $recipient, 'test_mode' => $s['test_mode'] ] );
	}

	public function create_onboarding_token( $application_id ) {
		$s       = $this->get_settings();
		$token   = wp_generate_password( 32, false, false );
		$created = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( DAY_IN_SECONDS * absint( $s['token_expiration_days'] ) ) );
		update_post_meta( $application_id, self::TOKEN_HASH_META, wp_hash_password( $token ) );
		update_post_meta( $application_id, self::TOKEN_CREATED_META, $created );
		update_post_meta( $application_id, self::TOKEN_EXPIRES_META, $expires );
		return $token;
	}

	private function append_status_log( $application_id, $status, $event, $extra = [] ) {
		$log   = get_post_meta( $application_id, self::STATUS_LOG_META, true );
		$log   = is_array( $log ) ? $log : [];
		$log[] = [
			'status' => $status,
			'event'  => $event,
			'time'   => current_time( 'mysql', true ),
			'extra'  => $extra,
		];
		update_post_meta( $application_id, self::STATUS_LOG_META, $log );
	}

	private function find_application_by_token( $token ) {
		$q = new WP_Query(
			[
				'post_type'      => 'awsm_job_application',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => self::TOKEN_HASH_META,
				'meta_compare'   => 'EXISTS',
			]
		);
		foreach ( $q->posts as $application_id ) {
			$hash = (string) get_post_meta( $application_id, self::TOKEN_HASH_META, true );
			if ( $hash && wp_check_password( $token, $hash ) ) {
				return get_post( $application_id );
			}
		}
		return null;
	}

	private function validate_token_application( $token ) {
		if ( '' === $token ) {
			return [ null, __( 'Missing onboarding token.', 'itsm-employee-onboarding' ) ];
		}
		$application = $this->find_application_by_token( $token );
		if ( ! $application ) {
			return [ null, __( 'Invalid onboarding link.', 'itsm-employee-onboarding' ) ];
		}
		$expires = (string) get_post_meta( $application->ID, self::TOKEN_EXPIRES_META, true );
		if ( $expires && strtotime( $expires . ' UTC' ) < time() ) {
			return [ null, __( 'This onboarding link has expired. Please request a new link.', 'itsm-employee-onboarding' ) ];
		}
		if ( get_post_meta( $application->ID, self::TOKEN_USED_META, true ) ) {
			return [ null, __( 'This onboarding link has already been used. Please contact HR if you need to update your onboarding package.', 'itsm-employee-onboarding' ) ];
		}
		return [ $application, '' ];
	}

	public function maybe_render_token_form() {
		if ( isset( $_GET['itsm_onboarding'] ) && 'submitted' === sanitize_key( wp_unslash( $_GET['itsm_onboarding'] ) ) ) {
			$this->render_candidate_success_page(
				__( 'Onboarding package submitted', 'itsm-employee-onboarding' ),
				__( 'Thank you. Your onboarding package has been submitted for HR review.', 'itsm-employee-onboarding' )
			);
		}
		$token = isset( $_GET['itsm_onboarding_token'] ) ? sanitize_text_field( wp_unslash( $_GET['itsm_onboarding_token'] ) ) : '';
		if ( '' === $token ) {
			return;
		}
		list( $application, $error ) = $this->validate_token_application( $token );
		if ( ! $application ) {
			$this->render_candidate_error_page(
				__( 'Onboarding link unavailable', 'itsm-employee-onboarding' ),
				__( 'This onboarding link is invalid, expired, or has already been used. Please contact HR to request a new onboarding link.', 'itsm-employee-onboarding' )
			);
		}
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ( isset( $_POST['itsm_employee_onboarding_step_submit'] ) || isset( $_POST['itsm_employee_onboarding_submit'] ) ) ) {
			$this->handle_onboarding_submit( $application, $token );
		}
		$current_step = $this->get_onboarding_current_step( $application->ID );
		$this->render_onboarding_form_page( $application, $token, $this->get_onboarding_messages_from_request(), null, $current_step );
	}

	public function render_shortcode_form() {
		$token = isset( $_GET['itsm_onboarding_token'] ) ? sanitize_text_field( wp_unslash( $_GET['itsm_onboarding_token'] ) ) : '';
		list( $application, $error ) = $this->validate_token_application( $token );
		if ( ! $application ) {
			return '<p>' . esc_html( $error ) . '</p>';
		}
		ob_start();
		$this->render_onboarding_form( $application, $token, $this->get_onboarding_messages_from_request() );
		return ob_get_clean();
	}

	private function onboarding_fields() {
		return [
			'full_name'                   => 'Full Name',
			'email'                       => 'Email',
			'phone'                       => 'Phone',
			'address'                     => 'Address',
			'job_location_applied_for'    => 'Job/Location Applied For',
			'employment_type'             => 'Employment Type',
		];
	}

	private function get_onboarding_step_meta( $application_id ) {
		$step = absint( get_post_meta( $application_id, 'itsm_onboarding_current_step', true ) );
		return $step > 0 ? $step : 1;
	}

	private function get_onboarding_current_step( $application_id ) {
		if ( get_post_meta( $application_id, self::STATE_META, true ) && 'pending_review' === get_post_meta( $application_id, self::STATE_META, true ) ) {
			return 4;
		}
		$current_step = $this->get_onboarding_step_meta( $application_id );
		if ( $current_step > 0 ) {
			return $current_step;
		}
		if ( 'yes' === get_post_meta( $application_id, 'itsm_onboarding_step_profile_completed', true ) ) {
			return 2;
		}
		return 1;
	}

	private function set_onboarding_step_meta( $application_id, $step ) {
		update_post_meta( $application_id, 'itsm_onboarding_current_step', absint( $step ) );
	}

	private function render_step_progress( $current_step ) {
		$steps = [
			1 => __( 'Profile', 'itsm-employee-onboarding' ),
			2 => __( 'Tax', 'itsm-employee-onboarding' ),
			3 => __( 'Driving License', 'itsm-employee-onboarding' ),
			4 => __( 'Review', 'itsm-employee-onboarding' ),
		];
		echo '<ol class="itsm-onboarding-progress" aria-label="' . esc_attr__( 'Onboarding progress', 'itsm-employee-onboarding' ) . '">';
		foreach ( $steps as $num => $label ) {
			$class = 'itsm-onboarding-progress__item';
			if ( $num < (int) $current_step ) {
				$class .= ' is-complete';
			} elseif ( $num === (int) $current_step ) {
				$class .= ' is-active';
			}
			echo '<li class="' . esc_attr( $class ) . '"><span class="itsm-onboarding-progress__eyebrow">' . esc_html( sprintf( __( 'Step %d of 4', 'itsm-employee-onboarding' ), $num ) ) . '</span><span class="itsm-onboarding-progress__label">' . esc_html( $label ) . '</span></li>';
		}
		echo '</ol>';
	}

	private function render_candidate_error_page( $title, $message ) {
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title><?php echo esc_html( $title ); ?></title><?php wp_head(); ?></head><body><main class="itsm-onboarding-message" style="max-width:760px;margin:48px auto;padding:28px;font-family:Arial,sans-serif;border:1px solid #d8dee8;border-radius:12px;background:#fff;"><h1><?php echo esc_html( $title ); ?></h1><p style="font-size:16px;line-height:1.6;"><?php echo esc_html( $message ); ?></p></main><?php wp_footer(); ?></body></html><?php
		exit;
	}

	private function render_candidate_success_page( $title, $message ) {
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title><?php echo esc_html( $title ); ?></title><?php wp_head(); ?></head><body><main class="itsm-onboarding-message" style="max-width:760px;margin:48px auto;padding:28px;font-family:Arial,sans-serif;border:1px solid #cfe7d8;border-radius:12px;background:#f6fff8;"><h1><?php echo esc_html( $title ); ?></h1><p style="font-size:16px;line-height:1.6;"><?php echo esc_html( $message ); ?></p></main><?php wp_footer(); ?></body></html><?php
		exit;
	}

	private function render_onboarding_form_page( $application, $token, $messages = [], $data_override = null, $current_step = null ) {
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title><?php esc_html_e( 'ITSM Onboarding', 'itsm-employee-onboarding' ); ?></title><?php wp_head(); ?></head><body><?php $this->render_onboarding_form( $application, $token, $messages, $data_override ); ?><?php wp_footer(); ?></body></html><?php
		exit;
	}

	private function onboarding_step_action_url( $action ) {
		return admin_url( 'admin-post.php?action=' . rawurlencode( $action ) );
	}

	private function onboarding_url_with_message( $token, $message = '', $step = null ) {
		$args = [ 'itsm_onboarding_token' => $token ];
		if ( '' !== $message ) {
			$args['itsm_onboarding_message'] = $message;
		}
		if ( null !== $step ) {
			$args['itsm_onboarding_step'] = absint( $step );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	private function get_onboarding_messages_from_request() {
		$messages = [];
		if ( isset( $_GET['itsm_onboarding_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['itsm_onboarding_message'] ) );
			if ( '' !== $message ) {
				$messages[] = $message;
			}
		}
		return $messages;
	}

	private function redirect_back_to_onboarding( $token, $message = '', $step = null ) {
		wp_safe_redirect( $this->onboarding_url_with_message( $token, $message, $step ) );
		exit;
	}

	private function is_onboarding_ajax_request( $is_ajax = false ) {
		if ( ! $is_ajax ) {
			return false;
		}
		$requested_with = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) ) : '';
		if ( 'xmlhttprequest' === $requested_with ) {
			return true;
		}
		return false;
	}

	private function render_step_form_open( $action, $token, $extra_args = [] ) {
		$action_url = ! empty( $extra_args['ajax'] ) ? admin_url( 'admin-ajax.php?action=' . $action ) : $this->onboarding_step_action_url( $action );
		$nonce_action = $action . '_' . $token;
		$enctype = ! empty( $extra_args['multipart'] ) ? ' enctype="multipart/form-data"' : '';
		$form_classes = [ 'itsm-onboarding-form' ];
		if ( ! empty( $extra_args['ajax'] ) ) {
			$form_classes[] = 'itsm-onboarding-ajax-upload-form';
		}
		if ( ! empty( $extra_args['class'] ) ) {
			$form_classes[] = sanitize_html_class( $extra_args['class'] );
		}
		$form_attrs = [
			'class="' . esc_attr( implode( ' ', array_filter( $form_classes ) ) ) . '"',
		];
		if ( ! empty( $extra_args['ajax'] ) ) {
			$form_attrs[] = 'data-itsm-ajax-upload="1"';
		}
		if ( ! empty( $extra_args['step'] ) ) {
			$form_attrs[] = 'data-itsm-onboarding-step="' . esc_attr( absint( $extra_args['step'] ) ) . '"';
		}
		if ( ! empty( $extra_args['document_field'] ) ) {
			$form_attrs[] = 'data-itsm-document-field="' . esc_attr( $extra_args['document_field'] ) . '"';
		}
		if ( ! empty( $extra_args['data_attrs'] ) && is_array( $extra_args['data_attrs'] ) ) {
			foreach ( $extra_args['data_attrs'] as $data_key => $data_value ) {
				$form_attrs[] = 'data-' . sanitize_key( $data_key ) . '="' . esc_attr( $data_value ) . '"';
			}
		}
		echo '<form method="post" action="' . esc_url( $action_url ) . '"' . $enctype . ' ' . implode( ' ', $form_attrs ) . '>';
		wp_nonce_field( $nonce_action, 'itsm_employee_onboarding_nonce' );
		echo '<input type="hidden" name="itsm_onboarding_token" value="' . esc_attr( $token ) . '" />';
		if ( ! empty( $extra_args['step'] ) ) {
			echo '<input type="hidden" name="itsm_onboarding_step" value="' . esc_attr( absint( $extra_args['step'] ) ) . '" />';
		}
		if ( ! empty( $extra_args['document_field'] ) ) {
			echo '<input type="hidden" name="document_field" value="' . esc_attr( $extra_args['document_field'] ) . '" />';
		}
	}

	private function render_step_form_close() {
		echo '</form>';
	}

	private function render_onboarding_form( $application, $token, $messages = [], $data_override = null ) {
		$s        = $this->get_settings();
		$existing = (array) get_post_meta( $application->ID, self::RESPONSE_META, true );
		$name     = get_post_meta( $application->ID, 'awsm_applicant_name', true );
		$email    = get_post_meta( $application->ID, 'awsm_applicant_email', true );
		$phone    = get_post_meta( $application->ID, 'awsm_applicant_phone', true );
		$job_id   = (int) get_post_field( 'post_parent', $application->ID );
		$job      = $job_id ? get_the_title( $job_id ) : '';
		$defaults = [
			'full_name'                => $name,
			'email'                    => $email,
			'phone'                    => $phone,
			'job_location_applied_for' => $job,
			'candidate_type'           => get_post_meta( $application->ID, 'itsm_job_type', true ),
		];
		$data = wp_parse_args( $existing, $defaults );
		if ( is_array( $data_override ) ) {
			$data = wp_parse_args( $data_override, $data );
		}
		$current_step = $this->get_onboarding_current_step( $application->ID );
		?>
		<style>
			.itsm-onboarding-shell{--itsm-primary:#0b70e1;--itsm-primary-dark:#095cb8;--itsm-surface:#ffffff;--itsm-surface-soft:#f4f8fd;--itsm-border:#d7e1ee;--itsm-text:#1f2937;--itsm-muted:#5b6572;--itsm-success:#0f766e;--itsm-danger:#b42318;max-width:980px;margin:clamp(18px,4vw,42px) auto;padding:0 16px;font-family:inherit;color:var(--itsm-text);}
			.itsm-onboarding-card{background:linear-gradient(180deg,#fff 0%,#fbfdff 100%);border:1px solid var(--itsm-border);border-radius:24px;box-shadow:0 18px 45px rgba(15,23,42,.08);overflow:hidden}
			.itsm-onboarding-card__header{padding:28px 28px 20px;background:linear-gradient(135deg,rgba(11,112,225,.12),rgba(11,112,225,.03));border-bottom:1px solid var(--itsm-border)}
			.itsm-onboarding-kicker{display:inline-flex;align-items:center;gap:.5rem;font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:var(--itsm-primary);margin:0 0 10px}
			.itsm-onboarding-title{margin:0 0 10px;font-size:clamp(1.6rem,3.6vw,2.3rem);line-height:1.2}
			.itsm-onboarding-subtitle{margin:0;color:var(--itsm-muted);font-size:1rem;line-height:1.6;max-width:62ch}
			.itsm-onboarding-card__body{padding:26px 28px 30px}
			.itsm-onboarding-progress{display:grid;gap:12px;list-style:none;padding:0;margin:0 0 28px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
			.itsm-onboarding-progress__item{position:relative;border:1px solid var(--itsm-border);border-radius:18px;padding:14px 14px 14px 16px;background:var(--itsm-surface-soft);min-height:78px;display:flex;flex-direction:column;justify-content:center;gap:4px;box-shadow:inset 0 1px 0 rgba(255,255,255,.8)}
			.itsm-onboarding-progress__item.is-complete{background:rgba(15,118,110,.08);border-color:rgba(15,118,110,.22)}
			.itsm-onboarding-progress__item.is-active{background:linear-gradient(180deg,rgba(11,112,225,.16),rgba(11,112,225,.06));border-color:rgba(11,112,225,.28);box-shadow:0 12px 30px rgba(11,112,225,.12)}
			.itsm-onboarding-progress__eyebrow{font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--itsm-primary-dark)}
			.itsm-onboarding-progress__label{font-size:.96rem;font-weight:700;color:var(--itsm-text)}
			.itsm-onboarding-panel{background:#fff;border:1px solid var(--itsm-border);border-radius:20px;padding:22px;margin:0 0 18px;box-shadow:0 10px 24px rgba(15,23,42,.05)}
			.itsm-onboarding-panel h2,.itsm-onboarding-panel h3{margin-top:0}
			.itsm-onboarding-panel h2{font-size:1.35rem}
			.itsm-onboarding-panel p{color:var(--itsm-muted);line-height:1.65}
			.itsm-onboarding-review{background:var(--itsm-surface-soft);border:1px solid var(--itsm-border);border-radius:16px;padding:18px}
			.itsm-onboarding-error,.itsm-onboarding-success{border-radius:14px;padding:14px 16px;margin:0 0 18px;border:1px solid transparent}
			.itsm-onboarding-error{background:#fff5f5;border-color:rgba(180,35,24,.18);color:#5f1717}
			.itsm-onboarding-success{background:#f0fdf9;border-color:rgba(15,118,110,.18);color:#0f4f46}
			.itsm-onboarding-field,.itsm-onboarding-file,.itsm-onboarding-textarea{margin:0 0 18px}
			.itsm-onboarding-field label,.itsm-onboarding-file label{display:block;font-weight:600;color:var(--itsm-text);margin-bottom:8px}
			.itsm-onboarding-field input[type="text"],.itsm-onboarding-field input[type="email"],.itsm-onboarding-field input[type="tel"],.itsm-onboarding-field textarea,.itsm-onboarding-file input[type="file"]{width:100%;box-sizing:border-box;border:1px solid var(--itsm-border);border-radius:14px;background:#fff;padding:14px 16px;font-size:1rem;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}
			.itsm-onboarding-field textarea{min-height:112px;resize:vertical}
			.itsm-onboarding-field input:focus,.itsm-onboarding-field textarea:focus,.itsm-onboarding-file input:focus{outline:none;border-color:var(--itsm-primary);box-shadow:0 0 0 4px rgba(11,112,225,.14)}
			.itsm-onboarding-help,.itsm-onboarding-field small,.itsm-onboarding-file small{display:block;margin-top:8px;color:var(--itsm-muted);font-size:.92rem;line-height:1.45}
			.itsm-onboarding-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:10px}
			.itsm-onboarding-actions .button,.itsm-onboarding-actions .button-primary,.itsm-onboarding-actions a.button{border-radius:999px;padding:12px 18px;line-height:1.2;box-shadow:none;text-decoration:none}
			.itsm-onboarding-actions .button-primary{background:var(--itsm-primary);border-color:var(--itsm-primary);color:#fff}
			.itsm-onboarding-actions .button-primary:hover,.itsm-onboarding-actions .button-primary:focus{background:var(--itsm-primary-dark);border-color:var(--itsm-primary-dark);color:#fff}
			.itsm-onboarding-actions .button:not(.button-primary){background:#fff;border-color:var(--itsm-border);color:var(--itsm-text)}
			.itsm-onboarding-actions .button:not(.button-primary):hover{border-color:var(--itsm-primary);color:var(--itsm-primary-dark)}
			.itsm-onboarding-upload-box{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px dashed rgba(11,112,225,.32);border-radius:18px;padding:18px;margin:0 0 18px}
			.itsm-onboarding-upload-box.is-required{border-color:rgba(180,35,24,.36);background:linear-gradient(180deg,#fff 0%,#fff8f7 100%)}
			.itsm-onboarding-upload-box strong{display:block;margin-bottom:6px}
			@media (max-width: 640px){
				.itsm-onboarding-shell{padding:0 10px}
				.itsm-onboarding-card__header,.itsm-onboarding-card__body,.itsm-onboarding-panel{padding:18px}
				.itsm-onboarding-actions{flex-direction:column;align-items:stretch}
				.itsm-onboarding-actions .button,.itsm-onboarding-actions .button-primary,.itsm-onboarding-actions a.button{width:100%;text-align:center}
				.itsm-onboarding-progress{grid-template-columns:1fr}
			}
		</style>
		<div class="itsm-onboarding-shell">
			<div class="itsm-onboarding-card">
				<div class="itsm-onboarding-card__header">
					<p class="itsm-onboarding-kicker"><?php esc_html_e( 'Secure onboarding', 'itsm-employee-onboarding' ); ?></p>
					<h1 class="itsm-onboarding-title"><?php esc_html_e( 'ITSM Technician Onboarding', 'itsm-employee-onboarding' ); ?></h1>
					<p class="itsm-onboarding-subtitle"><?php esc_html_e( 'Complete your onboarding package step by step. Dashboard access is granted only after HR/admin approval.', 'itsm-employee-onboarding' ); ?></p>
				</div>
				<div class="itsm-onboarding-card__body">
			<?php $this->render_step_progress( $current_step ); ?>
			<?php foreach ( (array) $messages as $message ) : ?>
				<div class="itsm-onboarding-error">
					<strong><?php esc_html_e( 'Action needed:', 'itsm-employee-onboarding' ); ?></strong>
					<?php echo esc_html( $message ); ?>
				</div>
			<?php endforeach; ?>
				<?php if ( 1 === (int) $current_step ) : ?>
					<div class="itsm-onboarding-panel">
					<?php $this->render_step_form_open( 'itsm_onboarding_step', $token, [ 'step' => 1 ] ); ?>
					<h2><?php esc_html_e( 'Step 1 of 4: Profile / Contact Details', 'itsm-employee-onboarding' ); ?></h2>
					<p class="itsm-onboarding-help"><?php esc_html_e( 'Confirm your contact details. Employment type is contract only.', 'itsm-employee-onboarding' ); ?></p>
					<?php $this->text_input( 'full_name', 'Full Name', $data, true ); ?>
					<?php $this->text_input( 'email', 'Email', $data, true, 'email' ); ?>
					<?php $this->text_input( 'phone', 'Phone', $data, true, 'tel' ); ?>
					<?php $this->textarea_input( 'address', 'Address', $data, true ); ?>
					<?php $this->text_input( 'job_location_applied_for', 'Job/Location Applied For', $data, true ); ?>
					<p><strong><?php esc_html_e( 'Employment Type', 'itsm-employee-onboarding' ); ?></strong><br /><?php esc_html_e( 'Contract', 'itsm-employee-onboarding' ); ?></p>
					<input type="hidden" name="employment_type" value="contract" />
					<div class="itsm-onboarding-actions"><button type="submit" class="button button-primary" name="itsm_employee_onboarding_step_submit" value="profile"><?php esc_html_e( 'Save and Continue to Tax', 'itsm-employee-onboarding' ); ?></button></div>
					<?php $this->render_step_form_close(); ?>
					</div>
				<?php elseif ( 2 === (int) $current_step ) : ?>
					<div class="itsm-onboarding-panel">
					<?php $this->render_step_form_open( 'itsm_onboarding_step', $token, [ 'step' => 2 ] ); ?>
					<h2><?php esc_html_e( 'Step 2 of 4: Tax Confirmation', 'itsm-employee-onboarding' ); ?></h2>
					<p><?php esc_html_e( 'Please complete your required W-9/W-8 tax form through the secure ITSM TaxBandits portal.', 'itsm-employee-onboarding' ); ?></p>
					<div class="itsm-onboarding-actions">
						<a class="button button-primary" href="<?php echo esc_url( $s['taxbandits_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Complete W-9/W-8 Form', 'itsm-employee-onboarding' ); ?></a>
					</div>
					<p><label><input type="checkbox" name="tax_form_confirmed" value="1" required <?php checked( isset( $data['tax_form_confirmed'] ) && 'yes' === $data['tax_form_confirmed'] ); ?> /> <?php esc_html_e( 'I confirm that I have completed my W-9/W-8 tax form through the secure ITSM TaxBandits portal.', 'itsm-employee-onboarding' ); ?></label></p>
					<div class="itsm-onboarding-actions"><button type="submit" class="button button-primary" name="itsm_employee_onboarding_step_submit" value="tax"><?php esc_html_e( 'Save and Continue to Driving License', 'itsm-employee-onboarding' ); ?></button></div>
					<?php $this->render_step_form_close(); ?>
					</div>
				<?php elseif ( 3 === (int) $current_step ) : ?>
					<div class="itsm-onboarding-panel">
					<?php $this->render_step_form_open( 'itsm_onboarding_ajax_upload_document', $token, [ 'step' => 3, 'multipart' => true, 'ajax' => true, 'document_field' => 'driving_license_upload', 'data_attrs' => [ 'itsm-fallback-redirect' => $this->onboarding_url_with_message( $token, '', 4 ) ] ] ); ?>
					<h2><?php esc_html_e( 'Step 3 of 4: Driving License Upload', 'itsm-employee-onboarding' ); ?></h2>
					<p><?php esc_html_e( 'Upload your required driving license document. This step must be completed before final review.', 'itsm-employee-onboarding' ); ?></p>
					<p class="itsm-onboarding-help"><?php esc_html_e( 'Your upload is sent through a secure background request so you can continue without interruption.', 'itsm-employee-onboarding' ); ?></p>
					<?php $this->file_input( 'driving_license_upload', 'Driving License Upload', true ); ?>
					<div class="itsm-onboarding-upload-status" aria-live="polite" style="display:none;margin:0 0 14px;padding:12px 14px;border-radius:12px;border:1px solid #d7e1ee;background:#f8fafc;color:#1f2937;"></div>
					<div class="itsm-onboarding-actions"><button type="submit" class="button button-primary" name="itsm_employee_onboarding_step_submit" value="license"><?php esc_html_e( 'Save and Continue to Review', 'itsm-employee-onboarding' ); ?></button></div>
					<?php $this->render_step_form_close(); ?>
					</div>
				<?php else : ?>
					<div class="itsm-onboarding-panel">
					<?php $this->render_step_form_open( 'itsm_onboarding_final_submit', $token, [ 'step' => 4 ] ); ?>
					<h2><?php esc_html_e( 'Step 4 of 4: Review and Final Submit', 'itsm-employee-onboarding' ); ?></h2>
					<p><?php esc_html_e( 'Review your onboarding details below. When you are ready, submit to send your package for HR review.', 'itsm-employee-onboarding' ); ?></p>
					<div class="itsm-onboarding-review">
						<p><strong><?php esc_html_e( 'Profile Completed:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( 'yes' === get_post_meta( $application->ID, 'itsm_onboarding_step_profile_completed', true ) ? __( 'Yes', 'itsm-employee-onboarding' ) : __( 'No', 'itsm-employee-onboarding' ) ); ?></p>
						<p><strong><?php esc_html_e( 'Tax Confirmation:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( 'yes' === get_post_meta( $application->ID, 'itsm_onboarding_step_tax_completed', true ) ? __( 'Yes', 'itsm-employee-onboarding' ) : __( 'No', 'itsm-employee-onboarding' ) ); ?></p>
						<p><strong><?php esc_html_e( 'Driving License Uploaded:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $application->ID, 'itsm_document_government_id_upload_attachment_id', true ) ? __( 'Yes', 'itsm-employee-onboarding' ) : __( 'No', 'itsm-employee-onboarding' ) ); ?></p>
					</div>
					<div class="itsm-onboarding-actions"><button type="submit" class="button button-primary" name="itsm_employee_onboarding_submit" value="1"><?php esc_html_e( 'Final Submit', 'itsm-employee-onboarding' ); ?></button></div>
					<?php $this->render_step_form_close(); ?>
					</div>
				<?php endif; ?>
				</div>
			</div>
		</div>
		<?php if ( 3 === (int) $current_step ) : ?>
			<script>
			(function() {
				function initOnboardingUpload(form) {
					if (!form || form.dataset.itsmAjaxUploadBound === '1') {
						return;
					}
					form.dataset.itsmAjaxUploadBound = '1';
					var statusBox = form.querySelector('.itsm-onboarding-upload-status');
					var button = form.querySelector('button[type="submit"], input[type="submit"]');
					function showMessage(message, isError) {
						if (!statusBox) {
							return;
						}
						statusBox.textContent = message || '';
						statusBox.style.display = message ? 'block' : 'none';
						statusBox.style.borderColor = isError ? '#fecaca' : '#c7e9d2';
						statusBox.style.background = isError ? '#fff5f5' : '#f0fdf9';
						statusBox.style.color = isError ? '#7f1d1d' : '#0f4f46';
					}
					form.addEventListener('submit', function(event) {
						if (form.dataset.itsmAjaxSubmitting === '1') {
							event.preventDefault();
							return;
						}
						event.preventDefault();
						var url = form.action || '';
						if (!url) {
							showMessage('<?php echo esc_js( __( 'Upload is unavailable right now. Please try again.', 'itsm-employee-onboarding' ) ); ?>', true);
							return;
						}
						var formData = new FormData(form);
						formData.set('document_field', 'driving_license_upload');
						formData.set('itsm_employee_onboarding_nonce', form.querySelector('[name="itsm_employee_onboarding_nonce"]') ? form.querySelector('[name="itsm_employee_onboarding_nonce"]').value : '');
						form.dataset.itsmAjaxSubmitting = '1';
						if (button) {
							button.disabled = true;
							button.setAttribute('aria-busy', 'true');
						}
						showMessage('<?php echo esc_js( __( 'Uploading driving license...', 'itsm-employee-onboarding' ) ); ?>', false);
						fetch(url, {
							method: 'POST',
							body: formData,
							credentials: 'same-origin',
							headers: {
								'X-Requested-With': 'XMLHttpRequest',
								'Accept': 'application/json'
							}
						}).then(function(response) {
							return response.text().then(function(text) {
								var payload = null;
								try {
									payload = text ? JSON.parse(text) : null;
								} catch (error) {
									throw new Error('<?php echo esc_js( __( 'Unexpected server response. Please try again.', 'itsm-employee-onboarding' ) ); ?>');
								}
								if (!response.ok || !payload) {
									throw new Error((payload && payload.data && payload.data.message) ? payload.data.message : '<?php echo esc_js( __( 'Upload failed. Please try again.', 'itsm-employee-onboarding' ) ); ?>');
								}
								return payload;
							});
						}).then(function(payload) {
							var data = payload && payload.data ? payload.data : {};
							if (!payload.success) {
								throw new Error((data && data.message) ? data.message : '<?php echo esc_js( __( 'Upload failed. Please try again.', 'itsm-employee-onboarding' ) ); ?>');
							}
							showMessage(data.message || '<?php echo esc_js( __( 'Driving license uploaded successfully.', 'itsm-employee-onboarding' ) ); ?>', false);
							window.setTimeout(function() {
								window.location.href = data.redirect || (form.dataset.itsmFallbackRedirect || url);
							}, 250);
						}).catch(function(error) {
							showMessage(error && error.message ? error.message : '<?php echo esc_js( __( 'Upload failed. Please try again.', 'itsm-employee-onboarding' ) ); ?>', true);
						}).finally(function() {
							form.dataset.itsmAjaxSubmitting = '';
							if (button) {
								button.disabled = false;
								button.removeAttribute('aria-busy');
							}
						});
					});
				}
				function boot() {
					document.querySelectorAll('.itsm-onboarding-ajax-upload-form').forEach(initOnboardingUpload);
				}
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', boot);
				} else {
					boot();
				}
			})();
			</script>
		<?php endif; ?>
		<?php
	}

	private function text_input( $key, $label, $data, $required = false, $type = 'text' ) {
		printf( '<div class="itsm-onboarding-field"><label for="%1$s"><strong>%2$s%3$s</strong></label><input id="%1$s" type="%4$s" name="%1$s" value="%5$s" %6$s /><small>%7$s</small></div>', esc_attr( $key ), esc_html( $label ), $required ? ' *' : '', esc_attr( $type ), esc_attr( isset( $data[ $key ] ) ? $data[ $key ] : '' ), $required ? 'required' : '', $required ? esc_html__( 'Required field.', 'itsm-employee-onboarding' ) : esc_html__( 'Optional field if applicable.', 'itsm-employee-onboarding' ) );
	}

	private function textarea_input( $key, $label, $data, $required = false ) {
		printf( '<div class="itsm-onboarding-field itsm-onboarding-textarea"><label for="%1$s"><strong>%2$s%3$s</strong></label><textarea id="%1$s" rows="3" name="%1$s" %4$s>%5$s</textarea><small>%6$s</small></div>', esc_attr( $key ), esc_html( $label ), $required ? ' *' : '', $required ? 'required' : '', esc_textarea( isset( $data[ $key ] ) ? $data[ $key ] : '' ), $required ? esc_html__( 'Required field.', 'itsm-employee-onboarding' ) : esc_html__( 'Optional field if applicable.', 'itsm-employee-onboarding' ) );
	}

	private function file_input( $key, $label, $required = false ) {
		printf( '<div class="itsm-onboarding-file itsm-onboarding-upload-box %5$s"><label for="%1$s"><strong>%2$s%3$s</strong></label><input id="%1$s" type="file" name="%1$s" accept=".pdf,.jpg,.jpeg,.png,.docx" %4$s /><small>%6$s</small></div>', esc_attr( $key ), esc_html( $label ), $required ? ' *' : '', $required ? 'required' : '', $required ? 'is-required' : '', esc_html__( 'Allowed: PDF, JPG, JPEG, PNG, DOCX.', 'itsm-employee-onboarding' ) );
	}

	private function handle_onboarding_submit( $application, $token ) {
		if ( ! isset( $_POST['itsm_employee_onboarding_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['itsm_employee_onboarding_nonce'] ) ), 'itsm_employee_onboarding_' . $token ) ) {
			wp_die( esc_html__( 'Security check failed.', 'itsm-employee-onboarding' ) );
		}
		$action = isset( $_POST['itsm_employee_onboarding_step_submit'] ) ? sanitize_key( wp_unslash( $_POST['itsm_employee_onboarding_step_submit'] ) ) : ( isset( $_POST['itsm_employee_onboarding_submit'] ) ? 'final' : '' );
		if ( 'profile' === $action ) {
			$this->handle_profile_step_submit( $application, $token );
			return;
		}
		if ( 'tax' === $action ) {
			$this->handle_tax_step_submit( $application, $token );
			return;
		}
		if ( 'license' === $action ) {
			$this->handle_license_step_submit( $application, $token );
			return;
		}
		if ( 'final' === $action ) {
			$this->handle_final_step_submit( $application, $token );
			return;
		}
		$this->render_onboarding_form_page( $application, $token, [ __( 'Please use the onboarding step buttons to continue.', 'itsm-employee-onboarding' ) ], null, $this->get_onboarding_current_step( $application->ID ) );
	}

	public function handle_onboarding_step_post() {
		$token = isset( $_POST['itsm_onboarding_token'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_onboarding_token'] ) ) : '';
		list( $application, $error ) = $this->validate_token_application( $token );
		if ( ! $application ) {
			$this->render_candidate_error_page(
				__( 'Onboarding link unavailable', 'itsm-employee-onboarding' ),
				$error ? $error : __( 'This onboarding link is invalid, expired, or has already been used. Please contact HR to request a new onboarding link.', 'itsm-employee-onboarding' )
			);
		}
		$nonce = isset( $_POST['itsm_employee_onboarding_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_employee_onboarding_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'itsm_onboarding_step_' . $token ) ) {
			$this->redirect_back_to_onboarding( $token, __( 'Security check failed. Please try again.', 'itsm-employee-onboarding' ), $this->get_onboarding_current_step( $application->ID ) );
		}
		$step = isset( $_POST['itsm_onboarding_step'] ) ? absint( $_POST['itsm_onboarding_step'] ) : $this->get_onboarding_current_step( $application->ID );
		$step_submit = isset( $_POST['itsm_employee_onboarding_step_submit'] ) ? sanitize_key( wp_unslash( $_POST['itsm_employee_onboarding_step_submit'] ) ) : '';
		if ( 1 === $step ) {
			$this->handle_profile_step_submit( $application, $token );
			return;
		}
		if ( 2 === $step ) {
			$this->handle_tax_step_submit( $application, $token );
			return;
		}
		if ( 3 === $step && 'license' === $step_submit ) {
			if ( empty( $_FILES['driving_license_upload']['name'] ) && empty( $_FILES['government_id_upload']['name'] ) ) {
				$this->redirect_back_to_onboarding( $token, __( 'A driving license upload is required before continuing.', 'itsm-employee-onboarding' ), 3 );
			}
			$upload_key = ! empty( $_FILES['driving_license_upload']['name'] ) ? 'driving_license_upload' : 'government_id_upload';
			$attachment_id = $this->handle_private_upload( $upload_key, $application->ID );
			if ( ! $attachment_id ) {
				$this->redirect_back_to_onboarding( $token, __( 'The document could not be uploaded. Please try again.', 'itsm-employee-onboarding' ), 3 );
			}
			update_post_meta( $application->ID, 'itsm_document_government_id_upload_attachment_id', $attachment_id );
			update_post_meta( $application->ID, 'itsm_document_government_id_upload_status', 'uploaded' );
			update_post_meta( $application->ID, 'itsm_document_driving_license_upload_attachment_id', $attachment_id );
			update_post_meta( $application->ID, 'itsm_document_driving_license_upload_status', 'uploaded' );
			update_post_meta( $application->ID, 'government_id_document_id', (int) $attachment_id );
			update_post_meta( $application->ID, 'driving_license_document_id', (int) $attachment_id );
			update_post_meta( $application->ID, 'itsm_onboarding_step_driving_license_completed', 'yes' );
			update_post_meta( $application->ID, 'itsm_onboarding_step_driving_license_completed_at', current_time( 'mysql', true ) );
			$this->record_document_extraction( $application->ID, 'driving_license_upload', $attachment_id );
			$this->set_onboarding_step_meta( $application->ID, 4 );
			$this->redirect_back_to_onboarding( $token, __( 'Driving license saved. Review your package and submit when ready.', 'itsm-employee-onboarding' ), 4 );
		}
		$this->redirect_back_to_onboarding( $token, __( 'Please use the onboarding step buttons to continue.', 'itsm-employee-onboarding' ), $this->get_onboarding_current_step( $application->ID ) );
	}

	public function handle_onboarding_ajax_upload_document() {
		$this->handle_onboarding_upload_request( true );
	}

	public function handle_onboarding_upload_post() {
		$this->handle_onboarding_upload_request( false );
	}

	private function handle_onboarding_upload_request( $is_ajax = false ) {
		$token = isset( $_POST['itsm_onboarding_token'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_onboarding_token'] ) ) : '';
		$ajax_requested = $this->is_onboarding_ajax_request( $is_ajax );
		list( $application, $error ) = $this->validate_token_application( $token );
		if ( ! $application ) {
			$message = $error ? $error : __( 'This onboarding link is invalid, expired, or has already been used. Please contact HR to request a new onboarding link.', 'itsm-employee-onboarding' );
			if ( $ajax_requested ) {
				$this->send_ajax_onboarding_error( $message );
			} else {
				$this->redirect_back_to_onboarding( $token, $message, 3 );
			}
			$this->render_candidate_error_page(
				__( 'Onboarding link unavailable', 'itsm-employee-onboarding' ),
				$message
			);
		}
		$nonce = isset( $_POST['itsm_employee_onboarding_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_employee_onboarding_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'itsm_onboarding_upload_document_' . $token ) && ! wp_verify_nonce( $nonce, 'itsm_onboarding_ajax_upload_document_' . $token ) ) {
			$message = __( 'Security check failed. Please try again.', 'itsm-employee-onboarding' );
			if ( $ajax_requested ) {
				$this->send_ajax_onboarding_error( $message );
			} else {
				$this->redirect_back_to_onboarding( $token, $message, $this->get_onboarding_current_step( $application->ID ) );
			}
			return;
		}
		$field = isset( $_POST['document_field'] ) ? sanitize_key( wp_unslash( $_POST['document_field'] ) ) : '';
		if ( 'driving_license_upload' !== $field ) {
			$message = __( 'Please choose a valid document upload step.', 'itsm-employee-onboarding' );
			if ( $ajax_requested ) {
				$this->send_ajax_onboarding_error( $message );
			} else {
				$this->redirect_back_to_onboarding( $token, $message, $this->get_onboarding_current_step( $application->ID ) );
			}
			return;
		}
		$upload_result = $this->process_driving_license_upload( $application->ID, $field );
		if ( is_wp_error( $upload_result ) ) {
			$message = $upload_result->get_error_message();
			if ( $ajax_requested ) {
				$this->send_ajax_onboarding_error( $message );
			} else {
				$this->redirect_back_to_onboarding( $token, $message, $this->get_onboarding_current_step( $application->ID ) );
			}
			return;
		}
		$message = __( 'Driving license saved. Review your package and submit when ready.', 'itsm-employee-onboarding' );
		if ( $ajax_requested ) {
			$this->send_ajax_onboarding_success( $message, $this->onboarding_url_with_message( $token, $message, 4 ), 4, $upload_result );
		} else {
			$this->redirect_back_to_onboarding( $token, $message, 4 );
		}
	}

	public function handle_onboarding_final_post() {
		$token = isset( $_POST['itsm_onboarding_token'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_onboarding_token'] ) ) : '';
		list( $application, $error ) = $this->validate_token_application( $token );
		if ( ! $application ) {
			$this->render_candidate_error_page(
				__( 'Onboarding link unavailable', 'itsm-employee-onboarding' ),
				$error ? $error : __( 'This onboarding link is invalid, expired, or has already been used. Please contact HR to request a new onboarding link.', 'itsm-employee-onboarding' )
			);
		}
		$nonce = isset( $_POST['itsm_employee_onboarding_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_employee_onboarding_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'itsm_onboarding_final_submit_' . $token ) ) {
			$this->redirect_back_to_onboarding( $token, __( 'Security check failed. Please try again.', 'itsm-employee-onboarding' ), $this->get_onboarding_current_step( $application->ID ) );
		}
		$this->handle_final_step_submit( $application, $token );
	}

	private function handle_profile_step_submit( $application, $token ) {
		$data = $this->get_posted_profile_data();
		update_post_meta( $application->ID, self::RESPONSE_META, $data );
		update_post_meta( $application->ID, 'employment_type', 'contract' );
		update_post_meta( $application->ID, 'itsm_employment_type', 'contract' );
		update_post_meta( $application->ID, 'itsm_onboarding_step_profile_completed', 'yes' );
		update_post_meta( $application->ID, 'itsm_onboarding_step_profile_completed_at', current_time( 'mysql', true ) );
		$this->set_onboarding_step_meta( $application->ID, 2 );
		$this->render_onboarding_form_page( $application, $token, [ __( 'Profile details saved. Continue with tax confirmation.', 'itsm-employee-onboarding' ) ], $data, 2 );
	}

	private function handle_tax_step_submit( $application, $token ) {
		$data = (array) get_post_meta( $application->ID, self::RESPONSE_META, true );
		if ( empty( $_POST['tax_form_confirmed'] ) ) {
			$this->render_onboarding_form_page( $application, $token, [ __( 'Please confirm your tax documentation step before continuing.', 'itsm-employee-onboarding' ) ], $data, 2 );
		}
		update_post_meta( $application->ID, 'itsm_onboarding_tax_form_required', 'yes' );
		update_post_meta( $application->ID, 'itsm_onboarding_tax_form_method', 'taxbandits_portal' );
		update_post_meta( $application->ID, 'itsm_onboarding_tax_form_status', 'complete' );
		update_post_meta( $application->ID, 'itsm_onboarding_tax_form_completed_date', current_time( 'mysql', true ) );
		update_post_meta( $application->ID, 'itsm_onboarding_irs_w9_reference_shown', 'yes' );
		update_post_meta( $application->ID, 'itsm_onboarding_step_tax_completed', 'yes' );
		update_post_meta( $application->ID, 'itsm_onboarding_step_tax_completed_at', current_time( 'mysql', true ) );
		update_post_meta( $application->ID, 'tax_form_required', 'Yes' );
		update_post_meta( $application->ID, 'tax_form_method', 'TaxBandits' );
		update_post_meta( $application->ID, 'tax_form_status', 'Completed' );
		update_post_meta( $application->ID, 'tax_form_completed_date', current_time( 'mysql', true ) );
		update_post_meta( $application->ID, 'irs_w9_reference_shown', 'Yes' );
		$this->set_onboarding_step_meta( $application->ID, 3 );
		$this->render_onboarding_form_page( $application, $token, [ __( 'Tax confirmation saved. Continue with your driving license upload.', 'itsm-employee-onboarding' ) ], $data, 3 );
	}

	private function handle_license_step_submit( $application, $token ) {
		$data = (array) get_post_meta( $application->ID, self::RESPONSE_META, true );
		if ( empty( $_FILES['driving_license_upload']['name'] ) && empty( $_FILES['government_id_upload']['name'] ) ) {
			$this->render_onboarding_form_page( $application, $token, [ __( 'A driving license upload is required before continuing.', 'itsm-employee-onboarding' ) ], $data, 3 );
		}
		$upload_key = ! empty( $_FILES['driving_license_upload']['name'] ) ? 'driving_license_upload' : 'government_id_upload';
		$attachment_id = $this->handle_private_upload( $upload_key, $application->ID );
		if ( ! $attachment_id ) {
			$this->render_onboarding_form_page( $application, $token, [ __( 'A driving license upload is required before continuing.', 'itsm-employee-onboarding' ) ], $data, 3 );
		}
		update_post_meta( $application->ID, 'itsm_document_government_id_upload_attachment_id', $attachment_id );
		update_post_meta( $application->ID, 'itsm_document_government_id_upload_status', 'uploaded' );
		update_post_meta( $application->ID, 'itsm_document_driving_license_upload_attachment_id', $attachment_id );
		update_post_meta( $application->ID, 'itsm_document_driving_license_upload_status', 'uploaded' );
		update_post_meta( $application->ID, 'government_id_document_id', (int) $attachment_id );
		update_post_meta( $application->ID, 'driving_license_document_id', (int) $attachment_id );
		update_post_meta( $application->ID, 'itsm_onboarding_step_driving_license_completed', 'yes' );
		update_post_meta( $application->ID, 'itsm_onboarding_step_driving_license_completed_at', current_time( 'mysql', true ) );
		$this->record_document_extraction( $application->ID, 'driving_license_upload', $attachment_id );
		$this->set_onboarding_step_meta( $application->ID, 4 );
		$this->render_onboarding_form_page( $application, $token, [ __( 'Driving license saved. Review your package and submit when ready.', 'itsm-employee-onboarding' ) ], $data, 4 );
	}

	private function handle_final_step_submit( $application, $token ) {
		$data = (array) get_post_meta( $application->ID, self::RESPONSE_META, true );
		if ( 'yes' !== get_post_meta( $application->ID, 'itsm_onboarding_step_profile_completed', true ) ) {
			$this->render_onboarding_form_page( $application, $token, [ __( 'Please complete the profile step before final submission.', 'itsm-employee-onboarding' ) ], $data, 1 );
		}
		if ( 'yes' !== get_post_meta( $application->ID, 'itsm_onboarding_step_tax_completed', true ) ) {
			$this->render_onboarding_form_page( $application, $token, [ __( 'Please complete the tax confirmation step before final submission.', 'itsm-employee-onboarding' ) ], $data, 2 );
		}
		if ( 'yes' !== get_post_meta( $application->ID, 'itsm_onboarding_step_driving_license_completed', true ) ) {
			$this->render_onboarding_form_page( $application, $token, [ __( 'A driving license upload is required before final submission.', 'itsm-employee-onboarding' ) ], $data, 3 );
		}
		update_post_meta( $application->ID, 'itsm_onboarding_package_status', 'pending_review' );
		update_post_meta( $application->ID, self::STATE_META, 'pending_review' );
		update_post_meta( $application->ID, 'itsm_onboarding_submitted_at', current_time( 'mysql', true ) );
		update_post_meta( $application->ID, self::TOKEN_USED_META, current_time( 'mysql', true ) );
		$this->notify_admin_package_submitted( $application->ID );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'itsm_onboarding', 'submitted', home_url( '/' ) ) );
		exit;
	}

	private function get_posted_profile_data() {
		$fields = $this->onboarding_fields();
		$data   = [];
		foreach ( $fields as $key => $label ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$data[ $key ] = is_array( $value ) ? '' : sanitize_textarea_field( $value );
		}
		$data['employment_type'] = 'contract';
		$data['candidate_type']  = 'contract';
		return $data;
	}

	private function handle_private_upload( $field, $application_id ) {
		$prepared = $this->prepare_private_upload( $field );
		if ( is_wp_error( $prepared ) ) {
			wp_die( esc_html( $prepared->get_error_message() ) );
		}
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		add_filter( 'upload_dir', [ $this, 'private_upload_dir' ] );
		$upload = wp_handle_upload(
			$_FILES[ $field ],
			[
				'test_form' => false,
				'mimes'     => $prepared['allowed'],
			]
		);
		remove_filter( 'upload_dir', [ $this, 'private_upload_dir' ] );
		if ( ! empty( $upload['error'] ) ) {
			wp_die( esc_html( $upload['error'] ) );
		}
		return (int) $this->store_private_upload_attachment( $upload, $application_id, $field );
	}

	private function prepare_private_upload( $field ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return new WP_Error( 'missing_upload', __( 'A driving license upload is required before continuing.', 'itsm-employee-onboarding' ) );
		}
		$s = $this->get_settings();
		if ( ! isset( $_FILES[ $field ]['size'] ) || $_FILES[ $field ]['size'] > ( absint( $s['max_file_size_mb'] ) * MB_IN_BYTES ) ) {
			return new WP_Error( 'file_too_large', __( 'Uploaded file is too large.', 'itsm-employee-onboarding' ) );
		}
		$allowed = [
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		];
		$upload_name   = sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) );
		$upload_ext    = strtolower( pathinfo( $upload_name, PATHINFO_EXTENSION ) );
		$filetype      = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], $upload_name, $allowed );
		$detected_ext  = ! empty( $filetype['ext'] ) ? strtolower( $filetype['ext'] ) : $upload_ext;
		$detected_type = ! empty( $filetype['type'] ) ? strtolower( $filetype['type'] ) : '';
		$is_allowed_ext = in_array( $detected_ext, array_keys( $allowed ), true );
		$is_allowed_type = empty( $detected_type ) || in_array( $detected_type, $allowed, true );
		$is_docx_zip_fallback = 'docx' === $upload_ext && ( 'application/zip' === $detected_type || empty( $detected_type ) );
		if ( ! $is_allowed_ext || ( ! $is_allowed_type && ! $is_docx_zip_fallback ) ) {
			return new WP_Error( 'invalid_type', __( 'Unsupported file type.', 'itsm-employee-onboarding' ) );
		}
		return [
			'allowed' => $allowed,
		];
	}

	private function store_private_upload_attachment( array $upload, $application_id, $field ) {
		$this->protect_upload_directory( dirname( $upload['file'] ) );
		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'private',
				'post_parent'    => $application_id,
			],
			$upload['file']
		);
		update_post_meta( $attachment_id, 'itsm_private_document', 'yes' );
		update_post_meta( $attachment_id, 'itsm_application_id', $application_id );
		update_post_meta( $attachment_id, 'itsm_document_field', $field );
		return $attachment_id;
	}

	private function process_driving_license_upload( $application_id, $field ) {
		$prepared = $this->prepare_private_upload( $field );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		add_filter( 'upload_dir', [ $this, 'private_upload_dir' ] );
		$upload = wp_handle_upload(
			$_FILES[ $field ],
			[
				'test_form' => false,
				'mimes'     => $prepared['allowed'],
			]
		);
		remove_filter( 'upload_dir', [ $this, 'private_upload_dir' ] );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload_failed', $upload['error'] );
		}
		$attachment_id = $this->store_private_upload_attachment( $upload, $application_id, $field );
		update_post_meta( $application_id, 'itsm_document_government_id_upload_attachment_id', $attachment_id );
		update_post_meta( $application_id, 'itsm_document_government_id_upload_status', 'uploaded' );
		update_post_meta( $application_id, 'itsm_document_driving_license_upload_attachment_id', $attachment_id );
		update_post_meta( $application_id, 'itsm_document_driving_license_upload_status', 'uploaded' );
		update_post_meta( $application_id, 'government_id_document_id', (int) $attachment_id );
		update_post_meta( $application_id, 'driving_license_document_id', (int) $attachment_id );
		update_post_meta( $application_id, 'itsm_onboarding_step_driving_license_completed', 'yes' );
		update_post_meta( $application_id, 'itsm_onboarding_step_driving_license_completed_at', current_time( 'mysql', true ) );
		$this->record_document_extraction( $application_id, 'driving_license_upload', $attachment_id );
		$this->set_onboarding_step_meta( $application_id, 4 );
		return [
			'attachment_id' => (int) $attachment_id,
			'message'       => __( 'Driving license saved. Review your package and submit when ready.', 'itsm-employee-onboarding' ),
			'allowed'       => array_keys( $prepared['allowed'] ),
		];
	}

	private function send_ajax_onboarding_success( $message, $redirect, $step, $result = [] ) {
		wp_send_json_success(
			[
				'message' => $message,
				'redirect' => $redirect,
				'step'    => absint( $step ),
				'result'  => is_array( $result ) ? $result : [],
			]
		);
	}

	private function send_ajax_onboarding_error( $message ) {
		wp_send_json_error( [ 'message' => $message ] );
	}

	private function record_document_extraction( $application_id, $field, $attachment_id ) {
		$result = $this->build_document_extraction_result( $attachment_id, $field );
		update_post_meta( $application_id, 'itsm_document_' . $field . '_extraction_status', $result['status'] );
		update_post_meta( $application_id, 'itsm_document_' . $field . '_extracted_data', $result['data'] );
		update_post_meta( $application_id, 'itsm_document_' . $field . '_suggested_data', $result['suggestions'] );
		update_post_meta( $application_id, 'itsm_document_' . $field . '_extraction_notes', $result['notes'] );
		update_post_meta( $application_id, 'itsm_document_' . $field . '_extracted_at', current_time( 'mysql', true ) );
		$summary = (array) get_post_meta( $application_id, 'itsm_document_extraction_summary', true );
		$summary[ $field ] = $result;
		update_post_meta( $application_id, 'itsm_document_extraction_summary', $summary );
	}

	private function build_document_extraction_result( $attachment_id, $field ) {
		$file = get_attached_file( $attachment_id );
		$mime = get_post_mime_type( $attachment_id );
		$name = $file ? basename( $file ) : '';
		$result = [
			'status'      => 'not_available',
			'data'        => [],
			'suggestions' => [],
			'notes'       => __( 'Safe local document extraction is not available for this file type on the server.', 'itsm-employee-onboarding' ),
		];
		if ( ! $file || ! is_file( $file ) ) {
			$result['notes'] = __( 'Uploaded file could not be inspected.', 'itsm-employee-onboarding' );
			return $result;
		}
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
			$size = @getimagesize( $file );
			if ( ! empty( $size ) ) {
				$result['status']      = 'suggested';
				$result['data']        = [
					'width'  => isset( $size[0] ) ? absint( $size[0] ) : 0,
					'height' => isset( $size[1] ) ? absint( $size[1] ) : 0,
					'mime'   => isset( $size['mime'] ) ? sanitize_text_field( $size['mime'] ) : $mime,
				];
				$result['suggestions'] = [
					'document_type' => $field,
					'file_name'     => $name,
					'file_mime'     => $mime,
					'visual_check'   => __( 'Admin review required to verify the uploaded image content.', 'itsm-employee-onboarding' ),
				];
				$result['notes']       = __( 'Image metadata captured locally for admin review only.', 'itsm-employee-onboarding' );
			}
			return $result;
		}
		if ( 'docx' === $ext && class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $file ) ) {
				$xml  = $zip->getFromName( 'word/document.xml' );
				$zip->close();
				if ( $xml ) {
					$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $xml ) ) );
					if ( '' !== $text ) {
						$result['status']      = 'suggested';
						$result['data']        = [
							'preview'   => substr( $text, 0, 500 ),
							'file_name' => $name,
							'file_mime' => $mime,
						];
						$result['suggestions'] = [
							'document_type'  => $field,
							'file_name'      => $name,
							'file_mime'      => $mime,
							'review_required' => __( 'Verify extracted preview manually; this is a suggestion only.', 'itsm-employee-onboarding' ),
						];
						$result['notes'] = __( 'Basic local DOCX text preview captured for admin review only.', 'itsm-employee-onboarding' );
					}
				}
			}
		}
		return $result;
	}

	public function private_upload_dir( $dirs ) {
		$subdir = '/itsm-private-docs' . $dirs['subdir'];
		$dirs['path']   = $dirs['basedir'] . $subdir;
		$dirs['url']    = $dirs['baseurl'] . $subdir;
		$dirs['subdir'] = $subdir;
		return $dirs;
	}

	private function protect_upload_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$root = trailingslashit( WP_CONTENT_DIR ) . 'uploads/itsm-private-docs';
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}
		$rules = "Options -Indexes\n<Files *>\nRequire all denied\nDeny from all\n</Files>\n";
		if ( is_dir( $root ) && ! file_exists( trailingslashit( $root ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $root ) . '.htaccess', $rules );
		}
		if ( ! file_exists( trailingslashit( $dir ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $dir ) . '.htaccess', $rules );
		}
	}

	private function notify_admin_package_submitted( $application_id ) {
		$s = $this->get_settings();
		if ( ! is_email( $s['admin_email'] ) ) {
			return;
		}
		$subject = '[DEV TEST] Onboarding package submitted';
		$message = 'Application #' . $application_id . ' has been submitted for onboarding review.';
		wp_mail( $s['admin_email'], $subject, $message );
	}

	public function handle_document_download() {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		if ( ! $attachment_id || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'itsm_download_' . $attachment_id ) ) {
			wp_die( esc_html__( 'Invalid download request.', 'itsm-employee-onboarding' ) );
		}
		if ( ! $this->current_user_can_view_document( $attachment_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this document.', 'itsm-employee-onboarding' ) );
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_file( $file ) ) {
			wp_die( esc_html__( 'File not found.', 'itsm-employee-onboarding' ) );
		}
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		readfile( $file );
		exit;
	}

	private function current_user_can_view_document( $attachment_id ) {
		if ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$application_id = (int) get_post_meta( $attachment_id, 'itsm_application_id', true );
		$profile_id     = $this->get_employee_profile_for_user( $user_id );
		return $profile_id && $application_id && (int) get_post_meta( $profile_id, 'itsm_hirezoot_application_id', true ) === $application_id;
	}

	public function render_review_queue() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$q = new WP_Query(
			[
				'post_type'      => 'awsm_job_application',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'meta_key'       => 'itsm_onboarding_package_status',
				'meta_value'     => 'pending_review',
			]
		);
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Onboarding Review Queue', 'itsm-employee-onboarding' ); ?></h1>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Application', 'itsm-employee-onboarding' ); ?></th><th><?php esc_html_e( 'Candidate', 'itsm-employee-onboarding' ); ?></th><th><?php esc_html_e( 'Tax Documentation', 'itsm-employee-onboarding' ); ?></th><th><?php esc_html_e( 'Documents', 'itsm-employee-onboarding' ); ?></th><th><?php esc_html_e( 'Actions', 'itsm-employee-onboarding' ); ?></th></tr></thead><tbody>
		<?php foreach ( $q->posts as $post ) : ?>
			<tr>
				<td>#<?php echo esc_html( $post->ID ); ?></td>
				<td><?php echo esc_html( get_post_meta( $post->ID, 'awsm_applicant_name', true ) ); ?><br /><?php echo esc_html( get_post_meta( $post->ID, 'awsm_applicant_email', true ) ); ?></td>
				<td><?php $this->render_tax_summary( $post->ID ); ?></td>
				<td><?php $this->render_document_summary( $post->ID ); ?></td>
				<td><?php $this->render_review_buttons( $post->ID ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	private function render_tax_summary( $application_id ) {
		$status       = get_post_meta( $application_id, 'itsm_onboarding_tax_form_status', true );
		$completed_at = get_post_meta( $application_id, 'itsm_onboarding_tax_form_completed_date', true );
		$method       = get_post_meta( $application_id, 'tax_form_method', true );
		echo '<p><strong>' . esc_html__( 'Status:', 'itsm-employee-onboarding' ) . '</strong> ' . esc_html( $status ? $status : __( 'pending', 'itsm-employee-onboarding' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Method:', 'itsm-employee-onboarding' ) . '</strong> ' . esc_html( $method ? $method : __( 'TaxBandits', 'itsm-employee-onboarding' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Completed:', 'itsm-employee-onboarding' ) . '</strong> ' . esc_html( $completed_at ? $completed_at : __( 'Not completed', 'itsm-employee-onboarding' ) ) . '</p>';
	}

	private function render_document_summary( $application_id ) {
		$data = (array) get_post_meta( $application_id, self::RESPONSE_META, true );
		echo '<p><strong>' . esc_html__( 'Government ID:', 'itsm-employee-onboarding' ) . '</strong><br />';
		$this->render_single_document_link( $application_id, 'driving_license_upload', __( 'Download Driving License', 'itsm-employee-onboarding' ) );
		$this->render_extraction_summary( $application_id, 'driving_license_upload' );
		if ( ! get_post_meta( $application_id, 'itsm_document_driving_license_upload_attachment_id', true ) ) {
			$this->render_single_document_link( $application_id, 'government_id_upload', __( 'Download Government ID', 'itsm-employee-onboarding' ) );
			$this->render_extraction_summary( $application_id, 'government_id_upload' );
		}
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Certification / License:', 'itsm-employee-onboarding' ) . '</strong><br />';
		$this->render_single_document_link( $application_id, 'certification_upload', __( 'Download Certification', 'itsm-employee-onboarding' ) );
		$this->render_extraction_summary( $application_id, 'certification_upload' );
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'Additional Documents:', 'itsm-employee-onboarding' ) . '</strong><br />';
		$this->render_single_document_link( $application_id, 'additional_document_upload', __( 'Download Additional Document', 'itsm-employee-onboarding' ) );
		$this->render_extraction_summary( $application_id, 'additional_document_upload' );
		echo '</p>';
	}

	private function render_extraction_summary( $application_id, $field ) {
		$status    = get_post_meta( $application_id, 'itsm_document_' . $field . '_extraction_status', true );
		$data      = (array) get_post_meta( $application_id, 'itsm_document_' . $field . '_extracted_data', true );
		$suggested = (array) get_post_meta( $application_id, 'itsm_document_' . $field . '_suggested_data', true );
		$notes     = get_post_meta( $application_id, 'itsm_document_' . $field . '_extraction_notes', true );
		echo '<br /><small><strong>' . esc_html__( 'Extraction status:', 'itsm-employee-onboarding' ) . '</strong> ' . esc_html( $status ? $status : __( 'not_available', 'itsm-employee-onboarding' ) ) . '</small>';
		if ( ! empty( $data ) ) {
			echo '<br /><small><strong>' . esc_html__( 'Extracted data:', 'itsm-employee-onboarding' ) . '</strong> ' . esc_html( wp_json_encode( $data ) ) . '</small>';
		}
		if ( ! empty( $suggested ) ) {
			echo '<br /><small><strong>' . esc_html__( 'Suggested data:', 'itsm-employee-onboarding' ) . '</strong> ' . esc_html( wp_json_encode( $suggested ) ) . '</small>';
		}
		if ( $notes ) {
			echo '<br /><small>' . esc_html( $notes ) . '</small>';
		}
	}

	private function render_single_document_link( $application_id, $field, $label ) {
		$attachment_id = (int) get_post_meta( $application_id, 'itsm_document_' . $field . '_attachment_id', true );
		if ( ! $attachment_id ) {
			echo esc_html__( 'No document uploaded', 'itsm-employee-onboarding' );
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=itsm_onboarding_download&attachment_id=' . $attachment_id ), 'itsm_download_' . $attachment_id );
		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	private function render_document_links( $application_id ) {
		foreach ( [ 'driving_license_upload' => 'Driving License', 'government_id_upload' => 'Government ID', 'certification_upload' => 'Certification', 'additional_document_upload' => 'Additional Document' ] as $field => $label ) {
			$attachment_id = (int) get_post_meta( $application_id, 'itsm_document_' . $field . '_attachment_id', true );
			if ( $attachment_id ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=itsm_onboarding_download&attachment_id=' . $attachment_id ), 'itsm_download_' . $attachment_id );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a><br />';
			}
		}
	}

	private function render_review_buttons( $application_id ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'itsm_review_' . $application_id );
		echo '<input type="hidden" name="action" value="itsm_onboarding_review" />';
		echo '<input type="hidden" name="application_id" value="' . esc_attr( $application_id ) . '" />';
		echo '<p><label><strong>' . esc_html__( 'Internal Notes', 'itsm-employee-onboarding' ) . '</strong><br /><textarea name="internal_notes" rows="3" style="width:100%;">' . esc_textarea( get_post_meta( $application_id, 'itsm_onboarding_internal_notes', true ) ) . '</textarea></label></p>';
		foreach ( [ 'approve' => 'Approve', 'request_updates' => 'Request Updates', 'reject' => 'Reject' ] as $action => $label ) {
			echo '<button class="button" style="margin:2px;" type="submit" name="review_action" value="' . esc_attr( $action ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</form>';
	}

	public function handle_review_action() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'itsm-employee-onboarding' ) );
		}
		$application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : ( isset( $_GET['application_id'] ) ? absint( $_GET['application_id'] ) : 0 );
		$action         = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : ( isset( $_GET['review_action'] ) ? sanitize_key( wp_unslash( $_GET['review_action'] ) ) : '' );
		$nonce          = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '' );
		if ( ! $application_id || ! wp_verify_nonce( $nonce, 'itsm_review_' . $application_id ) ) {
			wp_die( esc_html__( 'Invalid review request.', 'itsm-employee-onboarding' ) );
		}
		if ( isset( $_POST['internal_notes'] ) ) {
			update_post_meta( $application_id, 'itsm_onboarding_internal_notes', sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ) ) );
		}
		if ( 'approve' === $action ) {
			$profile_id = $this->create_or_update_employee_profile( $application_id );
			$user_id    = $this->create_or_link_employee_user( $application_id, $profile_id );
			update_post_meta( $application_id, 'itsm_onboarding_package_status', 'approved' );
			update_post_meta( $application_id, self::STATE_META, 'approved_dashboard_access_sent' );
			$this->send_dashboard_access_email( $user_id, $application_id );
		} elseif ( 'request_updates' === $action ) {
			update_post_meta( $application_id, 'itsm_onboarding_package_status', 'updates_requested' );
			update_post_meta( $application_id, self::STATE_META, 'updates_requested' );
		} elseif ( 'reject' === $action ) {
			update_post_meta( $application_id, 'itsm_onboarding_package_status', 'rejected' );
			update_post_meta( $application_id, self::STATE_META, 'onboarding_rejected' );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::PROFILE_CPT . '&page=itsm-onboarding-review-queue' ) );
		exit;
	}

	private function create_or_update_employee_profile( $application_id ) {
		$existing = (int) get_post_meta( $application_id, 'itsm_employee_profile_id', true );
		$data     = (array) get_post_meta( $application_id, self::RESPONSE_META, true );
		$title    = ! empty( $data['full_name'] ) ? $data['full_name'] : get_post_meta( $application_id, 'awsm_applicant_name', true );
		if ( $existing && get_post( $existing ) ) {
			$profile_id = $existing;
			wp_update_post( [ 'ID' => $profile_id, 'post_title' => $title ] );
		} else {
			$profile_id = wp_insert_post(
				[
					'post_type'   => self::PROFILE_CPT,
					'post_status' => 'publish',
					'post_title'  => $title,
				]
			);
			update_post_meta( $application_id, 'itsm_employee_profile_id', $profile_id );
		}
		$job_id = (int) get_post_field( 'post_parent', $application_id );
		$map = [
			'itsm_candidate_name'             => isset( $data['full_name'] ) ? $data['full_name'] : '',
			'itsm_candidate_email'            => isset( $data['email'] ) ? $data['email'] : '',
			'itsm_candidate_phone'            => isset( $data['phone'] ) ? $data['phone'] : '',
			'itsm_candidate_address'          => isset( $data['address'] ) ? $data['address'] : '',
			'itsm_job_applied_for'            => $job_id ? get_the_title( $job_id ) : '',
			'itsm_job_location'               => isset( $data['job_location_applied_for'] ) ? $data['job_location_applied_for'] : '',
			'itsm_hirezoot_application_id'    => $application_id,
			'itsm_candidate_type'             => isset( $data['employment_type'] ) ? $data['employment_type'] : ( isset( $data['candidate_type'] ) ? $data['candidate_type'] : '' ),
			'itsm_employment_type'            => isset( $data['employment_type'] ) ? $data['employment_type'] : '',
			'itsm_onboarding_status'          => 'approved',
			'itsm_document_status'            => 'approved',
			'itsm_tax_documentation_status'   => get_post_meta( $application_id, 'itsm_onboarding_tax_form_status', true ),
			'itsm_tax_documentation_completed_date' => get_post_meta( $application_id, 'itsm_onboarding_tax_form_completed_date', true ),
			'itsm_certification_status'       => 'approved',
		];
		foreach ( $map as $key => $value ) {
			update_post_meta( $profile_id, $key, $value );
		}
		return (int) $profile_id;
	}

	private function create_or_link_employee_user( $application_id, $profile_id ) {
		$user_id = (int) get_post_meta( $application_id, 'itsm_employee_user_id', true );
		if ( $user_id && get_user_by( 'id', $user_id ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( self::EMPLOYEE_ROLE );
		} else {
			$data  = (array) get_post_meta( $application_id, self::RESPONSE_META, true );
			$email = isset( $data['email'] ) && is_email( $data['email'] ) ? $data['email'] : get_post_meta( $application_id, 'awsm_applicant_email', true );
			$name  = isset( $data['full_name'] ) ? $data['full_name'] : get_post_meta( $application_id, 'awsm_applicant_name', true );
			$user  = get_user_by( 'email', $email );
			if ( $user ) {
				$user_id = $user->ID;
				$user->set_role( self::EMPLOYEE_ROLE );
			} else {
				$login   = sanitize_user( current( explode( '@', $email ) ), true );
				$base    = $login ? $login : 'itsm_employee';
				$login   = $base;
				$counter = 1;
				while ( username_exists( $login ) ) {
					$login = $base . $counter;
					$counter++;
				}
				$user_id = wp_insert_user(
					[
						'user_login'   => $login,
						'user_email'   => $email,
						'display_name' => $name,
						'user_pass'    => wp_generate_password( 32, true, true ),
						'role'         => self::EMPLOYEE_ROLE,
					]
				);
				if ( is_wp_error( $user_id ) ) {
					wp_die( esc_html( $user_id->get_error_message() ) );
				}
			}
			update_post_meta( $application_id, 'itsm_employee_user_id', $user_id );
		}
		update_post_meta( $profile_id, 'itsm_linked_user_id', $user_id );
		update_user_meta( $user_id, 'itsm_employee_profile_id', $profile_id );
		update_user_meta( $user_id, 'itsm_application_id', $application_id );
		return (int) $user_id;
	}

	private function send_dashboard_access_email( $user_id, $application_id ) {
		$s = $this->get_settings();
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return;
		}
		$dashboard_url = $this->get_technician_portal_url();
		$url       = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
		$recipient = $this->bool_value( $s['test_mode'] ) && is_email( $s['test_recipient'] ) ? $s['test_recipient'] : $user->user_email;
		$subject   = ( $this->bool_value( $s['test_mode'] ) ? '[DEV TEST] ' : '' ) . 'Your ITSM Employee Dashboard Access';
		$message   = "Welcome to ITSM,\n\nYour technician account has been created.\n\nAccess Your Account:\n{$url}\n\nIf you need help, contact " . ( ! empty( $s['admin_email'] ) ? $s['admin_email'] : get_option( 'admin_email' ) ) . "\n\nPlease keep this email for reference.\n";
		if ( $this->bool_value( $s['test_mode'] ) ) {
			$message = 'DEV TEST: Original recipient would have been ' . $user->user_email . "\n\n" . $message;
		}
		wp_mail( $recipient, $subject, $message );
		update_post_meta( $application_id, 'itsm_dashboard_access_email_sent_at', current_time( 'mysql', true ) );
	}

	private function get_employee_profile_for_user( $user_id ) {
		return (int) get_user_meta( $user_id, 'itsm_employee_profile_id', true );
	}

	public function render_employee_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'itsm-employee-onboarding' ) . '</p>';
		}
		$user_id    = get_current_user_id();
		$profile_id = $this->get_employee_profile_for_user( $user_id );
		if ( ! $profile_id ) {
			return '<p>' . esc_html__( 'No employee profile is linked to this account.', 'itsm-employee-onboarding' ) . '</p>';
		}
		ob_start();
		?>
		<div class="itsm-employee-dashboard">
			<h2><?php esc_html_e( 'My Employee Dashboard', 'itsm-employee-onboarding' ); ?></h2>
			<h3><?php esc_html_e( 'Notifications', 'itsm-employee-onboarding' ); ?></h3>
			<p><?php echo esc_html( __( 'Onboarding approved. Documents received. Dashboard access active.', 'itsm-employee-onboarding' ) ); ?></p>
			<h3><?php esc_html_e( 'My Profile', 'itsm-employee-onboarding' ); ?></h3>
			<p><strong><?php esc_html_e( 'Name:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_name', true ) ); ?></p>
			<p><strong><?php esc_html_e( 'Email:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_email', true ) ); ?></p>
			<p><strong><?php esc_html_e( 'Phone:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_candidate_phone', true ) ); ?></p>
			<p><strong><?php esc_html_e( 'Job/Location:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_job_location', true ) ); ?></p>
			<p><strong><?php esc_html_e( 'Onboarding Status:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_onboarding_status', true ) ); ?></p>
			<p><strong><?php esc_html_e( 'Employment Type:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_employment_type', true ) ? get_post_meta( $profile_id, 'itsm_employment_type', true ) : __( 'contract', 'itsm-employee-onboarding' ) ); ?></p>
			<h3><?php esc_html_e( 'Tax Documentation Status', 'itsm-employee-onboarding' ); ?></h3>
			<p><strong><?php esc_html_e( 'Status:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_tax_documentation_status', true ) ); ?></p>
			<p><strong><?php esc_html_e( 'Completed:', 'itsm-employee-onboarding' ); ?></strong> <?php echo esc_html( get_post_meta( $profile_id, 'itsm_tax_documentation_completed_date', true ) ); ?></p>
			<p><a class="button" href="<?php echo esc_url( self::TAXBANDITS_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Complete W-9/W-8 Form', 'itsm-employee-onboarding' ); ?></a> <a class="button" href="<?php echo esc_url( self::IRS_W9_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download Official IRS W-9 PDF', 'itsm-employee-onboarding' ); ?></a></p>
			<h3><?php esc_html_e( 'My Uploaded Documents', 'itsm-employee-onboarding' ); ?></h3>
			<?php $this->render_document_links( (int) get_post_meta( $profile_id, 'itsm_hirezoot_application_id', true ) ); ?>
			<p><small><?php esc_html_e( 'Document details are reviewed internally. Uploaded files remain private.', 'itsm-employee-onboarding' ); ?></small></p>
			<h3><?php esc_html_e( 'Attendance Records', 'itsm-employee-onboarding' ); ?></h3>
			<?php $this->render_attendance_widget( $user_id, $profile_id ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function has_any_documents_for_application( $application_id ) {
		if ( ! $application_id ) {
			return false;
		}
		foreach ( [ 'driving_license_upload', 'government_id_upload', 'certification_upload', 'additional_document_upload' ] as $field ) {
			if ( (int) get_post_meta( $application_id, 'itsm_document_' . $field . '_attachment_id', true ) ) {
				return true;
			}
		}
		return false;
	}

	private function has_open_attendance_session( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'itsm_attendance';
		$open  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND status='clocked_in'", $user_id ) );
		return (int) $open > 0;
	}

	private function render_attendance_widget( $user_id, $profile_id, $attendance_data = null, $section = 'attendance', $attendance_period = 'today', $attendance_from = '', $attendance_to = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'itsm_attendance';
		if ( isset( $_GET['itsm_attendance_notice'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['itsm_attendance_notice'] ) );
			$message = isset( $_GET['itsm_attendance_message'] ) ? wp_unslash( $_GET['itsm_attendance_message'] ) : '';
			if ( $message ) {
				echo '<div class="notice notice-' . esc_attr( 'error' === $notice ? 'error' : 'success' ) . '" style="margin:0 0 16px;padding:12px 14px;border-left:4px solid ' . esc_attr( 'error' === $notice ? '#dc3232' : '#46b450' ) . ';background:#fff;">' . esc_html( sanitize_text_field( $message ) ) . '</div>';
			}
		}
		$period = $attendance_period;
		$from   = $attendance_from;
		$to     = $attendance_to;
		$data   = is_array( $attendance_data ) ? $attendance_data : $this->get_attendance_dashboard_data( $user_id, $period, $from, $to );
		if ( ! in_array( $period, [ 'today', 'week', 'month', 'range' ], true ) ) {
			$period = 'today';
		}
		$latest_row       = ! empty( $data['rows'] ) ? $data['rows'][0] : null;
		$latest_clock_in  = isset( $latest_row['clock_in_label'] ) ? $latest_row['clock_in_label'] : __( 'N/A', 'itsm-employee-onboarding' );
		$latest_clock_out = isset( $latest_row['clock_out_label'] ) ? $latest_row['clock_out_label'] : __( 'N/A', 'itsm-employee-onboarding' );
		$live_duration_id = 'itsm-live-duration-' . absint( $user_id );
		echo '<div class="itsm-tech-attendance-panel">';
		echo '<div class="itsm-tech-attendance-summary">';
		echo '<div class="itsm-tech-metric"><div class="label">' . esc_html__( 'Current Status', 'itsm-employee-onboarding' ) . '</div><div class="value">' . esc_html( $data['current_status_label'] ) . '</div></div>';
		echo '<div class="itsm-tech-metric" style="margin-top:12px;"><div class="label">' . esc_html__( 'Latest Clock In', 'itsm-employee-onboarding' ) . '</div><div class="value">' . esc_html( $latest_clock_in ) . '</div></div>';
		echo '<div class="itsm-tech-metric" style="margin-top:12px;"><div class="label">' . esc_html__( 'Latest Clock Out', 'itsm-employee-onboarding' ) . '</div><div class="value">' . esc_html( $latest_clock_out ) . '</div></div>';
		echo '<div class="itsm-tech-metric" style="margin-top:12px;"><div class="label">' . esc_html__( 'Today', 'itsm-employee-onboarding' ) . '</div><div class="value">' . esc_html( $data['today_hours_label'] ) . '</div><div id="' . esc_attr( $live_duration_id ) . '" data-open="' . esc_attr( $data['open_row'] ? '1' : '0' ) . '" data-clock-in="' . esc_attr( $data['open_row'] ? $data['open_row']->clock_in_time : '' ) . '" style="margin-top:8px;color:#5f6b7a;">' . esc_html( $data['current_session_label'] ) . '</div></div>';
		echo '<div class="itsm-tech-metric" style="margin-top:12px;"><div class="label">' . esc_html__( 'This Week', 'itsm-employee-onboarding' ) . '</div><div class="value">' . esc_html( $data['week_hours_label'] ) . '</div></div>';
		echo '<div class="itsm-tech-metric" style="margin-top:12px;"><div class="label">' . esc_html__( 'This Month', 'itsm-employee-onboarding' ) . '</div><div class="value">' . esc_html( $data['month_hours_label'] ) . '</div></div>';
		echo '</div>';
		echo '<div class="itsm-tech-attendance-actions">';
		echo '<div class="itsm-tech-actions" style="margin-bottom:12px;">';
		if ( $data['open_row'] ) {
			$this->attendance_button( 'clock_out', __( 'Clock Out', 'itsm-employee-onboarding' ) );
		} else {
			$this->attendance_button( 'clock_in', __( 'Clock In', 'itsm-employee-onboarding' ) );
		}
		echo '</div>';
		echo '<div class="itsm-tech-filterbar">';
		echo '<form id="itsm-attendance-filters" method="get" action="' . esc_url( $this->get_technician_portal_url() ) . '" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">';
		echo '<input type="hidden" name="section" value="attendance" />';
		echo '<label>' . esc_html__( 'View', 'itsm-employee-onboarding' ) . '<select name="period"><option value="today"' . selected( $period, 'today', false ) . '>' . esc_html__( 'Today', 'itsm-employee-onboarding' ) . '</option><option value="week"' . selected( $period, 'week', false ) . '>' . esc_html__( 'Week', 'itsm-employee-onboarding' ) . '</option><option value="month"' . selected( $period, 'month', false ) . '>' . esc_html__( 'Month', 'itsm-employee-onboarding' ) . '</option><option value="range"' . selected( $period, 'range', false ) . '>' . esc_html__( 'Date Range', 'itsm-employee-onboarding' ) . '</option></select></label>';
		echo '<label>' . esc_html__( 'From', 'itsm-employee-onboarding' ) . '<input type="date" name="from" value="' . esc_attr( $from ) . '" /></label>';
		echo '<label>' . esc_html__( 'To', 'itsm-employee-onboarding' ) . '<input type="date" name="to" value="' . esc_attr( $to ) . '" /></label>';
		echo '<button class="button button-secondary" type="submit">' . esc_html__( 'Apply Filter', 'itsm-employee-onboarding' ) . '</button>';
		echo '</form>';
		echo '</div>';
		echo '<div class="itsm-tech-metric" style="margin-top:14px;"><div class="label">' . esc_html__( 'Attendance History', 'itsm-employee-onboarding' ) . '</div>';
		if ( empty( $data['rows'] ) ) {
			echo '<p style="margin:10px 0 0;">' . esc_html__( 'No attendance records found for this period.', 'itsm-employee-onboarding' ) . '</p>';
		} else {
			echo '<div class="itsm-tech-table-wrap"><table class="itsm-tech-table"><thead><tr><th>' . esc_html__( 'Date', 'itsm-employee-onboarding' ) . '</th><th>' . esc_html__( 'Clock In', 'itsm-employee-onboarding' ) . '</th><th>' . esc_html__( 'Clock Out', 'itsm-employee-onboarding' ) . '</th><th>' . esc_html__( 'Hours', 'itsm-employee-onboarding' ) . '</th><th>' . esc_html__( 'Status', 'itsm-employee-onboarding' ) . '</th></tr></thead><tbody>';
			foreach ( $data['rows'] as $row ) {
				echo '<tr><td>' . esc_html( $row['date_label'] ) . '</td><td>' . esc_html( $row['clock_in_label'] ) . '</td><td>' . esc_html( $row['clock_out_label'] ) . '</td><td>' . esc_html( $row['duration_label'] ) . '</td><td>' . esc_html( $row['status_label'] ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
		echo '</div>';
		if ( $data['open_row'] ) {
			echo '<script>(function(){var el=document.getElementById(' . wp_json_encode( $live_duration_id ) . ');if(!el){return;}function tick(){var open=el.getAttribute("data-open");var clockIn=el.getAttribute("data-clock-in");if(open!=="1"||!clockIn){return;}var start=new Date(clockIn.replace(" ","T")+"Z").getTime();if(isNaN(start)){return;}var diff=Math.max(0,Math.floor((Date.now()-start)/1000));var h=Math.floor(diff/3600);var m=Math.floor((diff%3600)/60);var s=diff%60;el.textContent="' . esc_js( __( 'Current session:', 'itsm-employee-onboarding' ) ) . ' "+h+"h "+m+"m "+s+"s";}tick();setInterval(tick,1000);})();</script>';
		}
		echo '</div>';
	}

	private function attendance_button( $action, $label ) {
		$action_url = $this->get_portal_attendance_action_url( $action );
		echo '<a class="button button-primary itsm-tech-attendance-link" href="' . esc_url( $action_url ) . '">' . esc_html( $label ) . '</a>';
	}

	private function get_attendance_dashboard_data( $user_id, $period = 'today', $from = '', $to = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'itsm_attendance';
		$tz    = wp_timezone();
		$now   = new DateTimeImmutable( 'now', $tz );
		$start = null;
		$end   = null;
		$from  = $this->normalize_attendance_date( $from );
		$to    = $this->normalize_attendance_date( $to );
		switch ( $period ) {
			case 'week':
				$start = $now->modify( 'monday this week' )->setTime( 0, 0, 0 );
				$end   = $now;
				break;
			case 'month':
				$start = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$end   = $now;
				break;
			case 'range':
				if ( $from ) {
					$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $from . ' 00:00:00', $tz );
				}
				if ( $to ) {
					$end = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $to . ' 23:59:59', $tz );
				}
				if ( ! $start ) {
					$start = $now->modify( 'monday this week' )->setTime( 0, 0, 0 );
				}
				if ( ! $end ) {
					$end = $now;
				}
				break;
			case 'today':
			default:
				$start = $now->setTime( 0, 0, 0 );
				$end   = $now;
				break;
		}
		$rows = $this->get_attendance_rows_for_user( $user_id, $start, $end );
		$open_row = null;
		foreach ( $rows as $row ) {
			if ( 'clocked_in' === $row->status ) {
				$open_row = $row;
				break;
			}
		}
		$today_total = $this->get_attendance_seconds_for_range( $rows, 'today' );
		$week_total  = $this->get_attendance_seconds_for_range( $rows, 'week' );
		$month_total = $this->get_attendance_seconds_for_range( $rows, 'month' );
		$current_session = $open_row ? $this->format_attendance_duration( $this->get_attendance_row_duration_seconds( $open_row, true ) ) : __( 'Not currently clocked in.', 'itsm-employee-onboarding' );
		$display_rows = [];
		foreach ( $rows as $row ) {
			$display_rows[] = [
				'date_label'      => $this->format_attendance_date( $row ),
				'clock_in_label'   => $this->format_attendance_datetime( $row->clock_in_time ),
				'clock_out_label'  => $this->format_attendance_datetime( $row->clock_out_time ),
				'duration_label'   => $this->format_attendance_duration( $this->get_attendance_row_duration_seconds( $row, false ) ),
				'status_label'     => $this->format_attendance_status( $row->status ),
			];
		}
		return [
			'rows'                  => $display_rows,
			'open_row'              => $open_row,
			'current_status_label'   => $open_row ? __( 'Clocked In', 'itsm-employee-onboarding' ) : __( 'Clocked Out', 'itsm-employee-onboarding' ),
			'today_hours_label'      => $this->format_attendance_duration( $today_total ),
			'week_hours_label'       => $this->format_attendance_duration( $week_total ),
			'month_hours_label'      => $this->format_attendance_duration( $month_total ),
			'current_session_label'  => $current_session,
		];
	}

	private function get_attendance_rows_for_user( $user_id, DateTimeImmutable $start, DateTimeImmutable $end ) {
		global $wpdb;
		$table = $wpdb->prefix . 'itsm_attendance';
		$start_mysql = $start->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
		$end_mysql   = $end->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id=%d AND created_at BETWEEN %s AND %s ORDER BY id DESC",
				$user_id,
				$start_mysql,
				$end_mysql
			)
		);
	}

	private function get_attendance_seconds_for_range( array $rows, $period ) {
		$seconds = 0;
		$tz      = wp_timezone();
		$now     = new DateTimeImmutable( 'now', $tz );
		foreach ( $rows as $row ) {
			$in  = $this->parse_attendance_datetime( $row->clock_in_time );
			$out = $this->parse_attendance_datetime( $row->clock_out_time );
			if ( ! $in ) {
				continue;
			}
			if ( ! $out && 'clocked_in' === $row->status ) {
				$out = $now;
			}
			if ( ! $out ) {
				continue;
			}
			$seconds += max( 0, $out->getTimestamp() - $in->getTimestamp() );
		}
		return $seconds;
	}

	private function get_attendance_row_duration_seconds( $row, $include_open = false ) {
		$in  = $this->parse_attendance_datetime( $row->clock_in_time );
		$out = $this->parse_attendance_datetime( $row->clock_out_time );
		if ( ! $in ) {
			return 0;
		}
		if ( ! $out && $include_open && 'clocked_in' === $row->status ) {
			$out = new DateTimeImmutable( 'now', wp_timezone() );
		}
		if ( ! $out ) {
			return 0;
		}
		return max( 0, $out->getTimestamp() - $in->getTimestamp() );
	}

	private function parse_attendance_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}
		try {
			return new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	private function normalize_attendance_date( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		return $value;
	}

	private function format_attendance_date( $row ) {
		$dt = $this->parse_attendance_datetime( $row->created_at );
		if ( ! $dt ) {
			$dt = $this->parse_attendance_datetime( $row->clock_in_time );
		}
		return $dt ? wp_date( get_option( 'date_format' ), $dt->getTimestamp(), wp_timezone() ) : __( 'N/A', 'itsm-employee-onboarding' );
	}

	private function format_attendance_datetime( $value ) {
		$dt = $this->parse_attendance_datetime( $value );
		return $dt ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dt->getTimestamp(), wp_timezone() ) : __( 'N/A', 'itsm-employee-onboarding' );
	}

	private function format_attendance_duration( $seconds ) {
		$seconds = max( 0, absint( $seconds ) );
		$hours   = floor( $seconds / HOUR_IN_SECONDS );
		$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		if ( 0 === $hours && 0 === $minutes ) {
			return __( '0m', 'itsm-employee-onboarding' );
		}
		$output = [];
		if ( $hours ) {
			$output[] = $hours . 'h';
		}
		if ( $minutes || ! $hours ) {
			$output[] = $minutes . 'm';
		}
		return implode( ' ', $output );
	}

	private function format_attendance_status( $status ) {
		$status = sanitize_key( (string) $status );
		if ( 'clocked_in' === $status ) {
			return __( 'Clocked In', 'itsm-employee-onboarding' );
		}
		if ( 'clocked_out' === $status ) {
			return __( 'Clocked Out', 'itsm-employee-onboarding' );
		}
		return $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'Unknown', 'itsm-employee-onboarding' );
	}

	private function get_portal_attendance_action_url( $action ) {
		$action = sanitize_key( $action );
		$url    = add_query_arg(
			[
				'section'                => 'attendance',
				'itsm_attendance_action' => $action,
			],
			$this->get_technician_portal_url()
		);
		return wp_nonce_url( $url, 'itsm_portal_attendance_' . $action . '_' . get_current_user_id() );
	}

	private function get_portal_attendance_return_url( $redirect ) {
		$redirect = esc_url_raw( $redirect );
		if ( ! $redirect || false === strpos( $redirect, 'technician-portal' ) ) {
			$redirect = $this->get_technician_portal_url();
		}
		return $redirect;
	}

	private function process_attendance_action_request( $action, $redirect ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'itsm_attendance_login', __( 'Please log in first.', 'itsm-employee-onboarding' ) );
		}
		$user = wp_get_current_user();
		if ( ! $user || ! in_array( self::EMPLOYEE_ROLE, (array) $user->roles, true ) ) {
			return new WP_Error( 'itsm_attendance_role', __( 'Attendance is only available to approved technicians.', 'itsm-employee-onboarding' ) );
		}
		$action = sanitize_key( $action );
		if ( ! in_array( $action, [ 'clock_in', 'clock_out' ], true ) ) {
			return new WP_Error( 'itsm_attendance_action', __( 'Invalid attendance request.', 'itsm-employee-onboarding' ) );
		}
		global $wpdb;
		$table      = $wpdb->prefix . 'itsm_attendance';
		$user_id    = get_current_user_id();
		$profile_id = $this->get_employee_profile_for_user( $user_id );
		$now        = current_time( 'mysql', true );
		$redirect   = $this->get_portal_attendance_return_url( $redirect );
		if ( ! $profile_id ) {
			return new WP_Error( 'itsm_attendance_profile', __( 'No employee profile is linked to this account.', 'itsm-employee-onboarding' ) );
		}
		$notice  = 'success';
		$message = '';
		if ( 'clock_in' === $action ) {
			$open = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d AND status='clocked_in' LIMIT 1", $user_id ) );
			if ( ! $open ) {
				$inserted = $wpdb->insert(
					$table,
					[
						'user_id'             => $user_id,
						'employee_profile_id' => $profile_id,
						'clock_in_time'       => $now,
						'timezone'            => wp_timezone_string(),
						'ip_address'          => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
						'device_info'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
						'status'              => 'clocked_in',
						'created_at'          => $now,
						'updated_at'          => $now,
					]
				);
				if ( false === $inserted ) {
					return new WP_Error( 'itsm_attendance_clock_in_failed', __( 'Unable to clock in right now. Please try again.', 'itsm-employee-onboarding' ) );
				}
				$message = __( 'Clock in recorded.', 'itsm-employee-onboarding' );
			} else {
				$notice  = 'error';
				$message = __( 'You already have an open clock-in session.', 'itsm-employee-onboarding' );
			}
		} else {
			$open = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id=%d AND status='clocked_in' ORDER BY id DESC LIMIT 1", $user_id ) );
			if ( $open ) {
				$updated = $wpdb->update( $table, [ 'clock_out_time' => $now, 'status' => 'clocked_out', 'updated_at' => $now ], [ 'id' => $open->id ] );
				if ( false === $updated ) {
					return new WP_Error( 'itsm_attendance_clock_out_failed', __( 'Unable to clock out right now. Please try again.', 'itsm-employee-onboarding' ) );
				}
				$message = __( 'Clock out recorded.', 'itsm-employee-onboarding' );
			} else {
				$notice  = 'error';
				$message = __( 'No open clock-in session was found.', 'itsm-employee-onboarding' );
			}
		}
		return add_query_arg(
			[
				'section'                 => 'attendance',
				'itsm_attendance_notice'  => $notice,
				'itsm_attendance_message' => rawurlencode( $message ),
			],
			$redirect
		);
	}

	public function handle_attendance_action() {
		$result = $this->process_attendance_action_request(
			isset( $_POST['attendance_action'] ) ? sanitize_key( wp_unslash( $_POST['attendance_action'] ) ) : '',
			isset( $_POST['itsm_return_url'] ) ? esc_url_raw( wp_unslash( $_POST['itsm_return_url'] ) ) : $this->get_technician_portal_url()
		);
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( [ 'section' => 'attendance', 'itsm_attendance_notice' => 'error', 'itsm_attendance_message' => rawurlencode( $result->get_error_message() ) ], $this->get_technician_portal_url() ) );
			exit;
		}
		wp_safe_redirect( $result );
		exit;
	}

	private function maybe_handle_portal_attendance_action() {
		$action = isset( $_GET['itsm_attendance_action'] ) ? sanitize_key( wp_unslash( $_GET['itsm_attendance_action'] ) ) : '';
		if ( ! in_array( $action, [ 'clock_in', 'clock_out' ], true ) ) {
			return true;
		}
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'itsm_attendance_login', __( 'Please log in first.', 'itsm-employee-onboarding' ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'itsm_portal_attendance_' . $action . '_' . get_current_user_id() ) ) {
			return new WP_Error( 'itsm_attendance_invalid_nonce', __( 'Invalid attendance request.', 'itsm-employee-onboarding' ) );
		}
		return $this->process_attendance_action_request( $action, $this->get_technician_portal_url() );
	}

	public function handle_portal_additional_document_upload() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in.', 'itsm-employee-onboarding' ) );
		}
		$application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
		if ( ! $application_id || ! wp_verify_nonce( isset( $_POST['itsm_portal_additional_document_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['itsm_portal_additional_document_nonce'] ) ) : '', 'itsm_portal_additional_document_' . $application_id ) ) {
			wp_die( esc_html__( 'Invalid upload request.', 'itsm-employee-onboarding' ) );
		}
		$user_id    = get_current_user_id();
		$profile_id = $this->get_employee_profile_for_user( $user_id );
		if ( ! $profile_id || (int) get_post_meta( $profile_id, 'itsm_hirezoot_application_id', true ) !== $application_id ) {
			wp_die( esc_html__( 'You do not have permission to upload to this record.', 'itsm-employee-onboarding' ) );
		}
		$attachment_id = $this->handle_private_upload( 'additional_document_upload', $application_id );
		if ( $attachment_id ) {
			$existing = (array) get_post_meta( $application_id, 'additional_document_ids', true );
			$existing[] = (int) $attachment_id;
			update_post_meta( $application_id, 'additional_document_ids', array_values( array_unique( array_filter( array_map( 'intval', $existing ) ) ) ) );
			update_post_meta( $application_id, 'itsm_document_additional_document_upload_attachment_id', (int) $attachment_id );
			update_post_meta( $application_id, 'itsm_document_additional_document_upload_status', 'approved' );
			$this->record_document_extraction( $application_id, 'additional_document_upload', $attachment_id );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		wp_safe_redirect( $this->get_technician_portal_redirect_url( wp_get_referer() ? wp_get_referer() : '' ) );
		exit;
	}

	public function render_attendance_admin() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'itsm_attendance';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100" );
		echo '<div class="wrap"><h1>' . esc_html__( 'Attendance', 'itsm-employee-onboarding' ) . '</h1><table class="widefat striped"><thead><tr><th>Employee</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>IP</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$user = get_user_by( 'id', $row->user_id );
			echo '<tr><td>' . esc_html( $user ? $user->display_name : $row->user_id ) . '</td><td>' . esc_html( $row->clock_in_time ) . '</td><td>' . esc_html( $row->clock_out_time ) . '</td><td>' . esc_html( $row->status ) . '</td><td>' . esc_html( $row->ip_address ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function block_employee_admin() {
		if ( ! is_admin() || wp_doing_ajax() || current_user_can( 'manage_options' ) ) {
			return;
		}
		$user = wp_get_current_user();
		if ( $user && in_array( self::EMPLOYEE_ROLE, (array) $user->roles, true ) ) {
			wp_safe_redirect( $this->get_technician_portal_url() );
			exit;
		}
	}
}

register_activation_hook( __FILE__, [ 'ITSM_Employee_Onboarding', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ITSM_Employee_Onboarding', 'deactivate' ] );
ITSM_Employee_Onboarding::instance();
