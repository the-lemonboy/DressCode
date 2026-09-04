<?php
/**
 * Skills management: folder-based skills + custom DB table + admin CRUD.
 *
 * Each skill is an independent folder under wp-content/uploads/dresscode-skills/<slug>/
 * containing at least a SKILL.md (the system prompt) and optionally a references/
 * subfolder with extra .md files that are concatenated into the prompt.
 *
 * @package DressCode Tool/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DressCode_Skills
 */
class DressCode_Skills {

	/**
	 * Table name (including prefix).
	 *
	 * @var string
	 */
	protected $table = '';

	/**
	 * Admin page slug (also the top-level menu slug).
	 *
	 * @var string
	 */
	protected $page_slug = 'dresscode';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string|null
	 */
	protected $hook_suffix = null;

	/**
	 * Subdirectory under uploads where skill folders live.
	 *
	 * @var string
	 */
	protected $subdir = 'dresscode-skills';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'dresscode_skills';

		// Self-healing schema/seed upgrade (no re-activation needed).
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

		// Register the parent menu before the settings submenu so WP does not
		// auto-insert a duplicate "DressCode" item.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_post_dresscode_skill_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_dresscode_skill_delete', array( $this, 'handle_delete' ) );
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function table_name() {
		return $this->table;
	}

	/**
	 * Current DB schema version for the skills table.
	 *
	 * Bump when the schema/seed changes. maybe_upgrade() runs the upgrade
	 * whenever the stored version is older, so file-only updates do not
	 * require re-activating the plugin.
	 */
	const DB_VERSION = '2';

	/**
	 * Run the upgrade (schema + migration + seed) if needed.
	 *
	 * Hooked on admin_init so any admin page load self-heals the schema.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$current = get_option( 'dresscode_db_version', '0' );
		if ( version_compare( $current, self::DB_VERSION, '>=' ) ) {
			return;
		}
		$this->upgrade();
		update_option( 'dresscode_db_version', self::DB_VERSION );
	}

	/**
	 * Create/repair the table, migrate legacy rows, seed defaults.
	 *
	 * @return void
	 */
	public function upgrade() {
		$this->migrate_legacy_plugin();
		$this->create_table();
		$this->migrate_legacy_rows();
		$this->seed_defaults();
	}

