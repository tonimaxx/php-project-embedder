<?php
/*
Plugin Name: PHP Project Embedder
Description: Embed and manage external PHP projects within your WordPress theme seamlessly. This plugin allows you to display standalone PHP project files within your WordPress environment, complete with optional WordPress headers and footers, custom CSS styling, and a timestamp display. Perfect for integrating external PHP applications directly into WordPress pages while maintaining a cohesive theme design.
Version: 1.7
Author: Toni Maxx & TM Soontornsing
*/

// Register activation hook to set default options
register_activation_hook(__FILE__, 'php_project_embedder_set_defaults');
function php_project_embedder_set_defaults() {
    if (get_option('php_project_url_path') === false) {
        update_option('php_project_url_path', '/phpproject');
    }
    if (get_option('display_header_footer') === false) {
        update_option('display_header_footer', 1); // Enable by default
    }
    if (get_option('php_project_embedder_custom_css') === false) {
        update_option('php_project_embedder_custom_css', ".php-project-embed {\n    background-color: #f0f0f0;\n    padding: 20px;\n    border-radius: 8px;\n    font-size: 16px;\n    margin: 20px;\n}");
    }
    if (get_option('display_timestamp') === false) {
        update_option('display_timestamp', 1); // Enable timestamp by default
    }
}

// Register activation and deactivation hooks to manage .htaccess rules
register_activation_hook(__FILE__, 'php_project_embedder_activate');
register_deactivation_hook(__FILE__, 'php_project_embedder_deactivate');

function php_project_embedder_activate() {
    php_project_embedder_add_htaccess_rules();
    php_project_embedder_flush_rewrites();
}

function php_project_embedder_deactivate() {
    php_project_embedder_remove_htaccess_rules();
    flush_rewrite_rules();
}

// Add custom rewrite rule based on the settings path
add_action('init', 'php_project_embedder_add_rewrite_rule');
function php_project_embedder_add_rewrite_rule() {
    $relative_path = ltrim(get_option('php_project_url_path', '/phpproject'), '/');
    add_rewrite_rule('^' . $relative_path . '/(.*)$', 'index.php?php_project=$1', 'top');
}

// Flush rewrite rules
function php_project_embedder_flush_rewrites() {
    php_project_embedder_add_rewrite_rule();
    flush_rewrite_rules();
}

// Automatically add .htaccess rules for the specified project path
function php_project_embedder_add_htaccess_rules() {
    // Get the .htaccess file path
    $htaccess_file = get_home_path() . '.htaccess';

    // Get the project path from settings
    $relative_path = ltrim(get_option('php_project_url_path', '/phpproject'), '/');

    // Define each line of the custom rules separately
    $custom_rules = [
        "# BEGIN PHP Project Embedder",
        "RewriteRule ^{$relative_path}/?$ /index.php?php_project=index.php [L]",
        "RewriteRule ^{$relative_path}/index\\.php$ /index.php?php_project=index.php [L]",
        "RewriteRule ^{$relative_path}/(.*)$ /index.php?php_project=\$1 [L]",
        "# END PHP Project Embedder"
    ];

    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        
        // If the custom rules are not in .htaccess, add them
        if (strpos($htaccess_content, '# BEGIN PHP Project Embedder') === false) {
            // Open the .htaccess file in append mode
            $fp = fopen($htaccess_file, 'a');
            if ($fp) {
                // Write each line to the file individually
                foreach ($custom_rules as $line) {
                    fwrite($fp, $line . PHP_EOL);
                }
                fclose($fp);
            }
        }
    }
}

// Remove custom .htaccess rules for the specified project path on deactivation
function php_project_embedder_remove_htaccess_rules() {
    $htaccess_file = get_home_path() . '.htaccess';

    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);

        // Remove the custom rules between # BEGIN PHP Project Embedder and # END PHP Project Embedder
        $htaccess_content = preg_replace('/# BEGIN PHP Project Embedder.*?# END PHP Project Embedder/s', '', $htaccess_content);
        file_put_contents($htaccess_file, $htaccess_content);
    }
}

// Register query variable to capture "php_project"
add_filter('query_vars', 'php_project_embedder_query_vars');
function php_project_embedder_query_vars($vars) {
    $vars[] = 'php_project';
    return $vars;
}

// Template redirect to include the PHP project file if "php_project" is set
add_action('template_redirect', 'php_project_embedder_template_redirect');
function php_project_embedder_template_redirect() {
    if ($file = get_query_var('php_project')) {
        php_project_embedder_include_project($file);
        exit;
    }
}

