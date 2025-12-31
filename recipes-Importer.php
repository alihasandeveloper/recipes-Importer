<?php
/**
 * Plugin Name: Recipe Importer & Visual Mapper
 * Description: A visual recipe scraper that allows users to select data fields visually from any URL.
 * Version: 1.1.0
 * Author: BoomDevs
 */
if (! defined('ABSPATH')) {
    exit;
}
define('RECIPES_IMPORTER_PATH', plugin_dir_path(__FILE__));
define('RECIPES_IMPORTER_URL', plugin_dir_url(__FILE__));
class Recipes_Importer
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_rs_fetch_url', [$this, 'handle_fetch_url']);
        add_action('wp_ajax_rs_create_post', [$this, 'handle_create_post']);
    }
    public function register_admin_menu()
    {
        add_menu_page(
            'Recipe Importer',
            'Recipe Importer',
            'manage_options',
            'recipes-importer',
            [$this, 'render_admin_page'],
            'dashicons-carrot',
            6
        );
    }
    public function enqueue_assets($hook)
    {
        if ('toplevel_page_recipes-importer' !== $hook) {
            return;
        }
        // Enqueue WordPress's built-in Select2
        wp_enqueue_style('select2',  RECIPES_IMPORTER_URL . 'assets/css/select2.min.css', [], '1.1.0');
        wp_enqueue_script('select2', RECIPES_IMPORTER_URL . 'assets/js/select2.min.js', ['jquery'], '4.1.0', true);
        
        wp_enqueue_style('rs-admin-style', RECIPES_IMPORTER_URL . 'assets/css/admin.css', [], '1.1.0');
        wp_enqueue_script('rs-admin-script', RECIPES_IMPORTER_URL . 'assets/js/admin.js', ['jquery', 'select2'], '1.1.0', true);
        wp_localize_script('rs-admin-script', 'rsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rs_fetch_nonce')
        ]);
    }
    public function render_admin_page()
    {
?>
        <div class="wrap rs-wrapper">
            <h1>Recipe Importer & Visual Mapper</h1>
            <!-- Setup Step -->
            <div class="rs-step-1">
                <div class="rs-input-group">
                    <input type="url" id="rs-target-url" placeholder="Enter Recipe URL (e.g. https://www.wellplated.com/shrimp-tacos/)" class="regular-text" style="width: 100%; max-width: 600px;">
                    <button id="rs-fetch-btn" class="button button-primary">Load Visual Editor</button>
                    <span class="spinner"></span>
                </div>
                <p class="description">Enter a recipe URL to begin. The visual editor will load the page so you can select data fields by clicking.</p>
            </div>
            <!-- Visual Editor Area (Hidden initially) -->
            <div id="rs-visual-editor" style="display:none;">
                <div class="rs-sidebar">
                    <h3>Map Fields</h3>
                    <p class="description">Click a button, then click the element on the right.</p>
                    <div class="rs-field-group">
                        <label>Recipe Title <span class="required">*</span></label>
                        <div class="rs-input-row">
                            <input type="text" id="rs-field-title" readonly placeholder="CSS Selector">
                            <button class="button rs-select-btn" data-field="title">Select</button>
                        </div>
                        <div class="rs-field-preview" id="rs-preview-title"></div>
                    </div>
                    <div class="rs-field-group">
                        <label>Description</label>
                        <div class="rs-input-row">
                            <input type="text" id="rs-field-description" readonly placeholder="CSS Selector (multiple selections will be combined)">
                            <button class="button rs-select-btn" data-field="description">Select</button>
                        </div>
                        <div class="rs-field-preview" id="rs-preview-description"></div>
                    </div>
                    <div class="rs-field-group">
                        <label>Featured Image</label>
                        <div class="rs-input-row">
                            <input type="text" id="rs-field-image" readonly placeholder="CSS Selector">
                            <button class="button rs-select-btn" data-field="image">Select</button>
                        </div>
                        <div class="rs-field-preview" id="rs-preview-image"></div>
                    </div>
                    <div class="rs-field-group">
                        <label>Total time</label>
                        <div style="margin-bottom: 8px;">
                            <label style="display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="rs-time-custom-toggle" style="margin-right: 5px;">
                                <span>Use Custom Value</span>
                            </label>
                        </div>
                        <div id="rs-time-scrape-mode">
                            <div class="rs-input-row">
                                <input type="text" id="rs-field-time" readonly placeholder="CSS Selector">
                                <button class="button rs-select-btn" data-field="time">Select</button>
                            </div>
                            <div class="rs-field-preview" id="rs-preview-time"></div>
                        </div>
                        <div id="rs-time-custom-mode" style="display: none;">
                            <div class="rs-input-row">
                                <input type="text" id="rs-field-time-custom" class="regular-text" placeholder="e.g., 30 minutes" style="width: 100%;">
                            </div>
                        </div>
                    </div>
                    <div class="rs-field-group">
                        <label>Ingredient count</label>
                        <div style="margin-bottom: 8px;">
                            <label style="display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="rs-ingredients-custom-toggle" style="margin-right: 5px;">
                                <span>Use Custom Value</span>
                            </label>
                        </div>
                        <div id="rs-ingredients-scrape-mode">
                            <div class="rs-input-row">
                                <input type="text" id="rs-field-ingredients" readonly placeholder="CSS Selector (Container)">
                                <button class="button rs-select-btn" data-field="ingredients">Select</button>
                            </div>
                            <div class="rs-field-preview" id="rs-preview-ingredients"></div>
                            <small class="description">Select the container that holds all ingredient items</small>
                        </div>
                        <div id="rs-ingredients-custom-mode" style="display: none;">
                               <div class="rs-input-row">
                                   <input type="text" id="rs-field-ingredients-custom" class="regular-text" placeholder="Enter ingredients" style="width: 100%;">
                               </div>
                        </div>
                    </div>
                    
                    <div class="rs-field-group">
                        <label>Step count</label>
                        <div style="margin-bottom: 8px;">
                            <label style="display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="rs-steps-custom-toggle" style="margin-right: 5px;">
                                <span>Use Custom Value</span>
                            </label>
                        </div>
                        <div id="rs-steps-scrape-mode">
                            <div class="rs-input-row">
                                <input type="text" id="rs-field-steps" readonly placeholder="CSS Selector (Container)">
                                <button class="button rs-select-btn" data-field="steps">Select</button>
                            </div>
                            <div class="rs-field-preview" id="rs-preview-steps"></div>
                            <small class="description">Select the container that holds all step items</small>
                        </div>
                        <div id="rs-steps-custom-mode" style="display: none;">
                            <div class="rs-input-row">
                                <input type="text" id="rs-field-steps-custom" class="regular-text" placeholder="Enter steps" style="width: 100%;">
                            </div>
                        </div>
                    </div>

                    <div class="rs-field-group"  style="display: flex; align-items: center; gap: 10px">
                        <label style="margin-bottom: 0">ADHD friendly</label>
                        <div class="rs-input-row" style="display: block;">
                            <input type="checkbox" id="rs-field-adhd-friendly">
                        </div>
                    </div>

                      <div class="rs-field-group" style="display: flex; align-items: center; gap: 10px">
                        <label style="margin-bottom: 0">Kid friendly</label>
                        <div class="rs-input-row" style="display: block;">
                            <input type="checkbox" id="rs-field-kid-friendly">
                        </div>
                    </div>

                      <div class="rs-field-group" style="display: flex; align-items: center; gap: 10px">
                        <label style="margin-bottom: 0">Pantry friendly</label>
                        <div class="rs-input-row" style="display: block;">
                            <input type="checkbox" id="rs-field-pantry-friendly">
                        </div>
                    </div>


                    <div class="rs-field-group" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none">
                        <?php
                        // Get all taxonomies registered for the 'recipe' post type
                        $recipe_taxonomies = get_object_taxonomies('recipe', 'objects');
                        
                        if (!empty($recipe_taxonomies)) {
                            foreach ($recipe_taxonomies as $taxonomy) {
                                // Only show hierarchical taxonomies
                                if ($taxonomy->hierarchical) {
                                    ?>
                                    <div class="rs-taxonomy-group" style="margin-bottom: 15px;">
                                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <?php echo esc_html($taxonomy->label); ?>
                                        </label>
                                        <?php
                                        // Get all terms for this taxonomy
                                        $terms = get_terms([
                                            'taxonomy' => $taxonomy->name,
                                            'hide_empty' => false,
                                            'hierarchical' => true,
                                            'orderby' => 'name',
                                            'order' => 'ASC'
                                        ]);
                                        
                                        if (!empty($terms) && !is_wp_error($terms)) {
                                            ?>
                                            <select class="rs-taxonomy-select" name="rs_taxonomies[<?php echo esc_attr($taxonomy->name); ?>][]" multiple="multiple" style="width: 100%;">
                                                <?php
                                                $this->display_select_options($terms, 0);
                                                ?>
                                            </select>
                                            <?php
                                        } else {
                                            echo '<p style="margin: 0; color: #999; font-style: italic;">No terms available</p>';
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                            }
                        } else {
                            echo '<p class="description">No taxonomies found for recipe post type. Make sure the recipe post type is registered.</p>';
                        }
                        ?>
                    </div>
                    
                    <hr>
                    <button id="rs-save-mapping" class="button button-large button-primary">Create Recipe</button>
                    <div id="rs-result-output"></div>
                </div>
                <div class="rs-preview-window">
                    <div class="rs-preview-header">
                        <span id="rs-current-mode">Mode: View</span>
                        <span class="rs-preview-url" id="rs-preview-url"></span>
                    </div>
                    <iframe id="rs-site-frame" sandbox="allow-scripts"></iframe>
                </div>
            </div>
        </div>
<?php
    }
    public function handle_fetch_url()
    {
        check_ajax_referer('rs_fetch_nonce', 'nonce');
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }
        // Fetch the remote content
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch URL: ' . $response->get_error_message());
        }
        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            wp_send_json_error('No content received from URL');
        }
        // Inject a base tag so relative links/images work
        $base_tag = '<base href="' . esc_url($url) . '" target="_blank">';
        $html = preg_replace('/<head[^>]*>/i', '$0' . $base_tag, $html);
        // Inject our custom CSS/JS for the iframe to handle selection
        $selector_script = "
        <style>
            .rs-hovered { outline: 3px solid #e14d43 !important; cursor: crosshair !important; background: rgba(225, 77, 67, 0.1) !important; }
            .rs-selected { outline: 3px solid #00a32a !important; background: rgba(0, 163, 42, 0.1) !important; }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Disable all links and form submissions
                document.querySelectorAll('a').forEach(a => {
                    a.addEventListener('click', e => e.preventDefault());
                });
                
                document.querySelectorAll('form').forEach(form => {
                    form.addEventListener('submit', e => e.preventDefault());
                });
                let activeMode = false;
                let currentField = null;
                
                window.addEventListener('message', function(event) {
                    if (event.data.action === 'startSelect') {
                        activeMode = true;
                        currentField = event.data.field || null;
                        document.body.style.cursor = 'crosshair';
                    } else if (event.data.action === 'stopSelect') {
                        activeMode = false;
                        currentField = null;
                        document.body.style.cursor = 'default';
                        document.querySelectorAll('.rs-hovered, .rs-selected').forEach(el => {
                            el.classList.remove('rs-hovered', 'rs-selected');
                        });
                    }
                });
                document.body.addEventListener('mouseover', function(e) {
                    if (!activeMode) return;
                    e.target.classList.add('rs-hovered');
                    e.stopPropagation();
                });
                document.body.addEventListener('mouseout', function(e) {
                    if (!activeMode) return;
                    e.target.classList.remove('rs-hovered');
                    e.stopPropagation();
                });
                document.body.addEventListener('click', function(e) {
                    if (!activeMode) return;
                    e.preventDefault();
                    e.stopPropagation();
                    
                    /**
                     * Enhanced selector generation with nth-child support
                     */
                    function getEnhancedSelector(el) {
                        // If element has unique ID, use it
                        if (el.id) {
                            return '#' + el.id;
                        }
                        
                        let path = [];
                        let current = el;
                        
                        while (current && current.nodeType === Node.ELEMENT_NODE && current.tagName !== 'BODY') {
                            let selector = current.tagName.toLowerCase();
                            
                            // Check for unique class combinations
                            if (current.className && typeof current.className === 'string') {
                                let classes = current.className.split(' ')
                                    .filter(c => c.trim() !== '' && !c.startsWith('rs-'))
                                    .join('.');
                                if (classes) {
                                    selector += '.' + classes;
                                    
                                    // Test if this selector is unique
                                    try {
                                        if (document.querySelectorAll(selector).length === 1) {
                                            path.unshift(selector);
                                            break;
                                        }
                                    } catch(err) {}
                                }
                            }
                            
                            // Add nth-child if needed for uniqueness
                            if (current.parentElement) {
                                let siblings = Array.from(current.parentElement.children);
                                let sameTagSiblings = siblings.filter(s => s.tagName === current.tagName);
                                
                                if (sameTagSiblings.length > 1) {
                                    let index = sameTagSiblings.indexOf(current) + 1;
                                    selector += ':nth-child(' + index + ')';
                                }
                            }
                            
                            path.unshift(selector);
                            current = current.parentElement;
                            
                            // Limit depth to avoid overly long selectors
                            if (path.length >= 4) break;
                        }
                        
                        return path.join(' > ');
                    }
                    let el = e.target;
                    let selector = getEnhancedSelector(el);
                    
                    // Get preview data
                    let previewText = el.innerText ? el.innerText.substring(0, 100) : '';
                    let previewHTML = el.innerHTML ? el.innerHTML.substring(0, 200) : '';
                    
                    // For containers (ingredients, steps), count children
                    let childCount = 0;
                    if (currentField === 'ingredients' || currentField === 'steps') {
                        // Count direct children or list items
                        let children = el.querySelectorAll('li, div, p');
                        childCount = children.length;
                    }
                    // Send data back to parent
                    window.parent.postMessage({
                        action: 'elementSelected',
                        field: currentField,
                        selector: selector,
                        tag: el.tagName,
                        text: previewText,
                        src: el.src || el.getAttribute('data-src') || el.getAttribute('data-lazy-src') || '',
                        html: previewHTML,
                        childCount: childCount
                    }, '*');
                    
                    // Visual feedback
                    document.querySelectorAll('.rs-selected').forEach(s => s.classList.remove('rs-selected'));
                    el.classList.add('rs-selected');
                    el.classList.remove('rs-hovered');
                });
            });
        </script>
        ";
        // Insert script before closing body
        $html = str_replace('</body>', $selector_script . '</body>', $html);
        wp_send_json_success(['html' => $html]);
    }
    public function handle_create_post()
    {
        check_ajax_referer('rs_fetch_nonce', 'nonce');
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : [];
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }
        
        if (empty($mapping) || empty($mapping['title'])) {
            wp_send_json_error('Please select at least a Title field');
        }
        
        // 1. Check for duplicates (normalize URL for comparison)
        $normalized_url = untrailingslashit($url);
        
        $args = [
            'post_type'   => 'recipe',
            'meta_key'    => 'source-url',
            'meta_value'  => $normalized_url,
            'post_status' => 'any',
            'numberposts' => 1
        ];
        $existing = get_posts($args);
        
        if (!empty($existing)) {
            $edit_link = get_edit_post_link($existing[0]->ID, 'raw');
            wp_send_json_error([
                'message'      => 'A recipe from this URL already exists!',
                'redirect_url' => $edit_link,
                'is_duplicate' => true
            ]);
        }
        
        // 2. Fetch Content
        $response = wp_remote_get($url, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch content: ' . $response->get_error_message());
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            wp_send_json_error('No content received from URL');
        }
        
        // 3. Scrape using DOMDocument
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        // Modern PHP-compatible encoding handling
        $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
        @$dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $data = [];
        $errors = [];
        
        $custom_values = isset($_POST['customValues']) ? $_POST['customValues'] : [];
        $checkboxes = isset($_POST['checkboxes']) ? $_POST['checkboxes'] : [];
        
        // Handle custom values
        if (!empty($custom_values['time'])) {
            $data['time'] = $custom_values['time'];
        }
        
        if (!empty($custom_values['ingredients'])) {
            $data['ingredients_count'] = wp_kses_post($custom_values['ingredients']);
        }
        
        if (!empty($custom_values['steps'])) {
            $data['steps_count'] = wp_kses_post($custom_values['steps']);
        }
        
        foreach ($mapping as $key => $css_selector) {
            if (empty($css_selector)) continue;
            
            $xpath_query = $this->css_to_xpath($css_selector);
            
            if (empty($xpath_query)) {
                $errors[] = "Invalid selector for field: $key";
                continue;
            }
            
            $nodes = @$xpath->query($xpath_query);
            
            if (!$nodes || $nodes->length === 0) {
                $errors[] = "No elements found for field: $key (selector: $css_selector)";
                continue;
            }
            
            if ($key === 'image') {
                $node = $nodes->item(0);
                
                // Check multiple image source attributes
                $src = $node->getAttribute('src');
                $data_src = $node->getAttribute('data-src');
                $data_lazy = $node->getAttribute('data-lazy-src');
                $srcset = $node->getAttribute('srcset');
                
                // Priority: data-src > data-lazy-src > src > srcset
                if (!empty($data_src)) {
                    $src = $data_src;
                } elseif (!empty($data_lazy)) {
                    $src = $data_lazy;
                } elseif (!empty($srcset)) {
                    // Extract first URL from srcset
                    $srcset_parts = explode(',', $srcset);
                    if (!empty($srcset_parts[0])) {
                        $src = trim(explode(' ', trim($srcset_parts[0]))[0]);
                    }
                }
                
                $data[$key] = $src;
                
                // Handle relative URLs
                if ($data[$key] && strpos($data[$key], 'http') !== 0) {
                    $parsed_url = parse_url($url);
                    $base = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                    
                    if (strpos($data[$key], '/') === 0) {
                        $data[$key] = $base . $data[$key];
                    } else {
                        $path = isset($parsed_url['path']) ? dirname($parsed_url['path']) : '';
                        $data[$key] = $base . $path . '/' . $data[$key];
                    }
                }
                
            } elseif ($key === 'ingredients' || $key === 'steps') {
                // Store both HTML content and count
                $html_content = '';
                $child_elements = [];
                
                foreach ($nodes as $node) {
                    $node_html = $dom->saveHTML($node);
                    $html_content .= $node_html . "\n";
                    
                    // Count child elements (li, div, p, etc.)
                    $children = $xpath->query('.//*[self::li or self::div or self::p]', $node);
                    if ($children && $children->length > 0) {
                        $child_elements[] = $children->length;
                    }
                }
                
                $data[$key . '_html'] = $html_content;
                $data[$key . '_count'] = !empty($child_elements) ? max($child_elements) : $nodes->length;
                
            } elseif ($key === 'description') {
                // Handle multiple description selectors (comma-separated)
                $combined_text = '';
                $selectors = array_map('trim', explode(',', $css_selector));
                
                foreach ($selectors as $single_selector) {
                    if (empty($single_selector)) continue;
                    
                    $single_xpath = $this->css_to_xpath($single_selector);
                    if (!empty($single_xpath)) {
                        $single_nodes = @$xpath->query($single_xpath);
                        if ($single_nodes && $single_nodes->length > 0) {
                            $text = trim($single_nodes->item(0)->textContent);
                            if (!empty($text)) {
                                $combined_text .= $text . "\n\n";
                            }
                        }
                    }
                }
                
                $data[$key] = trim($combined_text);
            } else {
                $data[$key] = trim($nodes->item(0)->textContent);
            }
        }
        
        // Validate scraped data
        if (empty($data['title'])) {
            wp_send_json_error('Failed to extract recipe title. Please verify your selection.');
        }
        
        // 4. Create Post
        $post_data = [
            'post_title'   => wp_strip_all_tags($data['title']),
            'post_content' => !empty($data['description']) ? $data['description'] : '',
            'post_status'  => 'publish',
            'post_type'    => 'recipe'
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create post: ' . $post_id->get_error_message());
        }
        
        // 5. Save Meta Fields
        update_post_meta($post_id, 'source-url', $normalized_url);
        
        if (!empty($data['title'])) {
            update_post_meta($post_id, 'display-title', sanitize_text_field($data['title']));
        }
        
        if (!empty($data['time'])) {
            update_post_meta($post_id, 'total-time', sanitize_text_field($data['time']));
        }
        
        // Store ingredients HTML and count separately
        if (!empty($data['ingredients_html'])) {
            update_post_meta($post_id, 'ingredients-html', wp_kses_post($data['ingredients_html']));
        }
        if (isset($data['ingredients_count'])) {
            update_post_meta($post_id, 'ingredient-count', absint($data['ingredients_count']));
        }
        
        // Store steps HTML and count separately
        if (!empty($data['steps_html'])) {
            update_post_meta($post_id, 'steps-html', wp_kses_post($data['steps_html']));
        }
        if (isset($data['steps_count'])) {
            update_post_meta($post_id, 'step-count', absint($data['steps_count']));
        }
        
        // 6. Assign Taxonomy Terms
        if (!empty($_POST['taxonomies']) && is_array($_POST['taxonomies'])) {
            foreach ($_POST['taxonomies'] as $taxonomy => $term_ids) {
                if (is_array($term_ids) && taxonomy_exists($taxonomy)) {
                    // Convert term IDs to integers
                    $term_ids = array_map('intval', $term_ids);
                    
                    // Assign terms to the post
                    wp_set_object_terms($post_id, $term_ids, $taxonomy);
                }
            }
        }
        
        // 7. Save Checkbox Meta Fields
        if (!empty($checkboxes['adhd-friendly']) && $checkboxes['adhd-friendly'] === 'true') {
            update_post_meta($post_id, 'adhd-friendly', 'ADHD friendly');
        }
        
        if (!empty($checkboxes['kid-friendly']) && $checkboxes['kid-friendly'] === 'true') {
            update_post_meta($post_id, 'kid-friendly', 'Kid friendly');
        }
        
        if (!empty($checkboxes['pantry-friendly']) && $checkboxes['pantry-friendly'] === 'true') {
            update_post_meta($post_id, 'pantry-friendly', 'Pantry friendly');
        }
        
        // 8. Handle Featured Image
        $image_error = null;
        if (!empty($data['image'])) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $img_url = $data['image'];
            $desc = "Featured image for " . $post_data['post_title'];
            
            // Set custom user agent for image download
            add_filter('http_request_args', function ($args) {
                $args['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
                return $args;
            }, 10, 1);
            
            $img_id = media_sideload_image($img_url, $post_id, $desc, 'id');
            
            if (!is_wp_error($img_id)) {
                set_post_thumbnail($post_id, $img_id);
            } else {
                $image_error = $img_id->get_error_message();
            }
        }
        
        // Prepare success message
        $message = 'Recipe post created successfully!';
        if (!empty($errors)) {
            $message .= ' Note: Some fields could not be extracted: ' . implode(', ', $errors);
        }
        if ($image_error) {
            $message .= ' Image download failed: ' . $image_error;
        }
        
        wp_send_json_success([
            'message'      => $message,
            'redirect_url' => get_edit_post_link($post_id, 'raw'),
            'post_id'      => $post_id,
            'warnings'     => !empty($errors) || $image_error
        ]);
    }
    /**
     * Enhanced CSS to XPath converter with comprehensive selector support
     */
    private function css_to_xpath($css_selector)
    {
        if (empty($css_selector)) {
            return '';
        }
        
        $css_selector = trim($css_selector);
        
        // Split by comma for multiple selectors (OR logic)
        $selector_groups = array_map('trim', explode(',', $css_selector));
        $xpath_parts = [];
        
        foreach ($selector_groups as $selector_group) {
            if (empty($selector_group)) continue;
            
            // Handle direct child combinator (>)
            $has_child_combinator = strpos($selector_group, '>') !== false;
            
            if ($has_child_combinator) {
                $parts = array_map('trim', explode('>', $selector_group));
                $xpath = '//' . $this->parse_css_segment($parts[0]);
                
                for ($i = 1; $i < count($parts); $i++) {
                    $xpath .= '/' . $this->parse_css_segment($parts[$i]);
                }
            } else {
                // Handle descendant combinator (space)
                $parts = preg_split('/\s+/', $selector_group);
                $xpath_segments = [];
                
                foreach ($parts as $part) {
                    if (empty($part)) continue;
                    $xpath_segments[] = $this->parse_css_segment($part);
                }
                
                $xpath = '//' . implode('//', $xpath_segments);
            }
            
            $xpath_parts[] = $xpath;
        }
        
        // Combine multiple selector groups with pipe (OR)
        return implode(' | ', $xpath_parts);
    }
    
    /**
     * Parse a single CSS selector segment into XPath
     */
    private function parse_css_segment($segment)
    {
        $segment = trim($segment);
        
        // Extract tag name
        $tag = '*';
        if (preg_match('/^([a-zA-Z0-9]+)/', $segment, $matches)) {
            $tag = $matches[0];
            $segment = substr($segment, strlen($tag));
        }
        
        $conditions = [];
        
        // Parse ID (#id)
        if (preg_match('/#([a-zA-Z0-9_-]+)/', $segment, $matches)) {
            $conditions[] = "@id='" . $matches[1] . "'";
            $segment = str_replace($matches[0], '', $segment);
        }
        
        // Parse classes (.class)
        if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $segment, $matches)) {
            foreach ($matches[1] as $class) {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
            }
            $segment = preg_replace('/\.([a-zA-Z0-9_-]+)/', '', $segment);
        }
        
        // Parse attribute selectors [attr] or [attr="value"]
        if (preg_match_all('/\[([a-zA-Z0-9_-]+)(?:=["\']?([^"\'\]]+)["\']?)?\]/', $segment, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $attr = $matches[1][$i];
                $value = isset($matches[2][$i]) && !empty($matches[2][$i]) ? $matches[2][$i] : null;
                
                if ($value !== null) {
                    $conditions[] = "@$attr='$value'";
                } else {
                    $conditions[] = "@$attr";
                }
            }
            $segment = preg_replace('/\[([a-zA-Z0-9_-]+)(?:=["\']?([^"\'\]]+)["\']?)?\]/', '', $segment);
        }
        
        // Parse pseudo-selectors
        if (preg_match('/:nth-child\((\d+)\)/', $segment, $matches)) {
            $position = $matches[1];
            $conditions[] = "position()=$position";
        } elseif (strpos($segment, ':first-child') !== false) {
            $conditions[] = "position()=1";
        } elseif (strpos($segment, ':last-child') !== false) {
            $conditions[] = "position()=last()";
        }
        
        // Build XPath
        $xpath = $tag;
        if (!empty($conditions)) {
            $xpath .= '[' . implode(' and ', $conditions) . ']';
        }
        
        return $xpath;
    }
    
    /**
     * Display hierarchical taxonomy terms as select options
     */
    private function display_select_options($terms, $parent = 0, $level = 0) {
        // Build a hierarchical array
        $children = [];
        foreach ($terms as $term) {
            if ($term->parent == $parent) {
                $children[] = $term;
            }
        }
        
        if (empty($children)) {
            return;
        }
        
        foreach ($children as $term) {
            $indent = str_repeat('—', $level);
            echo '<option value="' . esc_attr($term->term_id) . '">';
            echo $indent . ' ' . esc_html($term->name) . ' (' . $term->count . ')';
            echo '</option>';
            
            // Recursively display children
            $this->display_select_options($terms, $term->term_id, $level + 1);
        }
    }
}
new Recipes_Importer();