	/**
	 * One-time migration from the plugin's former "camthink-ai-tool" naming:
	 * copies old option values, renames the skills table and the uploads
	 * subfolder. Runs inside maybe_upgrade() only while the new db_version
	 * option is unset, so it executes at most once per install.
	 *
	 * @return void
	 */
	protected function migrate_legacy_plugin() {
		global $wpdb;

		// Options: copy old values when the new keys are still unset.
		$pairs = array(
			'camthink_glm_api_key'     => 'dresscode_glm_api_key',
			'camthink_glm_api_format'  => 'dresscode_glm_api_format',
			'camthink_glm_endpoint'    => 'dresscode_glm_endpoint',
			'camthink_glm_model'       => 'dresscode_glm_model',
			'camthink_glm_temperature' => 'dresscode_glm_temperature',
		);
		foreach ( $pairs as $old => $new ) {
			if ( false === get_option( $new ) ) {
				$old_value = get_option( $old );
				if ( false !== $old_value && '' !== $old_value ) {
					update_option( $new, $old_value );
				}
			}
		}
		delete_option( 'camthink_ai_tool_db_version' );

		// Table: rename the legacy one when present.
		$legacy_table = $wpdb->prefix . 'camthink_skills';
		$has_new      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
		$has_old      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) );
		if ( $has_old && ! $has_new ) {
			$wpdb->query( "RENAME TABLE {$legacy_table} TO {$this->table}" ); // phpcs:ignore
		}

		// Uploads subfolder: rename the legacy one when present.
		$uploads   = wp_upload_dir();
		$legacy_dir = trailingslashit( $uploads['basedir'] ) . 'camthink-skills';
		$new_dir    = $this->skills_dir();
		if ( is_dir( $legacy_dir ) && ! is_dir( $new_dir ) ) {
			wp_mkdir_p( dirname( $new_dir ) );
			@rename( $legacy_dir, $new_dir ); // phpcs:ignore
		}
	}

	/* ----------------------------------------------------------------------
	 * Filesystem helpers
	 * -------------------------------------------------------------------- */

	/**
	 * Absolute path to the skills root directory.
	 *
	 * @return string
	 */
	public function skills_dir() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . $this->subdir;
	}

	/**
	 * Ensure the skills root directory exists.
	 *
	 * @return string
	 */
	protected function ensure_skills_dir() {
		$dir = $this->skills_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// Guard against directory listing / PHP execution.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Options -Indexes\n<Files *.php>\nDeny from all\n</Files>\n" ); // phpcs:ignore
		}
		return $dir;
	}

	/**
	 * Absolute path to a single skill folder.
	 *
	 * @param string $slug Skill folder slug.
	 * @return string
	 */
	protected function folder_path( $slug ) {
		return trailingslashit( $this->skills_dir() ) . $slug;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	protected function rrmdir( $dir ) {
		if ( ! $dir || ! is_dir( $dir ) ) {
			return;
		}
		$items = @scandir( $dir ); // phpcs:ignore
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = trailingslashit( $dir ) . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				@unlink( $path ); // phpcs:ignore
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}

	/**
	 * Read SKILL.md content for a slug.
	 *
	 * @param string $slug Skill folder slug.
	 * @return string
	 */
	protected function read_skill_md( $slug ) {
		$path = trailingslashit( $this->folder_path( $slug ) ) . 'SKILL.md';
		if ( ! $slug || ! file_exists( $path ) ) {
			return '';
		}
		return (string) file_get_contents( $path ); // phpcs:ignore
	}

	/**
	 * Write SKILL.md content for a slug (creates the folder).
	 *
	 * @param string $slug    Skill folder slug.
	 * @param string $content SKILL.md content.
	 * @return bool
	 */
	protected function write_skill_md( $slug, $content ) {
		if ( ! $slug ) {
			return false;
		}
		$dir = $this->folder_path( $slug );
		wp_mkdir_p( $dir );
		$path = trailingslashit( $dir ) . 'SKILL.md';
		return false !== @file_put_contents( $path, $content ); // phpcs:ignore
	}

	/**
	 * List reference .md files inside a skill folder.
	 *
	 * @param string $slug Skill folder slug.
	 * @return string[] Relative paths (e.g. "references/ui.md").
	 */
	protected function list_reference_files( $slug ) {
		// Normalize separators: on Windows glob() returns forward slashes while
		// the built path mixes both, which broke the SKILL.md exclusion and
		// caused the skill prompt to be appended twice.
		$dir   = wp_normalize_path( $this->folder_path( $slug ) );
		$files = array();
		if ( ! $slug || ! is_dir( $dir ) ) {
			return $files;
		}
		// references/*.md
		$refs = glob( trailingslashit( $dir ) . 'references/*.md' );
		if ( $refs ) {
			foreach ( $refs as $f ) {
				$files[] = ltrim( str_replace( $dir, '', wp_normalize_path( $f ) ), '/' );
			}
		}
		// any other top-level .md besides SKILL.md (case-insensitive, Windows FS)
		$others = glob( trailingslashit( $dir ) . '*.md' );
		if ( $others ) {
			foreach ( $others as $f ) {
				$rel = ltrim( str_replace( $dir, '', wp_normalize_path( $f ) ), '/' );
				if ( 'skill.md' !== strtolower( $rel ) ) {
					$files[] = $rel;
				}
			}
		}
		sort( $files );
		return $files;
	}

	/**
	 * Build the full system prompt from a skill folder: SKILL.md + references.
	 *
	 * @param string $slug Skill folder slug.
	 * @return string
	 */
	public function get_skill_prompt_from_folder( $slug ) {
		if ( ! $slug ) {
			return '';
		}
		$prompt = $this->read_skill_md( $slug );
		foreach ( $this->list_reference_files( $slug ) as $rel ) {
			$full = trailingslashit( $this->folder_path( $slug ) ) . $rel;
			if ( file_exists( $full ) ) {
				$prompt .= "\n\n---\n\n# " . $rel . "\n\n" . file_get_contents( $full ); // phpcs:ignore
			}
		}
		return $prompt;
	}

	/**
	 * Resolve the system prompt for a skill id (folder first, legacy column fallback).
	 *
	 * @param int $id Skill id (0 = default).
	 * @return string
	 */
	public function get_skill_prompt( $id = 0 ) {
		$skill = $id ? $this->get_skill( $id ) : null;
		if ( ! $skill ) {
			$skill = $this->get_default_skill();
		}
		if ( ! $skill ) {
			return '';
		}
		$prompt = '';
		if ( ! empty( $skill->folder ) ) {
			$prompt = $this->get_skill_prompt_from_folder( $skill->folder );
		}
		if ( '' === trim( $prompt ) ) {
			$prompt = isset( $skill->system_prompt ) ? (string) $skill->system_prompt : '';
		}
		if ( '' === trim( $prompt ) ) {
			$prompt = $this->maybe_reseed_default_skill( $skill );
		}
		return $prompt;
	}

	/**
	 * Self-heal: when the default skill's folder/SKILL.md has gone missing
	 * (both disk and legacy column empty), rebuild it from the built-in
	 * default prompt so the editor AI button keeps working with a real skill.
	 *
	 * Custom (non-default) skills are not reconstructed — their content is
	 * unknown once the folder is lost.
	 *
	 * @param object $skill Skill row.
	 * @return string The restored prompt, or '' if not applicable.
	 */
	protected function maybe_reseed_default_skill( $skill ) {
		if ( empty( $skill->is_default ) ) {
			return '';
		}

		$slug = ! empty( $skill->folder ) ? sanitize_title( $skill->folder ) : $this->unique_slug( 'dresscode-standard' );
		if ( ! $this->write_skill_md( $slug, $this->default_prompt() ) ) {
			return '';
		}

		if ( $slug !== (string) $skill->folder ) {
			global $wpdb;
			$wpdb->update( $this->table, array( 'folder' => $slug, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $skill->id ), array( '%s', '%s' ), array( '%d' ) ); // phpcs:ignore
		}

		return $this->get_skill_prompt_from_folder( $slug );
	}

	/**
	 * Ensure a folder slug is unique among existing folders and DB rows.
	 *
	 * @param string $slug Proposed slug.
	 * @return string
	 */
	protected function unique_slug( $slug ) {
		global $wpdb;
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			$slug = 'skill';
		}
		$base  = $slug;
		$i     = 1;
		$paths = glob( trailingslashit( $this->skills_dir() ) . '*', GLOB_ONLYDIR );
		$taken = array();
		if ( $paths ) {
			foreach ( $paths as $p ) {
				$taken[] = basename( $p );
			}
		}
		$db = $wpdb->get_col( "SELECT folder FROM {$this->table}" ); // phpcs:ignore
		if ( $db ) {
			$taken = array_merge( $taken, $db );
		}
		while ( in_array( $slug, $taken, true ) ) {
			$slug = $base . '-' . ++$i;
		}
		return $slug;
	}

	/* ----------------------------------------------------------------------
	 * Activation: schema, migration, seed
	 * -------------------------------------------------------------------- */

	/**
	 * Create the skills table. Called on activation.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $this->table;

		// `system_prompt` kept for back-compat with rows created before the
		// folder-based design; new skills read their prompt from disk.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			description TEXT NULL,
			system_prompt LONGTEXT NULL,
			folder VARCHAR(191) NULL DEFAULT '',
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY is_default (is_default),
			KEY folder (folder)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Convert legacy rows (system_prompt set, folder empty) to folder skills.
	 *
	 * @return void
	 */
	public function migrate_legacy_rows() {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT id, name, system_prompt FROM {$this->table} WHERE (folder IS NULL OR folder = '') AND system_prompt IS NOT NULL AND system_prompt != ''" ); // phpcs:ignore
		if ( ! $rows ) {
			return;
		}
		$this->ensure_skills_dir();
		foreach ( $rows as $row ) {
			$slug = $this->unique_slug( 'skill-' . (int) $row->id );
			$this->write_skill_md( $slug, (string) $row->system_prompt );
			$wpdb->update( $this->table, array( 'folder' => $slug ), array( 'id' => $row->id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore
		}
	}

	/**
	 * Seed a default skill (as a folder) if the table is empty.
	 *
	 * @return void
	 */
	public function seed_defaults() {
		global $wpdb;

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore
		if ( $count > 0 ) {
			return;
		}

		$this->ensure_skills_dir();
		$slug = $this->unique_slug( 'dresscode-standard' );
		$this->write_skill_md( $slug, $this->default_prompt() );

			$now = current_time( 'mysql' );
			$wpdb->insert(
				$this->table, // phpcs:ignore WordPress.DB
				array(
					'name'          => __( 'DressCode 通用规范', 'dresscode' ),
					'description'   => __( '默认通用规范：rem 字体系统、1.5 行高、圆角、响应式基准、CSS 变量。可编辑或重新上传覆盖。', 'dresscode' ),
					'system_prompt' => '',
					'folder'        => $slug,
					'is_default'    => 1,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

	/**
	 * Default generic system prompt seeded on fresh installs.
	 *
	 * @return string
	 */
	protected function default_prompt() {
		return <<<PROMPT
你是 HTML 样式优化助手。严格遵循以下通用规范优化用户提供的 HTML，输出优化后的完整 HTML 片段。

【设计尺寸】PC 1920×900；移动端 750×1200（2倍图）。

【色彩】用 CSS 变量管理颜色（--color-primary、--color-text、--color-bg 等），层次分明：页面底色、大标题、正文、强调色、卡片背景与描边各自独立变量；强调色用于 CTA 与关键信息。

【字体系统】使用 rem 单位，1.5 倍行高。
- PC（root 16px）：大标题 3rem/48px Bold；二级 2.375rem/38px SemiBold；三级 1.75rem/28px Medium；正文 1.125rem/18px Regular；小字 0.875–1rem。PC 最小字号 0.5rem。
- 移动端（root 14px）：大标题 2rem/28px Bold；二级 1.57rem/22px SemiBold；三级 1.17rem/16px Medium；正文 1rem/14px Regular；小字 0.67–0.86rem。移动端最小字号 1rem。

【布局间距】
- PC：section 内边距 左右 11rem / 上下 6rem；页面两侧 10.625rem。
- 移动端：section 内边距 左右 2rem / 上下 4rem；页面两侧 2.08rem。

【圆角】按钮 6px；卡片最外层 4px；内嵌卡片 8px。

【响应式基准】
:root { font-size: 16px; }
@media (max-width: 1680px) { :root { font-size: 15px; } }
@media (max-width: 768px) { :root { font-size: 14px; } }

【代码要求】
1. 使用 CSS 变量管理颜色与间距。
2. 使用 rem 单位确保响应式缩放。
3. 严格遵循字号与字重规范，保持 1.5 倍行高。
4. PC 与移动端同时适配。
5. 根据主题选择对应颜色方案（未指定则默认浅色）。
6. 圆角值精确匹配规范。
7. 保留原有语义化标签与文字内容，不擅自删改正文。
PROMPT;
	}

	/* ----------------------------------------------------------------------
	 * Read accessors
	 * -------------------------------------------------------------------- */

	/**
	 * Get a single skill by id.
	 *
	 * @param int $id Skill id.
	 * @return object|null
	 */
	public function get_skill( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! $id ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Get the default skill (first is_default=1, else first row).
	 *
	 * @return object|null
	 */
	public function get_default_skill() {
		global $wpdb;
		$row = $wpdb->get_row( "SELECT * FROM {$this->table} WHERE is_default = 1 ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
		if ( ! $row ) {
			$row = $wpdb->get_row( "SELECT * FROM {$this->table} ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
		}
		return $row;
	}

	/**
	 * Get all skills as array of {id,name,is_default,folder}.
	 *
	 * @return array
	 */
	public function get_all_skills() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id, name, folder, is_default FROM {$this->table} ORDER BY is_default DESC, id ASC" ); // phpcs:ignore
		return $rows ? $rows : array();
	}

	/* ----------------------------------------------------------------------
	 * Admin menu + rendering
	 * -------------------------------------------------------------------- */

	/**
	 * Register the top-level DressCode menu and the Skills submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = 'manage_options';

		add_menu_page(
			__( 'DressCode', 'dresscode' ),
			__( 'DressCode', 'dresscode' ),
			$cap,
			'dresscode',
			array( $this, 'render_page' ),
			'none', // icon drawn via CSS mask in admin.css (sparkle SVG).
			58
		);

		$this->hook_suffix = add_submenu_page(
			'dresscode',
			__( 'Skills', 'dresscode' ),
			__( 'Skills', 'dresscode' ),
			$cap,
			'dresscode',
			array( $this, 'render_page' )
		);

		add_action( 'admin_print_styles-' . $this->hook_suffix, array( $this, 'enqueue_assets' ) );
	}

	/**
	 * No-op asset hook (admin.css is globally enqueued by the main class).
	 *
	 * @return void
	 */
	public function enqueue_assets() {
	}

	/**
	 * Render the skills admin page (list or edit form).
	 *
	 * @return void
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list'; // phpcs:ignore
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore

		echo '<div class="wrap" id="dresscode-skills-page">';
		echo '<h1>' . esc_html__( 'DressCode Skills', 'dresscode' ) . ' ';
		echo '<a class="page-title-action" href="' . esc_url( $this->edit_url( 0 ) ) . '">' . esc_html__( '新增 / 导入 Skill', 'dresscode' ) . '</a></h1>';
		echo '<p>' . esc_html__( '每个 Skill 是一个独立文件夹（含 SKILL.md，可选 references/*.md）。可手写或上传 zip 导入。AI 按选中的 Skill 优化编辑器里的 HTML。', 'dresscode' ) . '</p>';

		if ( 'edit' === $action ) {
			$this->render_edit_form( $id );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	/**
	 * Render the skills list table.
	 *
	 * @return void
	 */
	protected function render_list() {
		$skills = $this->get_all_skills();

		echo '<table class="widefat striped" id="dresscode-skills-table">';
		echo '<thead><tr>';
		echo '<th style="width:60px;">' . esc_html__( 'ID', 'dresscode' ) . '</th>';
		echo '<th>' . esc_html__( '名称', 'dresscode' ) . '</th>';
		echo '<th>' . esc_html__( '文件夹', 'dresscode' ) . '</th>';
		echo '<th style="width:100px;">' . esc_html__( '默认', 'dresscode' ) . '</th>';
		echo '<th style="width:160px;">' . esc_html__( '操作', 'dresscode' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $skills ) ) {
			echo '<tr><td colspan="5">' . esc_html__( '暂无 Skill，请新增或导入。', 'dresscode' ) . '</td></tr>';
		} else {
			foreach ( $skills as $s ) {
				$default = $s->is_default ? '<span class="dashicons dashicons-yes"></span>' : '';
				echo '<tr>';
				echo '<td>' . esc_html( $s->id ) . '</td>';
				echo '<td><strong>' . esc_html( $s->name ) . '</strong></td>';
				echo '<td><code>' . esc_html( $s->folder ? $s->folder : '—' ) . '</code></td>';
				echo '<td>' . $default . '</td>'; // phpcs:ignore
				echo '<td>';
				echo '<a href="' . esc_url( $this->edit_url( $s->id ) ) . '">' . esc_html__( '编辑', 'dresscode' ) . '</a> | ';
				echo '<a class="dresscode-skill-delete" href="' . esc_url( $this->delete_url( $s->id ) ) . '">' . esc_html__( '删除', 'dresscode' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the add/edit form (supports manual SKILL.md editing + zip import).
	 *
	 * @param int $id Skill id (0 for new).
	 * @return void
	 */
	protected function render_edit_form( $id ) {
		$skill = $id ? $this->get_skill( $id ) : null;

		$name        = $skill ? $skill->name : '';
		$description = $skill ? $skill->description : '';
		$folder      = $skill && isset( $skill->folder ) ? $skill->folder : '';
		$is_default  = $skill ? (int) $skill->is_default : 0;

		// SKILL.md content: from folder, else legacy system_prompt column.
		$skill_md = $folder ? $this->read_skill_md( $folder ) : '';
		if ( '' === trim( $skill_md ) && $skill && ! empty( $skill->system_prompt ) ) {
			$skill_md = $skill->system_prompt;
		}

		$refs = $folder ? $this->list_reference_files( $folder ) : array();

		$title = $skill ? sprintf( /* translators: %s: skill name */ __( '编辑 Skill： %s', 'dresscode' ), $name ) : __( '新增 / 导入 Skill', 'dresscode' );

		echo '<h2>' . esc_html( $title ) . '</h2>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="dresscode_skill_save" />';
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '" />';
		echo '<input type="hidden" name="existing_folder" value="' . esc_attr( $folder ) . '" />';
		wp_nonce_field( 'dresscode_skill_save', 'dresscode_skill_nonce' );

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="dresscode_skill_name">' . esc_html__( '名称', 'dresscode' ) . '</label></th>';
		echo '<td><input name="name" id="dresscode_skill_name" type="text" class="regular-text" required value="' . esc_attr( $name ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="dresscode_skill_description">' . esc_html__( '描述', 'dresscode' ) . '</label></th>';
		echo '<td><input name="description" id="dresscode_skill_description" type="text" class="regular-text" value="' . esc_attr( $description ) . '" /></td></tr>';

		if ( $folder ) {
			echo '<tr><th scope="row">' . esc_html__( '文件夹', 'dresscode' ) . '</th>';
			echo '<td><code>' . esc_html( $folder ) . '</code><p class="description">' . esc_html__( '文件夹名（slug）创建后不可更改；上传 zip 会替换其内容。', 'dresscode' ) . '</p></td></tr>';
		}

		echo '<tr><th scope="row"><label for="dresscode_skill_zip">' . esc_html__( '上传 zip 导入', 'dresscode' ) . '</label></th>';
		echo '<td><input name="skill_zip" id="dresscode_skill_zip" type="file" accept=".zip,application/zip,application/x-zip-compressed" />';
		echo '<p class="description">' . esc_html__( 'zip 内须含 SKILL.md（可在根目录，或单个子文件夹下）。上传后将以此 zip 内容创建/替换 Skill 文件夹，下面的 SKILL.md 文本框会被忽略。', 'dresscode' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="dresscode_skill_prompt">' . esc_html__( 'SKILL.md（System Prompt）', 'dresscode' ) . '</label></th>';
		echo '<td><textarea name="system_prompt" id="dresscode_skill_prompt" rows="18" class="large-text code">' . esc_textarea( $skill_md ) . '</textarea>';
		echo '<p class="description">' . esc_html__( '未上传 zip 时，以此文本作为 SKILL.md 内容保存。可选附带 references/*.md（在 zip 内）。', 'dresscode' ) . '</p></td></tr>';

		if ( $refs ) {
			echo '<tr><th scope="row">' . esc_html__( '参考文件', 'dresscode' ) . '</th>';
			echo '<td><ul style="margin-top:0;">';
			foreach ( $refs as $rel ) {
				echo '<li><code>' . esc_html( $rel ) . '</code></li>';
			}
			echo '</ul><p class="description">' . esc_html__( '这些文件会自动拼接到 System Prompt 末尾。仅 zip 导入可带入。', 'dresscode' ) . '</p></td></tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( '设为默认', 'dresscode' ) . '</th>';
		echo '<td><label><input name="is_default" type="checkbox" value="1" ' . checked( $is_default, 1, false ) . ' /> ' . esc_html__( '编辑器 AI 按钮未选择时使用此 Skill', 'dresscode' ) . '</label></td></tr>';
		echo '</table>';

		echo '<p class="submit">';
		echo '<input type="submit" class="button-primary" value="' . esc_attr__( '保存 Skill', 'dresscode' ) . '" /> ';
		echo '<a class="button" href="' . esc_url( $this->list_url() ) . '">' . esc_html__( '返回', 'dresscode' ) . '</a>';
		echo '</p>';
		echo '</form>';
	}

	/* ----------------------------------------------------------------------
	 * Handlers
	 * -------------------------------------------------------------------- */

	/**
	 * Handle save (insert/update) from admin-post. Supports zip import.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '无权限。', 'dresscode' ) );
		}

		if ( ! isset( $_POST['dresscode_skill_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dresscode_skill_nonce'] ), 'dresscode_skill_save' ) ) {
			wp_die( esc_html__( '无效请求。', 'dresscode' ) );
		}

		global $wpdb;

		$id              = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$existing_folder = isset( $_POST['existing_folder'] ) ? sanitize_title( wp_unslash( $_POST['existing_folder'] ) ) : '';
		$name            = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$description     = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$prompt          = isset( $_POST['system_prompt'] ) ? wp_unslash( $_POST['system_prompt'] ) : '';
		$is_default      = ! empty( $_POST['is_default'] ) ? 1 : 0;

		if ( '' === $name ) {
			wp_die( esc_html__( '名称不能为空。', 'dresscode' ) );
		}

		$this->ensure_skills_dir();

		// Determine folder: zip import wins, else keep existing, else derive from name.
		$folder = $existing_folder;
		$zip_error = '';

		$has_zip = isset( $_FILES['skill_zip'] ) && ! empty( $_FILES['skill_zip']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['skill_zip']['error'];

		if ( $has_zip ) {
			$slug_or_error = $this->extract_skill_zip( $_FILES['skill_zip']['tmp_name'], $name, $existing_folder ); // phpcs:ignore
			if ( is_wp_error( $slug_or_error ) ) {
				$zip_error = $slug_or_error->get_error_message();
			} else {
				$folder = $slug_or_error;
			}
		} elseif ( ! $folder ) {
			// New skill without zip: create folder from name, write SKILL.md.
			if ( '' === trim( $prompt ) ) {
				wp_die( esc_html__( '请上传 zip，或填写 SKILL.md 内容。', 'dresscode' ) );
			}
			$folder = $this->unique_slug( $name );
			$this->write_skill_md( $folder, $prompt );
		} else {
			// Existing skill without zip: update SKILL.md if content provided.
			if ( '' !== trim( $prompt ) ) {
				$this->write_skill_md( $folder, $prompt );
			}
		}

		if ( ! $folder ) {
			wp_die( esc_html__( '未能创建 Skill 文件夹。', 'dresscode' ) . ( $zip_error ? ' ' . $zip_error : '' ) );
		}

		// Clear other defaults if this one is default.
		if ( $is_default ) {
			$wpdb->query( "UPDATE {$this->table} SET is_default = 0" ); // phpcs:ignore
		}

		$now = current_time( 'mysql' );

		if ( $id ) {
			$wpdb->update(
				$this->table, // phpcs:ignore
				array(
					'name'          => $name,
					'description'   => $description,
					'folder'        => $folder,
					'is_default'    => $is_default,
					'updated_at'    => $now,
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$this->table, // phpcs:ignore
				array(
					'name'          => $name,
					'description'   => $description,
					'system_prompt' => '',
					'folder'        => $folder,
					'is_default'    => $is_default,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
		}

		$this->ensure_default();

		$redirect = $this->edit_url( $id ) . '&saved=1';
		if ( $zip_error ) {
			$redirect .= '&ziperr=' . rawurlencode( $zip_error );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Extract an uploaded skill zip into the skills directory.
	 *
	 * @param string $zip_path       Temporary zip file path.
	 * @param string $suggested_name Suggested skill name (for slug fallback).
	 * @param string $force_slug     Existing slug to replace (edit mode); empty for new.
	 * @return string|\WP_Error Resolved slug on success, WP_Error on failure.
	 */
	protected function extract_skill_zip( $zip_path, $suggested_name = '', $force_slug = '' ) {
		if ( ! file_exists( $zip_path ) ) {
			return new WP_Error( 'nozip', __( '上传的 zip 文件无效。', 'dresscode' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'fs', __( '无法初始化文件系统。', 'dresscode' ) );
		}

		$tmp = trailingslashit( $this->skills_dir() ) . '_import_' . wp_generate_password( 8, false );
		wp_mkdir_p( $tmp );

		$result = unzip_file( $zip_path, $tmp );
		if ( is_wp_error( $result ) ) {
			$this->rrmdir( $tmp );
			return $result;
		}

		// Locate the folder that contains SKILL.md.
		$root_dir = null;
		if ( file_exists( trailingslashit( $tmp ) . 'SKILL.md' ) ) {
			$root_dir = $tmp;
		} else {
			$subs = glob( trailingslashit( $tmp ) . '*', GLOB_ONLYDIR );
			if ( $subs ) {
				if ( 1 === count( $subs ) && file_exists( trailingslashit( $subs[0] ) . 'SKILL.md' ) ) {
					$root_dir = $subs[0];
				} else {
					foreach ( $subs as $s ) {
						if ( file_exists( trailingslashit( $s ) . 'SKILL.md' ) ) {
							$root_dir = $s;
							break;
						}
					}
				}
			}
		}

		if ( ! $root_dir ) {
			$this->rrmdir( $tmp );
			return new WP_Error( 'no-skill-md', __( '压缩包内未找到 SKILL.md（应在根目录或单个子文件夹下）。', 'dresscode' ) );
		}

		// Resolve slug.
		if ( $force_slug ) {
			$slug = sanitize_title( $force_slug );
		} else {
			$derived = ( $root_dir !== $tmp ) ? sanitize_title( basename( $root_dir ) ) : '';
			if ( ! $derived ) {
				$derived = sanitize_title( $suggested_name );
			}
			$slug = $this->unique_slug( $derived );
		}
		if ( '' === $slug ) {
			$slug = $this->unique_slug( 'skill' );
		}

		$final = trailingslashit( $this->skills_dir() ) . $slug;
		if ( is_dir( $final ) ) {
			$this->rrmdir( $final );
		}
		wp_mkdir_p( $final );

		// Move extracted root into the final slug folder.
		global $wp_filesystem;
		if ( $root_dir === $tmp ) {
			// Move contents of tmp into final.
			$items = $wp_filesystem->dirlist( $tmp );
			if ( $items ) {
				foreach ( $items as $name => $info ) {
					$wp_filesystem->move( trailingslashit( $tmp ) . $name, trailingslashit( $final ) . $name, true );
				}
			}
		} else {
			// $final now exists (empty); remove it so move() can place the dir directly.
			@rmdir( $final ); // phpcs:ignore
			$wp_filesystem->move( $root_dir, $final, true );
		}

		$this->rrmdir( $tmp );

		return $slug;
	}

	/**
	 * Handle delete from admin-post. Removes folder + DB row.
	 *
	 * @return void
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '无权限。', 'dresscode' ) );
		}

		if ( ! isset( $_GET['dresscode_skill_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['dresscode_skill_nonce'] ), 'dresscode_skill_delete' ) ) {
			wp_die( esc_html__( '无效请求。', 'dresscode' ) );
		}

		global $wpdb;

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id ) {
			$skill = $this->get_skill( $id );
			if ( $skill && ! empty( $skill->folder ) ) {
				$this->rrmdir( $this->folder_path( $skill->folder ) );
			}
			$wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
		}

		$this->ensure_default();

		wp_safe_redirect( $this->list_url() . '&deleted=1' );
		exit;
	}

	/**
	 * Make sure at least one skill is marked default (the first one).
	 *
	 * @return void
	 */
	protected function ensure_default() {
		global $wpdb;
		$has_default = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE is_default = 1" ); // phpcs:ignore
		if ( $has_default > 0 ) {
			return;
		}
		$first = $wpdb->get_var( "SELECT id FROM {$this->table} ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
		if ( $first ) {
			$wpdb->update( $this->table, array( 'is_default' => 1 ), array( 'id' => $first ), array( '%d' ), array( '%d' ) ); // phpcs:ignore
		}
	}

	/* ----------------------------------------------------------------------
	 * URL helpers
	 * -------------------------------------------------------------------- */

	/**
	 * Edit URL.
	 *
	 * @param int $id Skill id.
	 * @return string
	 */
	protected function edit_url( $id ) {
		return admin_url( 'admin.php?page=' . $this->page_slug . '&action=edit&id=' . (int) $id );
	}

	/**
	 * Delete URL with nonce.
	 *
	 * @param int $id Skill id.
	 * @return string
	 */
	protected function delete_url( $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=dresscode_skill_delete&id=' . (int) $id ), 'dresscode_skill_delete', 'dresscode_skill_nonce' );
	}

	/**
	 * List URL.
	 *
	 * @return string
	 */
	protected function list_url() {
		return admin_url( 'admin.php?page=' . $this->page_slug );
	}
}