// Settings page for configuring the project URL path, header/footer toggle, custom CSS, and timestamp toggle
add_action('admin_menu', 'php_project_embedder_settings_page');
function php_project_embedder_settings_page() {
    add_options_page('PHP Project Embedder', 'PHP Project Embedder', 'manage_options', 'php-project-embedder', 'php_project_embedder_settings_page_html');
}

// Settings page HTML with options and descriptions
function php_project_embedder_settings_page_html() {
    if (!current_user_can('manage_options')) return;

    // Check if form was submitted and save the settings
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $relative_path = sanitize_text_field($_POST['php_project_url_path']);
        update_option('php_project_url_path', $relative_path);

        $display_header_footer = isset($_POST['display_header_footer']) ? 1 : 0;
        update_option('display_header_footer', $display_header_footer);

        $display_timestamp = isset($_POST['display_timestamp']) ? 1 : 0;
        update_option('display_timestamp', $display_timestamp);

        $custom_css = sanitize_textarea_field($_POST['custom_css']);
        update_option('php_project_embedder_custom_css', $custom_css);
    }

    // Retrieve current settings for display
    $php_project_url_path = get_option('php_project_url_path');
    $display_header_footer = get_option('display_header_footer');
    $display_timestamp = get_option('display_timestamp');
    $custom_css = get_option('php_project_embedder_custom_css');

    ?>
    <div class="wrap">
        <h1>PHP Project Embedder Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">PHP Project URL Path</th>
                    <td>
                        <input type="text" name="php_project_url_path" value="<?php echo esc_attr($php_project_url_path); ?>" class="regular-text" />
                        <p class="description">Enter the relative URL path where your PHP project is located. For example, <code>/phpproject</code> will make it accessible at <code>https://yoursite.com/phpproject</code>.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Display WordPress Header and Footer</th>
                    <td>
                        <input type="checkbox" name="display_header_footer" value="1" <?php checked(1, $display_header_footer, true); ?> /> Enable
                        <p class="description">Check this box if you want to include the WordPress theme’s header and footer around your PHP project pages.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Display Timestamp</th>
                    <td>
                        <input type="checkbox" name="display_timestamp" value="1" <?php checked(1, $display_timestamp, true); ?> /> Enable
                        <p class="description">Check this box to display the current timestamp before the content of your embedded PHP project.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Custom CSS for .php-project-embed</th>
                    <td>
                        <textarea name="custom_css" rows="5" class="large-text"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description">Add custom CSS to style the embedded PHP project container. This CSS will apply to the <code>.php-project-embed</code> class.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Function to include the specified PHP project file within the theme layout
function php_project_embedder_include_project($file) {
    // Retrieve the relative URL path from settings
    $relative_path = get_option('php_project_url_path', '/phpproject');

    // Convert the relative URL path to an absolute server path
    $project_path = ABSPATH . ltrim($relative_path, '/');

    // Sanitize and ensure the file path is within the project directory
    $file = sanitize_file_name($file);
    $file_path = $project_path . '/' . $file;

    // Retrieve the header/footer and timestamp display settings
    $display_header_footer = get_option('display_header_footer', 1);
    $display_timestamp = get_option('display_timestamp', 1);

    if (file_exists($file_path)) {
        // Change working directory to the project path
        chdir($project_path);
        ob_start();

        // Display timestamp if enabled
        if ($display_timestamp) {
            echo "<p>Timestamp: " . date("Y-m-d H:i:s") . "</p>";
        }

        // Include the requested file
        include $file_path;

        $content = ob_get_clean();

        // Conditionally display the WordPress header and footer based on the setting
        if ($display_header_footer) {
            get_header();
        }

        echo '<div class="php-project-embed">';
        echo $content;
        echo '</div>';

        if ($display_header_footer) {
            get_footer();
        }
    } else {
        // If the file doesn't exist, show a 404 error
        if ($display_header_footer) {
            get_header();
        }
        
        echo '<div class="php-project-embed"><p>File not found.</p></div>';

        if ($display_header_footer) {
            get_footer();
        }
    }
}

// Output the custom CSS in the header
add_action('wp_head', 'php_project_embedder_custom_css');
function php_project_embedder_custom_css() {
    $custom_css = get_option('php_project_embedder_custom_css', '');
    if (!empty($custom_css)) {
        echo '<style type="text/css">' . esc_html($custom_css) . '</style>';
    }
}