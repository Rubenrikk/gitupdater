<?php
/**
 * Plugin Name: GitUpdater - WordPress Auto-Deploy
 * Plugin URI: https://rubenrikk.nl/projects/gitupdater
 * Description: Automatisch WordPress plugins en thema's installeren/bijwerken van private GitHub repository's via push-to-deploy functionaliteit.
 * Version: 2.5.4
 * Author: Rubenrikk
 * Author URI: https://rubenrikk.nl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gitupdater
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.3
 * Requires PHP: 7.4
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Update Checker (PUC) voor self-hosted updates
if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    require __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
}
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Initialiseer PUC voor self-hosted updates
$metaUrl = 'https://rubenrikk.nl/api/wp-plugin-meta'
    . '?owner=Rubenrikk'
    . '&repo=gitupdater'
    . '&slug=gitupdater'
    . '&asset=gitupdater.zip'
    . '&name=GitUpdater'
    . '&tested=6.6'
    . '&requires=5.6'
    . '&requires_php=7.4'
    . '&homepage=' . rawurlencode('https://rubenrikk.nl/projects/gitupdater')
    . '&download_url=' . rawurlencode('https://rubenrikk.nl/api/download/gitupdater?owner=Rubenrikk&asset=gitupdater.zip');

$updateChecker = PucFactory::buildUpdateChecker(
    $metaUrl,
    __FILE__,   // hoofd pluginbestand
    'gitupdater'  // exacte plugin folder/slug
);

// Zorg dat er altijd een geldige download URL is
$updateChecker->addResultFilter(function($pluginInfo) {
    if (empty($pluginInfo) || !is_object($pluginInfo)) {
        return $pluginInfo;
    }
    if (empty($pluginInfo->download_url)) {
        $pluginInfo->download_url = 'https://rubenrikk.nl/api/download/gitupdater?owner=Rubenrikk&asset=gitupdater.zip';
    } elseif (strpos($pluginInfo->download_url, 'http') !== 0) {
        // Converteer relatieve URL naar absolute URL
        $pluginInfo->download_url = 'https://rubenrikk.nl' . $pluginInfo->download_url;
    }
    return $pluginInfo;
});

// Forceer een vaste User-Agent voor alle requests naar rubenrikk.nl API's
add_filter('http_request_args', function ($args, $url) {
    if (strpos($url, 'rubenrikk.nl/api/') !== false) {
        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = array();
        }
        $args['headers']['User-Agent'] = 'Rubenrikk-updatebot';
    }
    return $args;
}, 10, 2);

// Definieer plugin constanten
define('GITHUB_PTD_VERSION', '2.5.4');
define('GITHUB_PTD_PLUGIN_FILE', __FILE__);
define('GITHUB_PTD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GITHUB_PTD_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Hoofdklasse voor GitHub Push-to-Deploy plugin
 */
class GitHub_Push_To_Deploy {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Laad plugin dependencies
     */
    private function load_dependencies() {
        require_once GITHUB_PTD_PLUGIN_DIR . 'includes/class-admin.php';
        require_once GITHUB_PTD_PLUGIN_DIR . 'includes/class-webhook.php';
        require_once GITHUB_PTD_PLUGIN_DIR . 'includes/class-updates.php';
    }
    
    /**
     * Initialiseer hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        
        // Initialiseer componenten
        new GitHub_PTD_Admin();
        new GitHub_PTD_Webhook();
        new GitHub_PTD_Updates();
        
        // Activeren/deactiveren hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Laad textdomain voor vertalingen
     */
    public function load_textdomain() {
        // WordPress 4.6+ handles textdomain loading automatically for WordPress.org hosted plugins
        // This function is kept for backward compatibility but does nothing
        // WordPress will automatically load translations when needed
    }
    
    /**
     * Plugin activatie
     */
    public function activate() {
        // Voeg default opties toe
        $options = get_option('github_ptd_options', array());
        if (empty($options)) {
            update_option('github_ptd_options', array(
                'github_token' => '',
                'webhook_secret' => wp_generate_password(32, false),
                'repositories' => array()
            ));
        }
        
        // Flush rewrite rules voor REST API
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivatie
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialiseer plugin
GitHub_Push_To_Deploy::get_instance();