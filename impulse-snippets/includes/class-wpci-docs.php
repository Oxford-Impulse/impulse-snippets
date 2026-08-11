<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documentation page: a plain-language, step-by-step explanation of every
 * feature. The Dashboard's "What you can do" grid is the short version of
 * this; this page is the detailed one.
 */
class Wpci_Docs {

	const PAGE_SLUG = 'wpci-docs';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		$hook = add_submenu_page(
			Wpci_Admin_Menu::DASHBOARD_SLUG,
			__( 'Documentation', 'impulse-snippets' ),
			__( 'Documentation', 'impulse-snippets' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'enqueue_assets' ) );
		}
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'wpci-admin', WPCI_PLUGIN_URL . 'assets/css/admin.css', array(), WPCI_VERSION );
	}

	public function render_page() {
		?>
		<div class="wrap wpci-docs">
			<h1><?php esc_html_e( 'Documentation — How It Works', 'impulse-snippets' ); ?></h1>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '1. Creating your first snippet', 'impulse-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Go to Impulse Snippets → Add New. Give it a name (just for your own reference), paste your code, choose where it should go (Head, Body, or Footer), then click Publish. That\'s it — the code is now live on your site.', 'impulse-snippets' ); ?></p>
			</div>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '2. Head, Body, and Footer — what\'s the difference?', 'impulse-snippets' ); ?></h2>
				<ul style="list-style:disc;padding-left:20px;">
					<li><strong><?php esc_html_e( 'Head:', 'impulse-snippets' ); ?></strong> <?php esc_html_e( 'loads before the page content, in the invisible <head> section. Best for tracking codes, fonts, and meta tags.', 'impulse-snippets' ); ?></li>
					<li><strong><?php esc_html_e( 'Body:', 'impulse-snippets' ); ?></strong> <?php esc_html_e( 'loads immediately after the page starts rendering. Some tools (like Google Tag Manager) specifically require this placement.', 'impulse-snippets' ); ?></li>
					<li><strong><?php esc_html_e( 'Footer:', 'impulse-snippets' ); ?></strong> <?php esc_html_e( 'loads near the very end of the page, after everything else. Good for chat widgets and anything that shouldn\'t slow down the initial page load.', 'impulse-snippets' ); ?></li>
				</ul>
			</div>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '3. Code type & Auto-detect', 'impulse-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Tell the plugin what kind of code you pasted: JavaScript, CSS, HTML/mixed, or Auto-detect. If you choose JavaScript or CSS and your pasted code doesn\'t already have <script> or <style> tags around it, the plugin adds them for you automatically. Choose HTML/mixed if your snippet already includes its own tags (most tracking/embed codes do) — nothing gets added or changed in that case.', 'impulse-snippets' ); ?></p>
			</div>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '4. Linking to an external file', 'impulse-snippets' ); ?></h2>
				<p><?php esc_html_e( 'On the snippet editor, choose "Link to an external file" instead of "Paste code" if you want to load a script or stylesheet that\'s already hosted somewhere (like a library file), rather than pasting its contents directly.', 'impulse-snippets' ); ?></p>
			</div>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '5. Display Conditions — controlling where a snippet appears', 'impulse-snippets' ); ?></h2>
				<p><?php esc_html_e( 'By default, a snippet appears on every page of your site. In the Display Conditions box, you can instead restrict it to:', 'impulse-snippets' ); ?></p>
				<ul style="list-style:disc;padding-left:20px;">
					<li><?php esc_html_e( 'Specific pages or posts — tick the ones you want, use the search box to find one quickly, or paste its link directly.', 'impulse-snippets' ); ?></li>
					<li><?php esc_html_e( 'Post types — e.g. only on Pages, or only on Posts.', 'impulse-snippets' ); ?></li>
					<li><?php esc_html_e( 'Categories — only on blog posts in the categories you pick.', 'impulse-snippets' ); ?></li>
				</ul>
			</div>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '6. One-click Integrations', 'impulse-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Under Impulse Snippets → Integrations, paste your Google Analytics 4 Measurement ID, Google Tag Manager Container ID, Meta Pixel ID, or Google Ads ID and the correct snippet(s) are created for you automatically — no code needed. If you ever change the ID and reconnect, it updates the same snippet instead of creating a duplicate.', 'impulse-snippets' ); ?></p>
				<p><?php esc_html_e( 'Google Ads conversion tracking: after connecting your AW- ID, add a conversion action by pasting its conversion label (Google Ads shows it when you create the conversion action). You can optionally set a fixed value and currency. The plugin creates a ready-made snippet and takes you to its edit screen — search for the page that counts as the conversion (usually your thank-you page), add it, and Publish. Until you do, the snippet stays a harmless draft.', 'impulse-snippets' ); ?></p>
				<p><?php esc_html_e( 'Enhanced conversions (for purchases and leads): these work with the tag this plugin installs — no extra code. Just switch them on inside Google Ads under Goals → Conversions → Settings → Enhanced conversions.', 'impulse-snippets' ); ?></p>
				<p><?php esc_html_e( 'Consent Mode V2: since March 2024, Google requires sites with EU/UK visitors to tell its tags what the visitor has consented to, before the tags run. The Consent Mode card creates that signal for you — choose whether tracking starts as "denied" for EU/UK visitors only (recommended) or for everyone. Important: this is the signal, not the cookie banner itself. Pair it with a consent banner plugin (Complianz, Cookiebot, CookieYes, or any Google-certified one) — the banner flips the signal to "granted" when the visitor accepts. Technical note: Google calls this "Advanced" consent mode (tags load but respect consent); if you want tags fully blocked until consent ("Basic" mode), your banner plugin does that, not this snippet.', 'impulse-snippets' ); ?></p>
				<p><?php esc_html_e( 'Meta Pixel consent: the Meta Pixel card has its own "Wait for cookie consent before tracking" checkbox. Meta\'s system is simpler and stricter than Google\'s — there is no per-region option and no estimated data: while waiting for consent the pixel collects nothing at all, worldwide, until your consent banner grants it. Only enable this if you actually have a consent banner plugin; without one, the pixel would simply never track anyone.', 'impulse-snippets' ); ?></p>
				<p><?php esc_html_e( 'WooCommerce purchase tracking: if WooCommerce is installed, the Conversion tracking panel gains a purchase-tracking section. Paste the conversion label of your "Purchase" conversion action and each order gets reported on the thank-you page with its real total, currency, and order number — no page picking needed, and repeat visits to the page are deduplicated by Google automatically. Optionally enable enhanced conversions to also send a hashed (unreadable) version of the billing email, which improves how many conversions Google can match to ad clicks. The same hashed-email option exists on regular conversion actions for logged-in visitors.', 'impulse-snippets' ); ?></p>
				<p><?php esc_html_e( 'Form lead tracking: if Contact Form 7 or WPForms is installed, the Conversion tracking panel gains a form-tracking section. Pick a form and a lead is counted the moment a visitor successfully submits it — a free GA4 "generate_lead" event always, plus a Google Ads conversion if you add a conversion label. No thank-you page is needed and failed submissions never count. Optionally enable the hashed-email checkbox: the email the visitor typed is scrambled in their own browser before being sent (the readable address never goes to Google), which lets Google match leads to ad clicks even for logged-out visitors.', 'impulse-snippets' ); ?></p>
			</div>

			<div class="postbox wpci-integration-card" style="max-width:800px;">
				<h2><?php esc_html_e( '7. Turning a snippet on or off', 'impulse-snippets' ); ?></h2>
				<p><?php esc_html_e( 'Every snippet is just a Published (active) or Draft (inactive) item, exactly like a WordPress post. Switch it to Draft to disable it instantly without losing the code, and switch it back to Published to bring it back.', 'impulse-snippets' ); ?></p>
			</div>

			<div class="notice notice-warning inline" style="max-width:800px;">
				<p><strong><?php esc_html_e( 'A word of caution:', 'impulse-snippets' ); ?></strong> <?php esc_html_e( 'code you add here runs exactly as written on your live site, with no safety checks. Only paste code from sources you trust, and double-check a snippet on a staging site first if you\'re unsure.', 'impulse-snippets' ); ?></p>
			</div>
		</div>
		<?php
	}
}
