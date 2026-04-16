<?php
/**
 * Plugin Name: QOTD (Zitat des Tages)
 * Description: CPT für Zitate + Ausgabe als Zitat des Tages (AJAX/REST, cache-sicher).
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Dieter Geiling
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qotd
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class QOTD_Plugin {
	private const VERSION = '1.1.0';
	private const CPT = 'qotd_quote';

	// Plaintext-Metafelder
	private const META_TEXT = '_qotd_text';
	private const META_AUTHOR = '_qotd_author';
	private const META_EXTRA = '_qotd_extra';

	private const REST_NAMESPACE = 'qotd/v1';
	private const REST_ROUTE = '/today';
	private const SHORTCODE = 'qotd';
	private const NONCE_ACTION = 'qotd_save_meta';
	private const NONCE_NAME = '_qotd_nonce';

	public static function init(): void {
		$instance = new self();

		add_action('init', [$instance, 'register_cpt']);
		add_action('add_meta_boxes', [$instance, 'register_meta_boxes']);
		add_action('save_post_' . self::CPT, [$instance, 'save_meta'], 10, 2);

		// Titel automatisch füllen, wenn leer (aus dem Zitat-Text)
		add_filter('wp_insert_post_data', [$instance, 'autofill_title'], 10, 2);

		add_action('rest_api_init', [$instance, 'register_rest']);
		add_shortcode(self::SHORTCODE, [$instance, 'shortcode']);

		add_action('wp_enqueue_scripts', [$instance, 'register_assets']);
		add_action('init', [$instance, 'register_block']);

		add_filter('manage_' . self::CPT . '_posts_columns', [$instance, 'admin_columns']);
		add_action('manage_' . self::CPT . '_posts_custom_column', [$instance, 'admin_column_content'], 10, 2);

		add_action('admin_menu', [$instance, 'register_admin_menu']);
		add_action('admin_enqueue_scripts', [$instance, 'enqueue_admin_export_script']);
		add_action('admin_post_qotd_import', [$instance, 'handle_import']);
	}

	public function register_cpt(): void {
		$labels = [
			'name'               => __('Zitate', 'qotd'),
			'singular_name'      => __('Zitat', 'qotd'),
			'add_new'            => __('Neu hinzufügen', 'qotd'),
			'add_new_item'       => __('Neues Zitat hinzufügen', 'qotd'),
			'edit_item'          => __('Zitat bearbeiten', 'qotd'),
			'new_item'           => __('Neues Zitat', 'qotd'),
			'view_item'          => __('Zitat ansehen', 'qotd'),
			'search_items'       => __('Zitate suchen', 'qotd'),
			'not_found'          => __('Keine Zitate gefunden', 'qotd'),
			'not_found_in_trash' => __('Keine Zitate im Papierkorb', 'qotd'),
		];

		register_post_type(self::CPT, [
			'labels' => $labels,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_position' => 25,
			'menu_icon' => 'dashicons-format-quote',

			// Wichtig: KEIN Editor. Nur Titel + Metabox mit PlainText.
			'supports' => ['title'],

			'has_archive' => false,
			'rewrite' => false,
			'show_in_rest' => false,
			'capability_type' => 'post',
		]);
	}

	/**
	 * Wenn kein Titel gesetzt ist, generieren wir ihn aus dem Zitat (PlainText).
	 * So verschwindet "Automatisch gespeicherter Entwurf" aus der Liste.
	 */
	public function admin_columns(array $columns): array {
		$new = [];
		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ($key === 'title') {
				$new['qotd_author'] = __('Autor', 'qotd');
				$new['qotd_extra']  = __('Zusatz', 'qotd');
			}
		}
		return $new;
	}

	public function admin_column_content(string $column, int $post_id): void {
		if ($column === 'qotd_author') {
			$value = (string) get_post_meta($post_id, self::META_AUTHOR, true);
			echo esc_html($value !== '' ? $value : '—');
		} elseif ($column === 'qotd_extra') {
			$value = (string) get_post_meta($post_id, self::META_EXTRA, true);
			echo esc_html($value !== '' ? $value : '—');
		}
	}

	public function autofill_title(array $data, array $postarr): array {
		if (($data['post_type'] ?? '') !== self::CPT) {
			return $data;
		}

		$title = isset($data['post_title']) ? trim((string) $data['post_title']) : '';
		if ($title !== '') {
			return $data;
		}

		// Metabox-Feld kommt beim Speichern via POST.
		$quote = '';
		if (isset($_POST['qotd_text']) && is_string($_POST['qotd_text'])) {
			$quote = sanitize_textarea_field($_POST['qotd_text']);
		}

		$quote = trim((string) preg_replace('/\s+/', ' ', $quote));
		if ($quote === '') {
			$data['post_title'] = __('Zitat', 'qotd') . ' ' . wp_date('Y-m-d H:i');
			return $data;
		}

		$max = 80;
		$short = function_exists('mb_substr') ? mb_substr($quote, 0, $max) : substr($quote, 0, $max);
		$len = function_exists('mb_strlen') ? mb_strlen($quote) : strlen($quote);
		if ($len > $max) {
			$short .= '…';
		}

		$data['post_title'] = $short;
		return $data;
	}

	public function register_meta_boxes(): void {
		add_meta_box(
			'qotd_meta',
			__('Zitat-Details', 'qotd'),
			[$this, 'render_meta_box'],
			self::CPT,
			'normal',
			'default'
		);
	}

	public function render_meta_box(\WP_Post $post): void {
		$text = (string) get_post_meta($post->ID, self::META_TEXT, true);
		$author = (string) get_post_meta($post->ID, self::META_AUTHOR, true);
		$extra = (string) get_post_meta($post->ID, self::META_EXTRA, true);

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
		?>
		<p>
			<label for="qotd_text"><strong><?php echo esc_html(__('Zitat', 'qotd')); ?></strong> <?php echo esc_html(__('(nur Text, keine Formatierung)', 'qotd')); ?></label><br>
			<textarea id="qotd_text" name="qotd_text" rows="6" style="width: 100%;" spellcheck="true"><?php echo esc_textarea($text); ?></textarea>
		</p>
		<p>
			<label for="qotd_author"><strong><?php echo esc_html(__('Autor', 'qotd')); ?></strong></label><br>
			<input type="text" id="qotd_author" name="qotd_author" value="<?php echo esc_attr($author); ?>" style="width: 100%;" autocomplete="off">
		</p>
		<p>
			<label for="qotd_extra"><strong><?php echo esc_html(__('Zusatz', 'qotd')); ?></strong> <?php echo esc_html(__('(optional)', 'qotd')); ?></label><br>
			<input type="text" id="qotd_extra" name="qotd_extra" value="<?php echo esc_attr($extra); ?>" style="width: 100%;" autocomplete="off">
		</p>
		<p style="color:#666;">
			<?php echo esc_html(__('Hinweis: Es wird ausschließlich PlainText gespeichert und ausgegeben (keine Links, kein HTML).', 'qotd')); ?>
		</p>
		<?php
	}

	public function save_meta(int $post_id, \WP_Post $post): void {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (wp_is_post_revision($post_id)) {
			return;
		}

		$nonce = $_POST[self::NONCE_NAME] ?? '';
		if (!is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$text = isset($_POST['qotd_text']) ? sanitize_textarea_field((string) $_POST['qotd_text']) : '';
		$author = isset($_POST['qotd_author']) ? sanitize_text_field((string) $_POST['qotd_author']) : '';
		$extra = isset($_POST['qotd_extra']) ? sanitize_text_field((string) $_POST['qotd_extra']) : '';

		update_post_meta($post_id, self::META_TEXT, $text);
		update_post_meta($post_id, self::META_AUTHOR, $author);
		update_post_meta($post_id, self::META_EXTRA, $extra);

		delete_transient('qotd_quote_ids');
	}

	public function register_rest(): void {
		register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [$this, 'rest_today'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::REST_NAMESPACE, '/export', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [$this, 'rest_export'],
			'permission_callback' => fn() => current_user_can('manage_options'),
		]);
	}

	public function rest_export(\WP_REST_Request $request): \WP_REST_Response {
		$quotes = [];

		foreach ($this->get_published_quote_ids() as $post_id) {
			$text = (string) get_post_meta($post_id, self::META_TEXT, true);
			if ($text === '') {
				continue;
			}

			$quotes[] = [
				'text'   => $text,
				'author' => (string) get_post_meta($post_id, self::META_AUTHOR, true),
				'extra'  => (string) get_post_meta($post_id, self::META_EXTRA,  true),
			];
		}

		return new \WP_REST_Response($quotes, 200);
	}

	public function rest_today(\WP_REST_Request $request): \WP_REST_Response {
		$quote = $this->get_quote_of_the_day();

		$now = new \DateTimeImmutable('now', wp_timezone());
		$tomorrow = $now->modify('tomorrow')->setTime(0, 0, 0);
		$max_age = max(60, $tomorrow->getTimestamp() - $now->getTimestamp());

		$response = new \WP_REST_Response($quote, 200);
		$response->header('Cache-Control', 'public, max-age=' . $max_age . ', must-revalidate');
		$response->header('Expires', gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
		return $response;
	}

	private function get_quote_of_the_day(): array {
		$ids = $this->get_published_quote_ids();

		if (empty($ids)) {
			return [
				'has_quote' => false,
				'text' => '',
				'author' => '',
				'extra' => '',
			];
		}

		$tz = wp_timezone();
		$today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		$seed = crc32($today . '|' . home_url());
		$index = (int) ($seed % count($ids));
		$post_id = (int) $ids[$index];

		$post = get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
			return [
				'has_quote' => false,
				'text' => '',
				'author' => '',
				'extra' => '',
			];
		}

		$text = (string) get_post_meta($post_id, self::META_TEXT, true);
		$author = (string) get_post_meta($post_id, self::META_AUTHOR, true);
		$extra = (string) get_post_meta($post_id, self::META_EXTRA, true);

		// Rückgabe bleibt PlainText. Frontend rendert via textContent.
		return [
			'has_quote' => true,
			'text' => $text,
			'author' => $author,
			'extra' => $extra,
		];
	}

	private function get_published_quote_ids(): array {
		$cached = get_transient('qotd_quote_ids');
		if (is_array($cached) && !empty($cached)) {
			return array_values(array_filter(array_map('intval', $cached)));
		}

		$q = new \WP_Query([
			'post_type' => self::CPT,
			'post_status' => 'publish',
			'posts_per_page' => 5000,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'ID',
			'order' => 'ASC',
		]);

		$ids = is_array($q->posts) ? array_values(array_filter(array_map('intval', $q->posts))) : [];
		set_transient('qotd_quote_ids', $ids, DAY_IN_SECONDS);
		return $ids;
	}

	// -------------------------------------------------------------------------
	// Import / Export
	// -------------------------------------------------------------------------

	public function register_admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . self::CPT,
			__('Import / Export', 'qotd'),
			__('Import / Export', 'qotd'),
			'manage_options',
			'qotd-import-export',
			[$this, 'render_import_export_page']
		);
	}

	public function render_import_export_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html(__('Keine Berechtigung.', 'qotd')));
		}

		$redirect_base = admin_url('edit.php?post_type=' . self::CPT . '&page=qotd-import-export');

		// Erfolgs- / Fehlermeldung aus Redirect-Parametern aufbauen
		$notice = '';
		if (isset($_GET['qotd_imported'])) {
			$imported = (int) $_GET['qotd_imported'];
			$skipped  = isset($_GET['qotd_skipped']) ? (int) $_GET['qotd_skipped'] : 0;

			$parts = [];
			$parts[] = esc_html(sprintf(
				_n('%d Zitat importiert', '%d Zitate importiert', $imported, 'qotd'),
				$imported
			));
			if ($skipped > 0) {
				$parts[] = esc_html(sprintf(
					_n('%d bereits vorhanden (übersprungen)', '%d bereits vorhanden (übersprungen)', $skipped, 'qotd'),
					$skipped
				));
			}
			$notice = '<div class="notice notice-success is-dismissible"><p>' . implode(', ', $parts) . '.</p></div>';
		} elseif (isset($_GET['qotd_import_error'])) {
			$messages = [
				'no_file'      => __('Keine Datei ausgewählt.', 'qotd'),
				'upload_error' => __('Fehler beim Datei-Upload.', 'qotd'),
				'invalid_type' => __('Ungültiger Dateityp – bitte eine JSON-Datei hochladen.', 'qotd'),
				'too_large'    => __('Die Datei ist zu groß (max. 2 MB).', 'qotd'),
				'parse_error'  => __('Die JSON-Datei konnte nicht gelesen werden oder hat ein ungültiges Format.', 'qotd'),
				'empty'        => __('Die JSON-Datei enthält keine Einträge.', 'qotd'),
			];
			$key     = sanitize_key((string) ($_GET['qotd_import_error'] ?? ''));
			$message = $messages[$key] ?? __('Unbekannter Fehler.', 'qotd');
			$notice  = '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html(__('Zitate Import / Export', 'qotd')); ?></h1>

			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- vollständig escapet oben ?>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:2em;margin-top:1.5em;max-width:900px;">

				<div class="postbox">
					<div class="postbox-header" style="padding: 0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Export', 'qotd')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('Alle veröffentlichten Zitate als JSON-Datei herunterladen (Felder: text, author, extra).', 'qotd')); ?></p>
						<button id="qotd-export-btn" class="button button-primary">
							<?php echo esc_html(__('JSON exportieren', 'qotd')); ?>
						</button>
						<span id="qotd-export-status" style="margin-left:8px;"></span>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding: 0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Import', 'qotd')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('JSON-Datei hochladen (Array mit Objekten der Felder text, author, extra). Bereits vorhandene Zitate (gleicher Text) werden übersprungen.', 'qotd')); ?></p>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field('qotd_import', 'qotd_import_nonce'); ?>
							<input type="hidden" name="action" value="qotd_import">
							<p><input type="file" name="qotd_csv" accept=".json" required></p>
							<?php submit_button(__('JSON importieren', 'qotd'), 'primary', 'submit', false); ?>
						</form>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	public function enqueue_admin_export_script(string $hook): void {
		// Nur auf der Import/Export-Seite laden.
		// Hook-Name für Untermenü unter einem CPT: {post_type}_page_{menu_slug}
		if ($hook !== 'qotd_quote_page_qotd-import-export') {
			return;
		}

		wp_enqueue_script(
			'qotd-admin-export',
			plugins_url('admin-export.js', __FILE__),
			[],
			self::VERSION,
			true
		);

		wp_localize_script('qotd-admin-export', 'QOTDExport', [
			'endpoint'     => esc_url_raw(rest_url(self::REST_NAMESPACE . '/export')),
			'nonce'        => wp_create_nonce('wp_rest'),
			'filename'     => 'qotd-export-' . gmdate('Y-m-d') . '.json',
			'labelExport'  => __('JSON exportieren', 'qotd'),
			'labelLoading' => __('Wird exportiert…', 'qotd'),
			'labelError'   => __('Fehler beim Export:', 'qotd'),
		]);
	}

	public function handle_import(): void {
		check_admin_referer('qotd_import', 'qotd_import_nonce');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html(__('Keine Berechtigung.', 'qotd')));
		}

		$base = admin_url('edit.php?post_type=' . self::CPT . '&page=qotd-import-export');

		// Upload-Fehler prüfen
		$upload_error = (int) ($_FILES['qotd_csv']['error'] ?? UPLOAD_ERR_NO_FILE);

		if ($upload_error === UPLOAD_ERR_NO_FILE) {
			wp_safe_redirect($base . '&qotd_import_error=no_file');
			exit;
		}
		if ($upload_error !== UPLOAD_ERR_OK) {
			wp_safe_redirect($base . '&qotd_import_error=upload_error');
			exit;
		}

		$tmp  = (string) ($_FILES['qotd_csv']['tmp_name'] ?? '');
		$name = (string) ($_FILES['qotd_csv']['name']     ?? '');
		$size = (int)    ($_FILES['qotd_csv']['size']      ?? 0);

		// Größe (max. 2 MB)
		if ($size > 2 * 1024 * 1024) {
			wp_safe_redirect($base . '&qotd_import_error=too_large');
			exit;
		}

		// Dateiendung
		if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
			wp_safe_redirect($base . '&qotd_import_error=invalid_type');
			exit;
		}

		// Sicherstellen, dass es ein echter Upload ist
		if (!is_uploaded_file($tmp)) {
			wp_safe_redirect($base . '&qotd_import_error=upload_error');
			exit;
		}

		// JSON einlesen und dekodieren
		$raw = file_get_contents($tmp);
		if ($raw === false) {
			wp_safe_redirect($base . '&qotd_import_error=parse_error');
			exit;
		}

		$items = json_decode($raw, true);
		if (!is_array($items) || json_last_error() !== JSON_ERROR_NONE) {
			wp_safe_redirect($base . '&qotd_import_error=parse_error');
			exit;
		}

		if (empty($items)) {
			wp_safe_redirect($base . '&qotd_import_error=empty');
			exit;
		}

		// Vorhandene Texte für Duplikat-Prüfung vorladen
		$existing = $this->get_existing_quote_texts();

		$imported = 0;
		$skipped  = 0;

		foreach ($items as $item) {
			if (!is_array($item) || !array_key_exists('text', $item)) {
				continue;
			}

			$text   = sanitize_textarea_field(trim((string) $item['text']));
			$author = isset($item['author']) ? sanitize_text_field(trim((string) $item['author'])) : '';
			$extra  = isset($item['extra'])  ? sanitize_text_field(trim((string) $item['extra']))  : '';

			if ($text === '') {
				continue;
			}

			if (in_array($text, $existing, true)) {
				$skipped++;
				continue;
			}

			// Kurztitel aus dem Zitat-Text (identisch mit autofill_title-Logik)
			$max   = 80;
			$short = function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
			$len   = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
			$title = $len > $max ? $short . '…' : $short;

			$post_id = wp_insert_post([
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $title,
			], true);

			if (is_wp_error($post_id)) {
				continue;
			}

			update_post_meta($post_id, self::META_TEXT,   $text);
			update_post_meta($post_id, self::META_AUTHOR, $author);
			update_post_meta($post_id, self::META_EXTRA,  $extra);

			// Innerhalb desselben Imports ebenfalls auf Duplikate prüfen
			$existing[] = $text;
			$imported++;
		}

		if ($imported > 0) {
			delete_transient('qotd_quote_ids');
		}

		wp_safe_redirect($base . '&qotd_imported=' . $imported . '&qotd_skipped=' . $skipped);
		exit;
	}

	private function get_existing_quote_texts(): array {
		$texts = [];
		foreach ($this->get_published_quote_ids() as $post_id) {
			$text = (string) get_post_meta($post_id, self::META_TEXT, true);
			if ($text !== '') {
				$texts[] = $text;
			}
		}
		return $texts;
	}

	// -------------------------------------------------------------------------

	public function register_block(): void {
		$build_dir = __DIR__ . '/build';
		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $build_dir, [
			'render_callback' => [$this, 'render_block'],
		] );
	}

	public function render_block( array $attributes ): string {
		// WordPress speichert das native "Zusätzliche CSS-Klasse"-Feld als className.
		$css_class = isset( $attributes['className'] ) ? trim( (string) $attributes['className'] ) : '';
		return $this->shortcode( $css_class !== '' ? [ 'class' => $css_class ] : [] );
	}

	public function register_assets(): void {
		$handle = 'qotd-frontend';
		$src = plugins_url('qotd.js', __FILE__);
		wp_register_script($handle, $src, [], self::VERSION, true);

		wp_localize_script($handle, 'QOTD', [
			'endpoint' => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_ROUTE)),
		]);
	}

	public function shortcode(array $atts = []): string {
		$atts = shortcode_atts([
			'class' => '',
		], $atts, self::SHORTCODE);

		$class = trim((string) $atts['class']);
		$class_attr = $class !== '' ? ' ' . sanitize_html_class($class) : '';

		wp_enqueue_script('qotd-frontend');

		$html  = '<div class="qotd' . $class_attr . '" data-qotd="1">';
		// pre-line: Zeilenumbrüche aus PlainText bleiben sichtbar
		$html .= '<div class="qotd__text" style="white-space:pre-line" aria-live="polite"></div>';
		$html .= '<div class="qotd__meta">';
		$html .= '<span class="qotd__author"></span>';
		$html .= '<span class="qotd__source"></span>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}

QOTD_Plugin::init();
