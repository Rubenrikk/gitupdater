<?php
/**
 * Webhook functionaliteit voor GitHub Push-to-Deploy plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_PTD_Webhook {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Registreer webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('gh-deployer/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'webhook_permission_check'),
            'args' => array(
                'payload' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'GitHub webhook payload'
                )
            )
        ));
    }
    
    /**
     * Webhook permission check met HMAC verificatie
     */
    public function webhook_permission_check($request) {
        // Rate limiting - max 10 webhooks per minuut
        $rate_limit_key = 'github_ptd_webhook_rate_limit';
        $rate_limit = get_transient($rate_limit_key);
        if ($rate_limit && $rate_limit >= 10) {
            $this->log_webhook_error('Rate limit overschreden');
            return false;
        }
        
        // Verhoog rate limit counter
        if ($rate_limit) {
            set_transient($rate_limit_key, $rate_limit + 1, 60);
        } else {
            set_transient($rate_limit_key, 1, 60);
        }
        
        $options = get_option('github_ptd_options', array());
        $webhook_secret = $options['webhook_secret'] ?? '';
        
        if (empty($webhook_secret)) {
            $this->log_webhook_error('Geen webhook secret geconfigureerd');
            return false;
        }
        
        // Controleer X-Hub-Signature-256 header
        $signature = $request->get_header('X-Hub-Signature-256');
        if (!$signature) {
            $this->log_webhook_error('Geen X-Hub-Signature-256 header gevonden');
            return false;
        }
        
        // Verifieer HMAC SHA256 signature
        $payload = $request->get_body();
        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
        
        if (!hash_equals($expected_signature, $signature)) {
            $this->log_webhook_error('Ongeldige webhook signature');
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle GitHub webhook
     */
    public function handle_webhook($request) {
        $event = $request->get_header('X-GitHub-Event');
        $payload = json_decode($request->get_body(), true);
        
        if (!$payload) {
            $this->log_webhook_error('Ongeldige JSON payload');
            return new WP_Error('invalid_payload', 'Ongeldige payload', array('status' => 400));
        }
        
        $this->log_webhook_info('Webhook ontvangen: ' . $event . ' voor repository: ' . ($payload['repository']['full_name'] ?? 'onbekend'));
        
        // Verwerk verschillende event types
        switch ($event) {
            case 'push':
                return $this->handle_push_event($payload);
            case 'release':
                return $this->handle_release_event($payload);
            case 'ping':
                return $this->handle_ping_event($payload);
            default:
                $this->log_webhook_info('Event niet ondersteund: ' . $event);
                return new WP_REST_Response(array('message' => 'Event niet ondersteund'), 200);
        }
    }
    
    /**
     * Handle push event
     */
    private function handle_push_event($payload) {
        $repository = $payload['repository']['full_name'];
        $ref = $payload['ref'];
        $commits = $payload['commits'] ?? array();
        
        $this->log_webhook_info("Push event voor {$repository} op {$ref} met " . count($commits) . " commits");
        
        // Controleer of dit repository geconfigureerd is
        $repo_config = $this->get_repository_config($repository);
        if (!$repo_config) {
            $this->log_webhook_info("Repository {$repository} niet geconfigureerd");
            return new WP_REST_Response(array('message' => 'Repository niet geconfigureerd'), 200);
        }
        
        // Deploy repository
        $result = $this->deploy_repository($repo_config, $ref);
        
        if (is_wp_error($result)) {
            $this->log_webhook_error('Deploy mislukt: ' . $result->get_error_message());
            return new WP_Error('deploy_failed', $result->get_error_message(), array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'message' => 'Deploy succesvol',
            'repository' => $repository,
            'ref' => $ref
        ), 200);
    }
    
    /**
     * Handle release event
     */
    private function handle_release_event($payload) {
        $repository = $payload['repository']['full_name'];
        $release = $payload['release'];
        $tag_name = $release['tag_name'];
        $action = $payload['action'] ?? 'published';
        
        $this->log_webhook_info("Release event voor {$repository}: {$action} tag {$tag_name}");
        
        // Alleen published releases
        if ($action !== 'published') {
            $this->log_webhook_info("Release action '{$action}' genegeerd");
            return new WP_REST_Response(array('message' => 'Release action genegeerd'), 200);
        }
        
        // Controleer of dit repository geconfigureerd is
        $repo_config = $this->get_repository_config($repository);
        if (!$repo_config) {
            $this->log_webhook_info("Repository {$repository} niet geconfigureerd");
            return new WP_REST_Response(array('message' => 'Repository niet geconfigureerd'), 200);
        }
        
        // Deploy repository
        $result = $this->deploy_repository($repo_config, $tag_name);
        
        if (is_wp_error($result)) {
            $this->log_webhook_error('Deploy mislukt: ' . $result->get_error_message());
            return new WP_Error('deploy_failed', $result->get_error_message(), array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'message' => 'Deploy succesvol',
            'repository' => $repository,
            'tag' => $tag_name
        ), 200);
    }
    
    /**
     * Handle ping event (GitHub webhook test)
     */
    private function handle_ping_event($payload) {
        $repository = $payload['repository']['full_name'] ?? 'onbekend';
        $this->log_webhook_info("Ping event ontvangen voor {$repository}");
        return new WP_REST_Response(array('message' => 'Ping ontvangen'), 200);
    }
    
    /**
     * Get repository config
     */
    private function get_repository_config($github_url) {
        $repos = get_option('github_ptd_repositories', array());
        
        foreach ($repos as $repo) {
            if ($repo['github_url'] === $github_url) {
                return $repo;
            }
        }
        
        return null;
    }
    
    /**
     * Deploy repository
     */
    private function deploy_repository($repo_config, $ref) {
        $github_url = $repo_config['github_url'];
        $type = $repo_config['type'];
        $slug = $repo_config['slug'];
        
        $this->log_webhook_info("Start deploy van {$github_url} ({$type}: {$slug}) op {$ref}");
        
        // Download ZIP van GitHub
        $zip_file = $this->download_github_zip($github_url, $ref);
        
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
        
        $this->log_webhook_info("Deploy succesvol voltooid voor {$slug}");
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_webhook_info("Downloaden ZIP van: {$zip_url}");
        }
        
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_webhook_info("ZIP gedownload naar: {$temp_file} (" . size_format(strlen($body)) . ")");
        }
        return $temp_file;
    }
    
    
    /**
     * Log webhook info
     */
    private function log_webhook_info($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_debug_log')) {
            wp_debug_log('[GitHub PTD] ' . $message);
        }
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
        
        $this->log_webhook_info("ZIP uitgepakt naar: {$target_dir}");
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
            $this->log_webhook_info("Verwijderen bestaande plugin: {$slug}");
            $this->recursive_rmdir($target_dir);
        }
        
        // Kopieer nieuwe plugin
        if (!$this->recursive_copy($source_dir, $target_dir)) {
            return new WP_Error('copy_failed', 'Kon plugin bestanden niet kopiëren');
        }
        
        $this->log_webhook_info("Plugin {$slug} succesvol geïnstalleerd/bijgewerkt");
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
                        $this->log_webhook_info("Verwijderen oude plugin versie: {$dir_name}");
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
            $this->log_webhook_info("Verwijderen bestaand thema: {$slug}");
            $this->recursive_rmdir($target_dir);
        }
        
        // Kopieer nieuw thema
        if (!$this->recursive_copy($source_dir, $target_dir)) {
            return new WP_Error('copy_failed', 'Kon thema bestanden niet kopiëren');
        }
        
        $this->log_webhook_info("Thema {$slug} succesvol geïnstalleerd/bijgewerkt");
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
     * Log webhook error
     */
    private function log_webhook_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_debug_log')) {
            wp_debug_log('[GitHub PTD ERROR] ' . $message);
        }
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
}
