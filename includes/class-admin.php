<?php
/**
 * Admin functionaliteit voor GitHub Push-to-Deploy plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_PTD_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_github_ptd_test_connection', array($this, 'test_github_connection'));
        add_action('wp_ajax_github_ptd_remove_repository', array($this, 'remove_repository'));
        add_action('wp_ajax_github_ptd_install_repository', array($this, 'install_repository'));
        add_action('wp_ajax_github_ptd_setup_webhook', array($this, 'setup_webhook'));
        add_action('wp_ajax_github_ptd_remove_webhook', array($this, 'remove_webhook'));
        add_action('wp_ajax_github_ptd_detect_slug', array($this, 'detect_slug'));
        add_action('wp_ajax_github_ptd_scan_repositories', array($this, 'scan_repositories'));
        add_action('wp_ajax_github_ptd_save_repositories', array($this, 'save_repositories'));
        add_action('wp_ajax_github_ptd_auto_install_repository', array($this, 'auto_install_repository'));
        add_action('wp_ajax_github_ptd_add_repositories', array($this, 'add_repositories'));
        add_action('wp_ajax_github_ptd_activate_plugin', array($this, 'activate_plugin'));
    }
    
    /**
     * Voeg admin menu toe
     */
    public function add_admin_menu() {
        add_menu_page(
            'Auto-Deploy for GitHub',
            'GitHub Deploy',
            'manage_options',
            'github-ptd',
            array($this, 'admin_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>'),
            30
        );
    }
    
    /**
     * Registreer instellingen
     */
    public function register_settings() {
        register_setting('github_ptd_options', 'github_ptd_options', array($this, 'sanitize_options'));
        
        // GitHub configuratie sectie
        add_settings_section(
            'github_ptd_main',
            'GitHub Configuratie',
            array($this, 'settings_section_callback'),
            'github-ptd'
        );
        
        add_settings_field(
            'github_token',
            'GitHub Personal Access Token',
            array($this, 'github_token_callback'),
            'github-ptd',
            'github_ptd_main'
        );
        
        add_settings_field(
            'webhook_secret',
            'Webhook Secret',
            array($this, 'webhook_secret_callback'),
            'github-ptd',
            'github_ptd_main'
        );
        
        
        // Webhook configuratie sectie
        add_settings_section(
            'github_ptd_webhook',
            'Webhook Configuratie',
            array($this, 'webhook_section_callback'),
            'github-ptd'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_github-ptd') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'github-ptd-admin',
            GITHUB_PTD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GITHUB_PTD_VERSION,
            true
        );
        
        wp_localize_script('github-ptd-admin', 'githubPTD', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('github_ptd_nonce'),
            'strings' => array(
                'test_connection' => 'Test verbinding',
                'testing' => 'Testen...',
                'connection_success' => 'Verbinding succesvol!',
                'connection_failed' => 'Verbinding mislukt: ',
                'remove_repo' => 'Repository verwijderen',
                'confirm_remove' => 'Weet je zeker dat je deze repository wilt verwijderen?'
            )
        ));
        
        wp_enqueue_style(
            'github-ptd-admin',
            GITHUB_PTD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GITHUB_PTD_VERSION
        );
    }
    
    /**
     * Sanitize opties - alleen settings, NIET repositories
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        if (isset($input['github_token'])) {
            $sanitized['github_token'] = sanitize_text_field($input['github_token']);
        }
        
        if (isset($input['webhook_secret'])) {
            $sanitized['webhook_secret'] = sanitize_text_field($input['webhook_secret']);
        }
        
        // Repositories worden opgeslagen in aparte optie - NIET in settings
        // Geen repositories in sanitize_options - die blijven in github_ptd_repositories
        
        return $sanitized;
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Configureer je GitHub integratie voor push-to-deploy functionaliteit.</p>';
    }
    
    /**
     * Webhook section callback
     */
    public function webhook_section_callback() {
        echo '<div class="webhook-info">';
        echo '<p><strong>Webhook beheer:</strong> Webhooks worden automatisch ingesteld bij het installeren van repositories. Geen handmatige configuratie meer nodig!</p>';
        echo '</div>';
    }
    
    /**
     * GitHub token callback
     */
    public function github_token_callback() {
        $options = get_option('github_ptd_options', array());
        $value = $options['github_token'] ?? '';
        echo '<input type="password" name="github_ptd_options[github_token]" value="' . esc_attr($value) . '" class="regular-text" id="github_token" />';
        echo '<div id="connection-status"></div>';
        echo '<p class="description">GitHub Personal Access Token met <code>repo</code> rechten. <a href="https://github.com/settings/tokens" target="_blank">Token aanmaken</a></p>';
    }
    
    /**
     * Webhook secret callback
     */
    public function webhook_secret_callback() {
        $options = get_option('github_ptd_options', array());
        $value = $options['webhook_secret'] ?? '';
        if (empty($value)) {
            $value = wp_generate_password(32, false);
        }
        echo '<input type="text" name="github_ptd_options[webhook_secret]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<button type="button" id="generate-secret" class="button">Nieuwe secret genereren</button>';
        echo '<p class="description">Gebruik deze secret in je GitHub webhook configuratie voor beveiliging.</p>';
    }
    
    
    /**
     * Render repository row
     */
    private function render_repository_row($repo, $index) {
        $repos = get_option('github_ptd_repositories', array());
        
        echo '<div class="repository-row" data-index="' . esc_attr($index) . '">';
        echo '<div class="repository-fields">';
        
        // GitHub URL
        echo '<div class="field-group">';
        echo '<label for="repo_github_url_' . esc_attr($index) . '">GitHub URL (owner/repo):</label>';
        echo '<input type="text" id="repo_github_url_' . esc_attr($index) . '" name="github_ptd_options[repositories][' . esc_attr($index) . '][github_url]" value="' . esc_attr($repo['github_url'] ?? '') . '" class="regular-text" placeholder="username/repository" />';
        echo '</div>';
        
        // Type
        echo '<div class="field-group">';
        echo '<label for="repo_type_' . esc_attr($index) . '">Type:</label>';
        echo '<select id="repo_type_' . esc_attr($index) . '" name="github_ptd_options[repositories][' . esc_attr($index) . '][type]">';
        echo '<option value="plugin"' . selected($repo['type'] ?? 'plugin', 'plugin', false) . '>Plugin</option>';
        echo '<option value="theme"' . selected($repo['type'] ?? 'plugin', 'theme', false) . '>Thema</option>';
        echo '</select>';
        echo '</div>';
        
        // Slug (auto-detect) - verborgen standaard
        echo '<div class="field-group slug-field" style="display: none;">';
        echo '<label for="repo_slug_' . esc_attr($index) . '">WordPress Slug (auto-detect):</label>';
        echo '<input type="text" id="repo_slug_' . esc_attr($index) . '" name="github_ptd_options[repositories][' . esc_attr($index) . '][slug]" value="' . esc_attr($repo['slug'] ?? '') . '" class="regular-text" placeholder="WordPress slug wordt automatisch gedetecteerd" readonly />';
        echo '<button type="button" class="button detect-slug" data-index="' . esc_attr($index) . '">Auto-detect</button>';
        echo '</div>';
        
        // Toggle knop voor slug
        echo '<div class="field-group">';
        echo '<button type="button" class="button button-link toggle-slug" data-index="' . esc_attr($index) . '">Toon WordPress Slug</button>';
        echo '</div>';
        
        echo '</div>';
        
        // Remove button
        if (count($repos) > 1 || !empty($repo['github_url'])) {
            echo '<button type="button" class="button button-link remove-repository" data-index="' . esc_attr($index) . '">Verwijderen</button>';
        }
        
        echo '</div>';
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap github-ptd-admin">
            <div class="github-ptd-header">
                <h1>🚀 GitHub Push-to-Deploy</h1>
                <p class="description">Automatisch WordPress plugins en thema's installeren/bijwerken vanuit GitHub repositories</p>
            </div>
            
            <!-- Tabs -->
            <div class="nav-tab-wrapper">
                <a href="#install-tab" class="nav-tab nav-tab-active" data-tab="install">📦 Repositories</a>
                <a href="#settings-tab" class="nav-tab" data-tab="settings">⚙️ Instellingen</a>
            </div>
            
            <!-- Install Tab -->
            <div id="install-tab" class="tab-content active">
                <div class="github-ptd-main-content">
                    <!-- Repository Beheer -->
                    <div class="repositories-section">
                        <h2>📦 Repository Beheer</h2>
                        <div class="repositories-card">
                            <?php $this->render_repositories_section(); ?>
                        </div>
                    </div>
                    
                    <!-- Informatie -->
                    <div class="info-section">
                        <h2>ℹ️ Hoe het werkt</h2>
                        <div class="info-card">
                            <div class="workflow-steps">
                                <div class="step">
                                    <div class="step-number">1</div>
                                    <div class="step-content">
                                        <h4>Configureer GitHub</h4>
                                        <p>Voeg je GitHub Personal Access Token toe en genereer een webhook secret</p>
                                    </div>
                                </div>
                                <div class="step">
                                    <div class="step-number">2</div>
                                    <div class="step-content">
                                        <h4>Voeg Repository toe</h4>
                                        <p>Voeg je GitHub repository toe met URL, type (plugin/thema) en WordPress slug</p>
                                    </div>
                                </div>
                                <div class="step">
                                    <div class="step-number">3</div>
                                    <div class="step-content">
                                        <h4>Installeer & Activeer</h4>
                                        <p>Klik op "Installeren" - webhook wordt automatisch ingesteld</p>
                                    </div>
                                </div>
                                <div class="step">
                                    <div class="step-number">4</div>
                                    <div class="step-content">
                                        <h4>Automatische Updates</h4>
                                        <p>Bij elke push naar GitHub wordt je plugin/thema automatisch bijgewerkt</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div id="settings-tab" class="tab-content">
                <div class="github-ptd-main-content">
                    <!-- GitHub Configuratie -->
                    <div class="config-section">
                        <h2>⚙️ GitHub Configuratie</h2>
                        <div class="config-card">
                            <?php $this->render_settings_section(); ?>
                        </div>
                    </div>
                    
                    <!-- Logs Info -->
                    <div class="info-section">
                        <h2>📋 Logs & Debugging</h2>
                        <div class="info-card">
                            <h3>Logs</h3>
                            <p>Controleer de WordPress error log voor deployment logs. Je kunt de logs ook bekijken via:</p>
                            <ul>
                                <li><code>wp-content/debug.log</code> (als WP_DEBUG_LOG is ingeschakeld)</li>
                                <li>Server error logs</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render repositories section
     */
    private function render_repositories_section() {
        $repos = get_option('github_ptd_repositories', array());
        
        // Alleen formulier bovenin - GEEN preview lijst
        echo '<div class="add-repository-section">';
        echo '<h3>➕ Nieuwe Repository Toevoegen</h3>';
        echo '<p>Voeg een GitHub repository toe aan je configuratie:</p>';
        
        echo '<form id="add-repo-form" class="add-repository-form">';
        echo '<div class="repository-fields">';
        echo '<div class="field-group">';
        echo '<label for="github-url">GitHub URL (owner/repo):</label>';
        echo '<input type="text" id="github-url" name="github_url" class="regular-text" placeholder="username/repository" />';
        echo '</div>';
        echo '<div class="field-group">';
        echo '<label for="type">Type:</label>';
        echo '<select id="type" name="type">';
        echo '<option value="plugin">Plugin</option>';
        echo '<option value="theme">Thema</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="field-group">';
        echo '<label for="slug">WordPress Slug (optioneel):</label>';
        echo '<input type="text" id="slug" name="slug" class="regular-text" placeholder="plugin-or-theme-slug" />';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="add-buttons">';
        echo '<button type="submit" id="add-repository" class="button button-primary">➕ Repository Toevoegen</button>';
        echo '<button type="button" id="scan-repositories" class="button button-secondary">🔍 Browse GitHub</button>';
        echo '</div>';
        echo '<p class="help-text">💡 <strong>Tip:</strong> GitHub URL formaat: <code>username/repository</code></p>';
        echo '</form>';
        echo '</div>';
        
        // ENIGE lijst - alle repositories
        echo '<div class="repositories-list-section">';
        echo '<h3>📋 Repository Overzicht</h3>';
        echo '<p>Beheer je geconfigureerde repositories:</p>';
        
        if (!empty($repos)) {
            echo '<div class="repo-list">';
            foreach ($repos as $index => $repo) {
                if (!empty($repo['github_url']) && !empty($repo['slug'])) {
                    $this->render_repository_card($repo, $index);
                }
            }
            echo '</div>';
        } else {
            echo '<div class="no-repositories">';
            echo '<p>Nog geen repositories geconfigureerd. Voeg er een toe via het formulier hierboven.</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render individual repository card
     */
    private function render_repository_card($repo, $index) {
        // Controleer webhook status
        $webhook_status = $this->get_webhook_status($repo['github_url']);
        $webhook_exists = $webhook_status['exists'];
        $webhook_active = $webhook_status['active'];
        
        // Gebruik nieuwe installatie status helper
        $install_status = $this->rmh_repo_is_installed($repo);
        
        // Haal versie informatie op
        $version_info = $this->get_version_info($repo);
        
        echo '<div class="repo-card" data-index="' . esc_attr($index) . '">';
        
        // Header met titel en badges
        echo '<div class="repo-card__header">';
        echo '<h3>' . esc_html($repo['github_url']) . '</h3>';
        echo '<div class="repo-card__badges">';
        
        // Status badges
        if ($install_status['installed']) {
            if ($repo['type'] === 'plugin' && $install_status['active'] !== null) {
                if ($install_status['active']) {
                    echo '<span class="status-badge badge--ok">✅ Actief</span>';
                } else {
                    echo '<span class="status-badge badge--muted">⚪ Gedeactiveerd</span>';
                }
            } else {
                echo '<span class="status-badge badge--ok">✅ Geïnstalleerd</span>';
            }
        } else {
            echo '<span class="status-badge badge--err">❌ Niet geïnstalleerd</span>';
        }
        
        if ($webhook_exists && $webhook_active) {
            echo '<span class="status-badge badge--ok">🔗 Webhook actief</span>';
        } elseif ($webhook_exists && !$webhook_active) {
            echo '<span class="status-badge badge--warn">⚠️ Webhook inactief</span>';
        } else {
            echo '<span class="status-badge badge--muted">⚪ Geen webhook</span>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Body met versie informatie
        echo '<div class="repo-card__body">';
        echo '<p class="repo-card__version">Huidige versie: ' . esc_html($install_status['installed_version'] ?: '—') . '</p>';
        if ($version_info['latest_version']) {
            echo '<p class="repo-card__available">Beschikbaar: ' . esc_html($version_info['latest_version']) . '</p>';
        }
        echo '</div>';
        
        // Footer met actie knoppen
        echo '<div class="repo-card__footer">';
        
        // Bepaal knop tekst op basis van installatie en activatie status
        if ($install_status['installed']) {
            if ($install_status['active'] === false && $repo['type'] === 'plugin') {
                // Plugin geïnstalleerd maar niet actief - toon activeren knop
                echo '<button type="button" class="btn btn-success js-activate" data-id="' . esc_attr($index) . '">✅ Activeren</button>';
            } else {
                // Plugin/thema actief - toon updaten knop
                echo '<button type="button" class="btn js-update" data-id="' . esc_attr($index) . '">🔄 Updaten</button>';
            }
        } else {
            // Niet geïnstalleerd - toon installeren knop
            echo '<button type="button" class="btn btn-primary js-install" data-id="' . esc_attr($index) . '">📦 Installeren</button>';
        }
        
        if ($webhook_exists && $webhook_active) {
            echo '<button type="button" class="btn js-webhook-toggle" data-id="' . esc_attr($index) . '">🔗 Webhook deactiveren</button>';
        } else {
            echo '<button type="button" class="btn js-webhook-toggle" data-id="' . esc_attr($index) . '">🔗 Webhook activeren</button>';
        }
        
        echo '<button type="button" class="btn btn-danger js-remove" data-id="' . esc_attr($index) . '">🗑️ Verwijderen</button>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render settings section
     */
    private function render_settings_section() {
        ?>
        <div class="settings-form">
            <form method="post" action="options.php" id="github-ptd-form">
                <?php
                settings_fields('github_ptd_options');
                do_settings_sections('github-ptd');
                ?>
                <div class="form-actions">
                    <button type="submit" class="button button-primary">💾 Instellingen Opslaan</button>
                    <button type="button" id="test-connection" class="button button-secondary">🔍 Test Verbinding</button>
                </div>
            </form>
            
            <?php $this->render_github_connection_status(); ?>
        </div>
        <?php
    }
    
    /**
     * Render GitHub connection status
     */
    private function render_github_connection_status() {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (!empty($token)) {
            // Test connection
            $response = wp_remote_get('https://api.github.com/user', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent' => 'WordPress-GitHub-PTD',
                    'Accept' => 'application/vnd.github.v3+json'
                ),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $user_data = json_decode($body, true);
                
                if (isset($user_data['login'])) {
                    echo '<div class="github-connection-status">';
                    echo '<h3>GitHub Verbinding</h3>';
                    echo '<p><strong>✅ Verbonden met GitHub account:</strong> <a href="' . esc_url($user_data['html_url']) . '" target="_blank">@' . esc_html($user_data['login']) . '</a></p>';
                    echo '<p><small>Account: ' . esc_html($user_data['name'] ?? $user_data['login']) . ' (' . esc_html($user_data['public_repos']) . ' public repositories)</small></p>';
                    echo '</div>';
                }
            }
        }
    }
    
    /**
     * Test GitHub connection
     */
    public function test_github_connection() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (empty($token)) {
            wp_send_json_error('Geen GitHub token ingesteld');
        }
        
        $response = wp_remote_get('https://api.github.com/user', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Verbindingsfout: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code === 401) {
            wp_send_json_error('Ongeldige GitHub token. Controleer of de token correct is en de juiste rechten heeft.');
        } elseif ($response_code === 403) {
            wp_send_json_error('GitHub API rate limit bereikt of onvoldoende rechten. Probeer het later opnieuw.');
        } elseif ($response_code !== 200) {
            wp_send_json_error('GitHub API fout (HTTP ' . $response_code . '): ' . ($data['message'] ?? 'Onbekende fout'));
        } elseif (isset($data['login'])) {
            wp_send_json_success(array(
                'message' => 'Verbinding succesvol! Ingelogd als: ' . $data['login'],
                'user' => $data['login']
            ));
        } else {
            wp_send_json_error('Ongeldige response van GitHub API');
        }
    }
    
    /**
     * Remove repository
     */
    public function remove_repository() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $repos = get_option('github_ptd_repositories', array());
        
        if (isset($repos[$index])) {
            $repo = $repos[$index];
            
            // Verwijder plugin/thema van de website
            $uninstall_result = $this->uninstall_plugin_or_theme($repo);
            
            // Verwijder repository uit lijst
            unset($repos[$index]);
            $repos = array_values($repos); // Re-index
            update_option('github_ptd_repositories', $repos);
            
            $message = 'Repository verwijderd uit lijst';
            if ($uninstall_result) {
                $message .= ' en plugin/thema gedeïnstalleerd van de website';
            }
            
            wp_send_json_success($message);
        } else {
            wp_send_json_error('Repository niet gevonden');
        }
    }
    
    /**
     * Uninstall plugin or theme
     */
    private function uninstall_plugin_or_theme($repo) {
        $type = $repo['type'];
        $slug = $repo['slug'];
        
        if ($type === 'plugin') {
            return $this->uninstall_plugin($slug);
        } else {
            return $this->uninstall_theme($slug);
        }
    }
    
    /**
     * Uninstall plugin
     */
    private function uninstall_plugin($slug) {
        // Zoek de plugin file
        $plugins = get_plugins();
        $plugin_file = null;
        
        foreach ($plugins as $file => $plugin_data) {
            if (strpos($file, $slug . '/') === 0) {
                $plugin_file = $file;
                break;
            }
        }
        
        if (!$plugin_file) {
            return false; // Plugin niet gevonden
        }
        
        // Deactiveer plugin eerst
        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file);
        }
        
        // Verwijder plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (is_dir($plugin_dir)) {
            $this->recursive_rmdir($plugin_dir);
            return true;
        }
        
        return false;
    }
    
    /**
     * Uninstall theme
     */
    private function uninstall_theme($slug) {
        $theme = wp_get_theme($slug);
        
        if (!$theme->exists()) {
            return false; // Thema niet gevonden
        }
        
        // Verwijder thema directory
        $theme_dir = $theme->get_stylesheet_directory();
        if (is_dir($theme_dir)) {
            $this->recursive_rmdir($theme_dir);
            return true;
        }
        
        return false;
    }
    
    /**
     * Activate plugin
     */
    public function activate_plugin() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $repos = get_option('github_ptd_repositories', array());
        
        if (!isset($repos[$index])) {
            wp_send_json_error('Repository niet gevonden');
        }
        
        $repo = $repos[$index];
        
        if ($repo['type'] !== 'plugin') {
            wp_send_json_error('Alleen plugins kunnen worden geactiveerd');
        }
        
        // Zoek de plugin file
        $plugins = get_plugins();
        $plugin_file = null;
        
        foreach ($plugins as $file => $plugin_data) {
            if (strpos($file, $repo['slug'] . '/') === 0) {
                $plugin_file = $file;
                break;
            }
        }
        
        if (!$plugin_file) {
            wp_send_json_error('Plugin bestand niet gevonden');
        }
        
        // Activeer de plugin
        $result = activate_plugin($plugin_file);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Fout bij activeren plugin: ' . $result->get_error_message());
        } else {
            wp_send_json_success('Plugin succesvol geactiveerd');
        }
    }
    
    /**
     * Install repository manually
     */
    public function install_repository() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $repos = get_option('github_ptd_repositories', array());
        
        if (!isset($repos[$index])) {
            wp_send_json_error('Repository niet gevonden');
        }
        
        $repo = $repos[$index];
        
        if (empty($repo['github_url']) || empty($repo['slug'])) {
            wp_send_json_error('Repository configuratie onvolledig');
        }
        
        // Gebruik de webhook class voor installatie
        $webhook = new GitHub_PTD_Webhook();
        $result = $this->manual_install_repository($repo);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Installatie mislukt: ' . $result->get_error_message());
        } else {
            // Automatisch webhook instellen na succesvolle installatie
            $webhook_result = $this->auto_setup_webhook($repo);
            $message = 'Repository succesvol geïnstalleerd/bijgewerkt';
            
            if (!is_wp_error($webhook_result)) {
                $message .= ' en webhook automatisch ingesteld';
            } else {
                $message .= ' (webhook instellen mislukt: ' . $webhook_result->get_error_message() . ')';
            }
            
            wp_send_json_success($message);
        }
    }
    
    /**
     * Manual install repository
     */
    private function manual_install_repository($repo_config) {
        $github_url = $repo_config['github_url'];
        $type = $repo_config['type'];
        $slug = $repo_config['slug'];
        
        $this->log_debug("[GitHub PTD] Handmatige installatie van {$github_url} ({$type}: {$slug})");
        
        // Download ZIP van GitHub
        $zip_file = $this->download_github_zip($github_url, 'main');
        
        if (is_wp_error($zip_file)) {
            return $zip_file;
        }
        
        // Extract en herstructureer ZIP voor WordPress
        $extracted_path = $this->extract_and_restructure_zip($zip_file, $slug, $type);
        
        if (is_wp_error($extracted_path)) {
            // Cleanup
            if (file_exists($zip_file)) {
                wp_delete_file($zip_file);
            }
            return $extracted_path;
        }
        
        // Installeer/bijwerk plugin of thema
        if ($type === 'plugin') {
            $result = $this->install_plugin_from_folder($extracted_path, $slug);
        } else {
            $result = $this->install_theme_from_folder($extracted_path, $slug);
        }
        
        // Cleanup
        if (file_exists($zip_file)) {
            wp_delete_file($zip_file);
        }
        if (is_dir($extracted_path)) {
            $this->recursive_rmdir($extracted_path);
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $this->log_debug("[GitHub PTD] Handmatige installatie succesvol voltooid voor {$slug}");
        return true;
    }
    
    /**
     * Download GitHub ZIP
     */
    private function download_github_zip($github_url, $ref) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (empty($token)) {
            return new WP_Error('no_token', 'Geen GitHub token geconfigureerd');
        }
        
        $zip_url = "https://api.github.com/repos/{$github_url}/zipball/{$ref}";
        
        $this->log_debug("[GitHub PTD] Downloaden ZIP van: {$zip_url}");
        
        $response = wp_remote_get($zip_url, array(
            'timeout' => 300,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            return new WP_Error('download_failed', "Download mislukt (HTTP {$response_code}): " . $body);
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_response', 'Lege response van GitHub');
        }
        
        // Sla ZIP op in tijdelijke directory
        $temp_file = $this->create_temp_file('github-ptd-');
        $result = file_put_contents($temp_file, $body);
        
        if ($result === false) {
            return new WP_Error('write_failed', 'Kon ZIP bestand niet opslaan');
        }
        
        $this->log_debug("[GitHub PTD] ZIP gedownload naar: {$temp_file} (" . size_format(strlen($body)) . ")");
        return $temp_file;
    }
    
    /**
     * Extract en herstructureer ZIP voor WordPress
     */
    private function extract_and_restructure_zip($zip_file, $slug, $type) {
        $temp_dir = $this->create_temp_directory('github-ptd-extract-');
        
        if (!wp_mkdir_p($temp_dir)) {
            return new WP_Error('mkdir_failed', 'Kon tijdelijke directory niet aanmaken');
        }
        
        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== TRUE) {
            return new WP_Error('zip_open_failed', 'Kon ZIP bestand niet openen');
        }
        
        // ZIP Bomb protection - controleer bestandsgrootte
        $total_size = 0;
        $max_size = 100 * 1024 * 1024; // 100MB limiet
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $total_size += $stat['size'];
            if ($total_size > $max_size) {
                $zip->close();
                return new WP_Error('zip_too_large', 'ZIP bestand te groot (max 100MB)');
            }
        }
        
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // GitHub ZIP bestanden hebben een root directory met commit hash
        // We moeten de inhoud van die directory verplaatsen naar de juiste locatie
        $extracted_items = scandir($temp_dir);
        $source_dir = null;
        
        foreach ($extracted_items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($temp_dir . '/' . $item)) {
                $source_dir = $temp_dir . '/' . $item;
                break;
            }
        }
        
        if (!$source_dir) {
            return new WP_Error('no_source_dir', 'Geen bron directory gevonden in ZIP');
        }
        
        // Verplaats inhoud naar juiste locatie
        $target_dir = $temp_dir . '/' . $slug;
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('target_mkdir_failed', 'Kon target directory niet aanmaken');
        }
        
        $this->recursive_copy($source_dir, $target_dir);
        $this->recursive_rmdir($source_dir);
        
        // Valideer bestandstypen in de uitgepakte directory
        if (!$this->validate_extracted_files($target_dir)) {
            $this->recursive_rmdir($target_dir);
            return new WP_Error('invalid_files', 'Ongeldige bestandstypen gevonden');
        }
        
        $this->log_debug("[GitHub PTD] ZIP uitgepakt naar: {$target_dir}");
        return $target_dir;
    }
    
    /**
     * Installeer plugin vanuit folder
     */
    private function install_plugin_from_folder($source_dir, $slug) {
        $target_dir = WP_PLUGIN_DIR . '/' . $slug;
        
        // Zoek en verwijder alle bestaande versies van deze plugin
        $this->cleanup_existing_plugin_versions($slug);
        
        // Verwijder bestaande plugin als het bestaat
        if (is_dir($target_dir)) {
            $this->log_debug("[GitHub PTD] Verwijderen bestaande plugin: {$slug}");
            $this->recursive_rmdir($target_dir);
        }
        
        // Kopieer nieuwe plugin
        if (!$this->recursive_copy($source_dir, $target_dir)) {
            return new WP_Error('copy_failed', 'Kon plugin bestanden niet kopiëren');
        }
        
        $this->log_debug("[GitHub PTD] Plugin {$slug} succesvol geïnstalleerd/bijgewerkt");
        return true;
    }
    
    /**
     * Cleanup bestaande plugin versies om duplicaten te voorkomen
     */
    private function cleanup_existing_plugin_versions($slug) {
        $plugin_dirs = glob(WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR);
        
        foreach ($plugin_dirs as $dir) {
            $dir_name = basename($dir);
            
            // Zoek naar directories die lijken op onze plugin
            if (strpos($dir_name, $slug) !== false || 
                strpos($dir_name, 'gitupdater') !== false ||
                strpos($dir_name, 'github-push-to-deploy') !== false) {
                
                // Controleer of dit onze plugin is door naar de hoofdbestand te kijken
                $main_file = $dir . '/' . $slug . '.php';
                if (file_exists($main_file)) {
                    $content = file_get_contents($main_file);
                    if (strpos($content, 'GitHub Push-to-Deploy') !== false) {
                        $this->log_debug("[GitHub PTD] Verwijderen oude plugin versie: {$dir_name}");
                        $this->recursive_rmdir($dir);
                    }
                }
            }
        }
    }
    
    /**
     * Installeer thema vanuit folder
     */
    private function install_theme_from_folder($source_dir, $slug) {
        $target_dir = WP_CONTENT_DIR . '/themes/' . $slug;
        
        // Verwijder bestaand thema als het bestaat
        if (is_dir($target_dir)) {
            $this->log_debug("[GitHub PTD] Verwijderen bestaand thema: {$slug}");
            $this->recursive_rmdir($target_dir);
        }
        
        // Kopieer nieuw thema
        if (!$this->recursive_copy($source_dir, $target_dir)) {
            return new WP_Error('copy_failed', 'Kon thema bestanden niet kopiëren');
        }
        
        $this->log_debug("[GitHub PTD] Thema {$slug} succesvol geïnstalleerd/bijgewerkt");
        return true;
    }
    
    /**
     * Recursief kopiëren van directory
     */
    private function recursive_copy($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }
        
        if (!is_dir($dst)) {
            if (!wp_mkdir_p($dst)) {
                return false;
            }
        }
        
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            if (is_dir($src_file)) {
                if (!$this->recursive_copy($src_file, $dst_file)) {
                    return false;
                }
            } else {
                if (!copy($src_file, $dst_file)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Custom logging function
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Use WordPress debug.log instead of error_log
            if (function_exists('wp_debug_log')) {
                wp_debug_log($message);
            }
        }
    }
    
    /**
     * Recursief verwijderen van directory
     */
    private function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $dir . '/' . $file;
            
            if (is_dir($file_path)) {
                $this->recursive_rmdir($file_path);
            } else {
                wp_delete_file($file_path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Setup webhook for repository
     */
    public function setup_webhook() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $repos = get_option('github_ptd_repositories', array());
        $options = get_option('github_ptd_options', array());
        
        if (!isset($repos[$index])) {
            wp_send_json_error('Repository niet gevonden');
        }
        
        $repo = $repos[$index];
        $github_url = $repo['github_url'];
        $webhook_url = home_url('/wp-json/gh-deployer/v1/webhook');
        $webhook_secret = $options['webhook_secret'] ?? '';
        
        if (empty($webhook_secret)) {
            wp_send_json_error('Geen webhook secret geconfigureerd');
        }
        
        // Controleer eerst of webhook al bestaat
        $existing_webhook = $this->get_webhook_info($github_url);
        
        if ($existing_webhook) {
            // Update bestaande webhook
            $result = $this->update_webhook($github_url, $existing_webhook['id'], $webhook_url, $webhook_secret);
        } else {
            // Maak nieuwe webhook
            $result = $this->create_webhook($github_url, $webhook_url, $webhook_secret);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error('Webhook instellen mislukt: ' . $result->get_error_message());
        } else {
            wp_send_json_success('Webhook succesvol ingesteld voor ' . $github_url);
        }
    }
    
    /**
     * Remove webhook for repository
     */
    public function remove_webhook() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $repos = get_option('github_ptd_repositories', array());
        
        if (!isset($repos[$index])) {
            wp_send_json_error('Repository niet gevonden');
        }
        
        $repo = $repos[$index];
        $github_url = $repo['github_url'];
        
        // Zoek bestaande webhook
        $webhook = $this->get_webhook_info($github_url);
        
        if (!$webhook) {
            wp_send_json_error('Geen webhook gevonden voor dit repository');
        }
        
        $result = $this->delete_webhook($github_url, $webhook['id']);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Webhook verwijderen mislukt: ' . $result->get_error_message());
        } else {
            wp_send_json_success('Webhook succesvol verwijderd voor ' . $github_url);
        }
    }
    
    /**
     * Get webhook info for repository
     */
    private function get_webhook_info($github_url) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (empty($token)) {
            return false;
        }
        
        $response = wp_remote_get("https://api.github.com/repos/{$github_url}/hooks", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $webhooks = json_decode($body, true);
        
        if (!is_array($webhooks)) {
            return false;
        }
        
        $webhook_url = home_url('/wp-json/gh-deployer/v1/webhook');
        
        foreach ($webhooks as $webhook) {
            if (isset($webhook['config']['url']) && $webhook['config']['url'] === $webhook_url) {
                return $webhook;
            }
        }
        
        return false;
    }
    
    /**
     * Create webhook
     */
    private function create_webhook($github_url, $webhook_url, $webhook_secret) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        $data = array(
            'name' => 'web',
            'active' => true,
            'events' => array('push', 'release'),
            'config' => array(
                'url' => $webhook_url,
                'content_type' => 'json',
                'secret' => $webhook_secret
            )
        );
        
        $response = wp_remote_post("https://api.github.com/repos/{$github_url}/hooks", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 201) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = $error_data['message'] ?? 'Onbekende fout';
            return new WP_Error('webhook_create_failed', "Webhook aanmaken mislukt (HTTP {$response_code}): {$error_message}");
        }
        
        return true;
    }
    
    /**
     * Update webhook
     */
    private function update_webhook($github_url, $webhook_id, $webhook_url, $webhook_secret) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        $data = array(
            'active' => true,
            'events' => array('push', 'release'),
            'config' => array(
                'url' => $webhook_url,
                'content_type' => 'json',
                'secret' => $webhook_secret
            )
        );
        
        $response = wp_remote_request("https://api.github.com/repos/{$github_url}/hooks/{$webhook_id}", array(
            'method' => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = $error_data['message'] ?? 'Onbekende fout';
            return new WP_Error('webhook_update_failed', "Webhook bijwerken mislukt (HTTP {$response_code}): {$error_message}");
        }
        
        return true;
    }
    
    /**
     * Delete webhook
     */
    private function delete_webhook($github_url, $webhook_id) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        $response = wp_remote_request("https://api.github.com/repos/{$github_url}/hooks/{$webhook_id}", array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 204) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = $error_data['message'] ?? 'Onbekende fout';
            return new WP_Error('webhook_delete_failed', "Webhook verwijderen mislukt (HTTP {$response_code}): {$error_message}");
        }
        
        return true;
    }
    
    /**
     * Create temporary file
     */
    private function create_temp_file($prefix = 'github-ptd-') {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/github-ptd-temp/';
        
        // Maak temp directory aan als het niet bestaat
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Genereer unieke bestandsnaam
        $filename = $prefix . uniqid() . '.tmp';
        return $temp_dir . $filename;
    }
    
    /**
     * Valideer bestandstypen in uitgepakte directory
     */
    private function validate_extracted_files($dir) {
        // Alleen controleren op echt gevaarlijke bestanden
        $dangerous_extensions = array('exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'jar', 'sh', 'ps1');
        
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                
                // Controleer alleen op echt gevaarlijke extensies
                if (in_array($extension, $dangerous_extensions)) {
                    $this->log_debug("[GitHub PTD] Gevaarlijk bestandstype gevonden: " . $file->getPathname());
                    return false;
                }
                
                // Controleer bestandsgrootte (max 50MB per bestand voor WordPress)
                if ($file->getSize() > 50 * 1024 * 1024) {
                    $this->log_debug("[GitHub PTD] Bestand te groot: " . $file->getPathname());
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Create temporary directory
     */
    private function create_temp_directory($prefix = 'github-ptd-') {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/github-ptd-temp/';
        
        // Maak temp directory aan als het niet bestaat
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Genereer unieke directory naam
        $dirname = $prefix . uniqid();
        return $temp_dir . $dirname;
    }
    
    /**
     * Get webhook status for repository
     */
    private function get_webhook_status($github_url) {
        $webhook = $this->get_webhook_info($github_url);
        
        if (!$webhook) {
            return array('exists' => false, 'active' => false);
        }
        
        return array(
            'exists' => true,
            'active' => $webhook['active'] ?? false
        );
    }
    
    /**
     * Automatisch webhook instellen
     */
    private function auto_setup_webhook($repo) {
        $github_url = $repo['github_url'];
        $webhook_url = home_url('/wp-json/gh-deployer/v1/webhook');
        $options = get_option('github_ptd_options', array());
        $webhook_secret = $options['webhook_secret'] ?? '';
        
        if (empty($webhook_secret)) {
            return new WP_Error('no_secret', 'Geen webhook secret geconfigureerd');
        }
        
        // Controleer eerst of webhook al bestaat
        $existing_webhook = $this->get_webhook_info($github_url);
        
        if ($existing_webhook) {
            // Update bestaande webhook
            return $this->update_webhook($github_url, $existing_webhook['id'], $webhook_url, $webhook_secret);
        } else {
            // Maak nieuwe webhook
            return $this->create_webhook($github_url, $webhook_url, $webhook_secret);
        }
    }
    
    /**
     * Get version information for repository
     */
    private function get_version_info($repo) {
        $github_url = $repo['github_url'];
        $type = $repo['type'];
        $slug = $repo['slug'];
        
        $current_version = '';
        $latest_version = '';
        
        // Haal huidige versie op
        if ($type === 'plugin') {
            // Controleer eerst of plugin directory bestaat
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (is_dir($plugin_dir)) {
                // Zoek naar hoofdplugin bestand
                $main_files = array(
                    $slug . '.php',
                    $slug . '-main.php', 
                    $slug . '-plugin.php',
                    'index.php',
                    'main.php'
                );
                
                foreach ($main_files as $main_file) {
                    $plugin_file = $plugin_dir . '/' . $main_file;
                    if (file_exists($plugin_file)) {
                        $plugin_data = get_plugin_data($plugin_file);
                        $current_version = $plugin_data['Version'] ?? '';
                        break;
                    }
                }
                
                // Als geen versie gevonden, probeer alle PHP bestanden in de directory
                if (empty($current_version)) {
                    $php_files = glob($plugin_dir . '/*.php');
                    foreach ($php_files as $php_file) {
                        $plugin_data = get_plugin_data($php_file);
                        if (!empty($plugin_data['Version'])) {
                            $current_version = $plugin_data['Version'];
                            break;
                        }
                    }
                }
            }
        } else {
            $theme = wp_get_theme($slug);
            if ($theme->exists()) {
                $current_version = $theme->get('Version') ?? '';
            }
        }
        
        // Haal nieuwste versie op van GitHub
        $latest_version = $this->get_latest_github_version($github_url);
        
        return array(
            'current_version' => $current_version,
            'latest_version' => $latest_version
        );
    }
    
    /**
     * Get latest version from GitHub
     */
    private function get_latest_github_version($github_url) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (empty($token)) {
            return '';
        }
        
        // Probeer eerst releases
        $response = wp_remote_get("https://api.github.com/repos/{$github_url}/releases/latest", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            ),
            'timeout' => 10
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['tag_name'])) {
                return $data['tag_name'];
            }
        }
        
        // Fallback: probeer tags
        $response = wp_remote_get("https://api.github.com/repos/{$github_url}/tags", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            ),
            'timeout' => 10
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (is_array($data) && !empty($data) && isset($data[0]['name'])) {
                return $data[0]['name'];
            }
        }
        
        return '';
    }
    
    /**
     * Detect WordPress slug from GitHub repository
     */
    public function detect_slug() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $github_url = isset($_POST['github_url']) ? sanitize_text_field(wp_unslash($_POST['github_url'])) : '';
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        
        // Valideer GitHub URL formaat
        if (!preg_match('/^[a-zA-Z0-9\-_]+\/[a-zA-Z0-9\-_\.]+$/', $github_url)) {
            wp_send_json_error('Ongeldige GitHub URL formaat');
        }
        
        if (empty($github_url)) {
            wp_send_json_error('GitHub URL is verplicht');
        }
        
        $detected_slug = $this->auto_detect_slug($github_url, $type);
        
        if ($detected_slug) {
            wp_send_json_success($detected_slug);
        } else {
            wp_send_json_error('Kon WordPress slug niet detecteren');
        }
    }
    
    /**
     * Auto-detect WordPress slug from GitHub repository
     */
    private function auto_detect_slug($github_url, $type) {
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (empty($token)) {
            return false;
        }
        
        // Haal repository informatie op
        $response = wp_remote_get("https://api.github.com/repos/{$github_url}", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'WordPress-GitHub-PTD',
                'Accept' => 'application/vnd.github.v3+json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $repo_data = json_decode($body, true);
        
        if (!$repo_data || !isset($repo_data['name'])) {
            return false;
        }
        
        $repo_name = $repo_data['name'];
        
        // Converteer repository naam naar WordPress slug
        $slug = $this->convert_to_wordpress_slug($repo_name);
        
        // Controleer of er al een plugin/thema bestaat met deze slug
        if ($type === 'plugin') {
            $plugin_file = $slug . '/' . $slug . '.php';
            if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
                return $slug; // Bestaande plugin gevonden
            }
        } else {
            if (file_exists(WP_CONTENT_DIR . '/themes/' . $slug)) {
                return $slug; // Bestaand thema gevonden
            }
        }
        
        // Probeer alternatieve slug namen
        $alternatives = $this->generate_slug_alternatives($repo_name);
        foreach ($alternatives as $alt_slug) {
            if ($type === 'plugin') {
                $plugin_file = $alt_slug . '/' . $alt_slug . '.php';
                if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
                    return $alt_slug;
                }
            } else {
                if (file_exists(WP_CONTENT_DIR . '/themes/' . $alt_slug)) {
                    return $alt_slug;
                }
            }
        }
        
        return $slug; // Return de geconverteerde slug
    }
    
    /**
     * Convert repository name to WordPress slug
     */
    private function convert_to_wordpress_slug($repo_name) {
        // Verwijder WordPress prefixen
        $slug = preg_replace('/^(wp-|wordpress-|plugin-|theme-)/i', '', $repo_name);
        
        // Converteer naar lowercase
        $slug = strtolower($slug);
        
        // Vervang underscores en spaties met streepjes
        $slug = preg_replace('/[_\s]+/', '-', $slug);
        
        // Verwijder speciale karakters
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Verwijder meerdere streepjes
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Verwijder streepjes aan begin/einde
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Generate alternative slug names
     */
    private function generate_slug_alternatives($repo_name) {
        $alternatives = array();
        
        // Originele naam
        $alternatives[] = $this->convert_to_wordpress_slug($repo_name);
        
        // Zonder prefixen
        $without_prefix = preg_replace('/^(wp-|wordpress-|plugin-|theme-)/i', '', $repo_name);
        $alternatives[] = $this->convert_to_wordpress_slug($without_prefix);
        
        // Alleen de naam zonder organisatie prefix
        if (strpos($repo_name, '-') !== false) {
            $parts = explode('-', $repo_name);
            $last_part = end($parts);
            $alternatives[] = $this->convert_to_wordpress_slug($last_part);
        }
        
        return array_unique($alternatives);
    }
    
    /**
     * Scan GitHub repositories for WordPress plugins/themes
     */
    public function scan_repositories() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $options = get_option('github_ptd_options', array());
        $token = $options['github_token'] ?? '';
        
        if (empty($token)) {
            wp_send_json_error('Geen GitHub token geconfigureerd');
        }
        
        // Haal alle repositories op van de gebruiker
        $repositories = $this->get_user_repositories($token);
        
        if (empty($repositories)) {
            wp_send_json_error('Geen repositories gevonden');
        }
        
        // Filter repositories die WordPress plugins/thema's kunnen zijn
        $wordpress_repos = $this->filter_wordpress_repositories($repositories);
        
        if (empty($wordpress_repos)) {
            wp_send_json_error('Geen WordPress repositories gevonden');
        }
        
        wp_send_json_success($wordpress_repos);
    }
    
    /**
     * Get all repositories for the authenticated user
     */
    private function get_user_repositories($token) {
        $repositories = array();
        $page = 1;
        $per_page = 100;
        
        do {
            $response = wp_remote_get("https://api.github.com/user/repos?page={$page}&per_page={$per_page}&sort=updated", array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent' => 'WordPress-GitHub-PTD',
                    'Accept' => 'application/vnd.github.v3+json'
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                break;
            }
            
            $body = wp_remote_retrieve_body($response);
            $repos = json_decode($body, true);
            
            if (!is_array($repos) || empty($repos)) {
                break;
            }
            
            $repositories = array_merge($repositories, $repos);
            $page++;
            
        } while (count($repos) === $per_page);
        
        return $repositories;
    }
    
    /**
     * Filter repositories that might be WordPress plugins/themes
     */
    private function filter_wordpress_repositories($repositories) {
        $wordpress_repos = array();
        
        foreach ($repositories as $repo) {
            $name = $repo['name'] ?? '';
            $description = $repo['description'] ?? '';
            $topics = $repo['topics'] ?? array();
            $language = $repo['language'] ?? '';
            
            // Check if repository might be a WordPress plugin/theme
            if ($this->is_wordpress_repository($name, $description, $topics, $language)) {
                $wordpress_repos[] = array(
                    'github_url' => $repo['full_name'],
                    'name' => $name,
                    'description' => $description,
                    'language' => $language,
                    'updated_at' => $repo['updated_at'],
                    'html_url' => $repo['html_url']
                );
            }
        }
        
        return $wordpress_repos;
    }
    
    /**
     * Check if repository might be a WordPress plugin/theme
     */
    private function is_wordpress_repository($name, $description, $topics, $language) {
        // Check for WordPress keywords in name
        $wp_keywords = array('wp-', 'wordpress', 'plugin', 'theme', 'wp-plugin', 'wp-theme');
        $name_lower = strtolower($name);
        
        foreach ($wp_keywords as $keyword) {
            if (strpos($name_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // Check for WordPress keywords in description
        $desc_lower = strtolower($description);
        $wp_desc_keywords = array('wordpress', 'wp plugin', 'wp theme', 'wordpress plugin', 'wordpress theme');
        
        foreach ($wp_desc_keywords as $keyword) {
            if (strpos($desc_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // Check for WordPress topics
        foreach ($topics as $topic) {
            if (in_array(strtolower($topic), array('wordpress', 'wp-plugin', 'wp-theme', 'plugin', 'theme'))) {
                return true;
            }
        }
        
        // Check if it's PHP (common for WordPress)
        if (strtolower($language) === 'php') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if plugin or theme is installed
     */
    private function is_plugin_or_theme_installed($repo) {
        if ($repo['type'] === 'plugin') {
            $plugin_file = $repo['slug'] . '/' . $repo['slug'] . '.php';
            // Check if plugin file exists in plugins directory
            return file_exists(WP_PLUGIN_DIR . '/' . $plugin_file);
        } else {
            return wp_get_theme($repo['slug'])->exists();
        }
    }
    
    /**
     * Enhanced repository installation status helper
     */
    private function rmh_repo_is_installed($repo) {
        $result = [
            'installed' => false,
            'active' => null,
            'installed_version' => null,
            'main_file' => null
        ];
        
        if ($repo['type'] === 'plugin') {
            $slug = $repo['slug'];
            $plugins = get_plugins();
            
            // Zoek naar plugin met matching slug
            foreach ($plugins as $plugin_file => $plugin_data) {
                if (strpos($plugin_file, $slug . '/') === 0) {
                    $result['installed'] = true;
                    $result['main_file'] = $plugin_file;
                    $result['installed_version'] = $plugin_data['Version'] ?? 'Unknown';
                    
                    // Check if plugin is active
                    if (is_plugin_active($plugin_file)) {
                        $result['active'] = true;
                    } else {
                        $result['active'] = false;
                    }
                    break;
                }
            }
        } else {
            // Theme check
            $theme = wp_get_theme($repo['slug']);
            if ($theme->exists()) {
                $result['installed'] = true;
                $result['installed_version'] = $theme->get('Version') ?: 'Unknown';
                $result['active'] = ($theme->get_stylesheet() === get_stylesheet());
            }
        }
        
        return $result;
    }
    
    /**
     * Save repositories directly via AJAX
     */
    public function save_repositories() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        // Sanitize and validate repositories data
        $repositories = array();
        if (isset($_POST['repositories']) && is_array($_POST['repositories'])) {
            $raw_repositories = wp_unslash($_POST['repositories']);
            foreach ($raw_repositories as $repo) {
                if (is_array($repo)) {
                    $repositories[] = array(
                        'github_url' => sanitize_text_field($repo['github_url'] ?? ''),
                        'type' => sanitize_text_field($repo['type'] ?? ''),
                        'slug' => sanitize_text_field($repo['slug'] ?? '')
                    );
                }
            }
        }
        
        if (empty($repositories)) {
            wp_send_json_error('Geen geldige repositories data');
        }
        
        // Sanitize repositories data
        $sanitized_repos = array();
        foreach ($repositories as $repo) {
            $sanitized_repos[] = array(
                'github_url' => sanitize_text_field($repo['github_url']),
                'type' => sanitize_text_field($repo['type']),
                'slug' => sanitize_text_field($repo['slug'])
            );
        }
        
        $options = get_option('github_ptd_options', array());
        $options['repositories'] = $sanitized_repos;
        update_option('github_ptd_options', $options);
        
        wp_send_json_success('Repositories opgeslagen');
    }
    
    /**
     * Add multiple repositories
     */
    public function add_repositories() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        // Sanitize and validate items data
        $items = array();
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $raw_items = wp_unslash($_POST['items']);
            foreach ($raw_items as $item) {
                if (is_array($item)) {
                    $items[] = array(
                        'github_url' => sanitize_text_field($item['github_url'] ?? ''),
                        'type' => sanitize_text_field($item['type'] ?? ''),
                        'slug' => sanitize_text_field($item['slug'] ?? '')
                    );
                }
            }
        }
        
        if (empty($items)) {
            wp_send_json_error('Geen repositories om toe te voegen');
        }
        
        $repositories = get_option('github_ptd_repositories', array());
        
        $added_repos = array();
        
        foreach ($items as $item) {
            $github_url = sanitize_text_field($item['github_url']);
            $type = sanitize_text_field($item['type']);
            $slug = sanitize_text_field($item['slug']);
            
            if (empty($github_url) || empty($slug)) {
                continue;
            }
            
            // Check if repository already exists
            $exists = false;
            foreach ($repositories as $existing_repo) {
                if ($existing_repo['github_url'] === $github_url) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $new_repo = array(
                    'github_url' => $github_url,
                    'type' => $type,
                    'slug' => $slug
                );
                
                $repositories[] = $new_repo;
                $added_repos[] = array(
                    'index' => count($repositories) - 1,
                    'github_url' => $github_url,
                    'type' => $type,
                    'slug' => $slug,
                    'installed' => false,
                    'active' => false,
                    'webhook' => false
                );
            }
        }
        
        update_option('github_ptd_repositories', $repositories);
        
        wp_send_json_success($added_repos);
    }
    
    /**
     * Auto-install repository after adding
     */
    public function auto_install_repository() {
        check_ajax_referer('github_ptd_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Onvoldoende rechten');
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $repos = get_option('github_ptd_repositories', array());
        
        if (!isset($repos[$index])) {
            wp_send_json_error('Repository niet gevonden');
        }
        
        $repo = $repos[$index];
        
        if (empty($repo['github_url']) || empty($repo['slug'])) {
            wp_send_json_error('Repository gegevens onvolledig');
        }
        
        // Gebruik de webhook class voor installatie
        $webhook = new GitHub_PTD_Webhook();
        $result = $this->manual_install_repository($repo);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Installatie mislukt: ' . $result->get_error_message());
        } else {
            // Automatisch webhook instellen na succesvolle installatie
            $webhook_result = $this->auto_setup_webhook($repo);
            $message = 'Repository succesvol geïnstalleerd';
            
            if (!is_wp_error($webhook_result)) {
                $message .= ' en webhook automatisch ingesteld';
            } else {
                $message .= ' (webhook instellen mislukt: ' . $webhook_result->get_error_message() . ')';
            }
            
            wp_send_json_success($message);
        }
    }
}
