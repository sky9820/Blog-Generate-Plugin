<?php
/**
 * Plugin Name: Blog Content Generator
 * Description: Generate blog content from a string or keywords, preview, edit, and save posts directly from the WordPress admin backend.
 * Version: 1.0
 * Author: Aakash sharma (Dotsquares)
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Text Domain: blog-content-generator
 * Requires at least: 5.8
 * Tested up to: 6.4
 * PHP Version: 7.4
 */


// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class BlogContentGenerator {

	/**
	* Constructor function to initialize the class and set up WordPress hooks.
	*/
    public function __construct() {
        // Add a custom menu item in the WordPress admin panel.
		add_action('admin_menu', [$this, 'add_admin_menu']);

		// Enqueue necessary scripts and styles for the plugin in the admin panel.
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

		// Register an AJAX handler for generating blog content.
		// This is triggered via the 'wp_ajax_generate_blog_content' action in admin-ajax.php.
		add_action('wp_ajax_generate_blog_content', [$this, 'generate_blog_content']);

		// Register an AJAX handler for saving blog posts.
		// This is triggered via the 'wp_ajax_save_blog_post' action in admin-ajax.php.
		add_action('wp_ajax_save_blog_post', [$this, 'save_blog_post']);

		// Register an AJAX handler for saving blog post images.
		// This is triggered via the 'wp_ajax_save_blog_post_image' action in admin-ajax.php.
		add_action('wp_ajax_save_blog_post_image', [$this, 'save_blog_post_image']);
		
		add_action('wp_ajax_save_api_settings', [$this, 'save_api_settings']);
		
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
		
    }
	
	
    public function add_admin_menu() {
        add_menu_page(
            'Blog Content Generator',   // The title of the parent menu page
            'Blog Generator', 			// The label of the parent menu item
            'manage_options',           // The capability required to access the parent menu
            'blog-content-generator',   // The slug of the parent menu
            [$this, 'admin_page'],      // The function to display the parent menu page content  
            'dashicons-format-status',  // The Icon for the parent menu page content
            20
        );
		// Add a submenu item under the "Blog Generator" menu
		add_submenu_page(
			'blog-content-generator', 	// The slug of the parent menu
			'Settings',               	// The title of the submenu page
			'Settings',               	// The label of the submenu item
			'manage_options',         	// The capability required to access the submenu
			'bcg_settings',           	// The slug for the submenu page
			[$this, 'bcg_setting']    	// The function to display the submenu page content
		);
    }

	/**
	* Enqueue scripts and styles for the plugin's admin page.
	*
	* @param string $hook The current admin page hook suffix.
	*/
    public function enqueue_scripts($hook) {
		if ($hook === 'toplevel_page_blog-content-generator' || $hook === 'blog-generator_page_bcg_settings') {
            wp_enqueue_script('blog-generator-script', plugin_dir_url(__FILE__) . 'js/script.js', ['jquery'], '1.0', true);
            wp_enqueue_style('blog-generator-style', plugin_dir_url(__FILE__) . 'css/style.css', [], '1.0');
            wp_localize_script('blog-generator-script', 'ajax_object', [
                'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('blog_generator_nonce'),
            ]);
        }
    }

	/**
	* Render the content for the plugin's admin page.
	*/
    public function admin_page() {
    ?>
		<div class="loading" style="display:none;">Loading&#8230;</div>
		<h1>Blog Content Generator</h1>
		<div class="main-wrapper">
			<div class="wrap">
				<h2>Input Your Blog Content Prompt</h2>
				<div class="form-layout">
					<div class="textarea-container">
					  <textarea placeholder="Enter keywords here..." oninput="autoResize(this)" wrap="soft" id="input-keywords" class="regular-text" style="width: 100%; height: 80px;"></textarea>
					  <button id="generate-content" class="send-button">➤</button>
					</div>
					<em>Your blog writing assistant, ready to create. Turn your ideas into compelling blog posts effortlessly.</em>
				</div>

				<div id="content-preview" style="margin-top: 20px; display: none;">
					<h2>Generated Blog Content:</h2>
					<label for="post_title">Post heading:</label>
					<input type="text" id="post_title" class="regular-text" />
					
					<label for="generated-content">Blog post content:</label>
					
					<?php
					// Render the WordPress Editor
					$editor_id = 'generated-content';
					$editor_content = ''; // You can populate this with default content if needed
					$editor_settings = array(
						'textarea_name' => 'generated-content', // Matches the ID for saving content
						'media_buttons' => true,                // Enable media upload
						'teeny'         => false,               // Full editor
						'quicktags'     => true,                // Enable Quicktags toolbar
					);
					wp_editor($editor_content, $editor_id, $editor_settings);
					?>
					
					<div id="image-preview" style="margin-top: 20px; display: none;">
						<h2 style="font-size: 14px; color: #000;">Image Preview:</h2>
						<img id="keyword-image" src="" alt="Image preview" style="max-width: 400px; border: 1px solid #ccc; border-radius: 5px;">
					</div>
					
					<label for="post-category">Select Category:</label>
					<select id="post-category" name="post-category">
						<option value="">-Choose Category-</option>
						<?php
						$categories = get_categories(['hide_empty' => false, 'exclude' => array(1,5)]);
						foreach ($categories as $category) {
							echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
						}
						?>
					</select>

					<div style="margin-top: 20px; display:flex;">
						<button id="save-post" class="button button-primary">Insert Post</button>
						<input type="hidden" value="template1" id="post_template" />
						<input type="hidden" value="" id="feature_image" />
					</div>
					<div class="info">
						<em>NOTE: Please properly check the preview of post and make corrections in the content before saving.</em>
					</div>
				</div>
			</div>
			
			<div class="wrap preview">
				<h2>Blog Preview</h2>
				<label>
					<input type="radio" name="template" value="template1" checked>
					Template 1
				</label>
				<label>
					<input type="radio" name="template" value="template2">
					Template 2
				</label>
				<label>
					<input type="radio" name="template" value="template3">
					Template 3
				</label>

				<!-- Templates -->
				<div id="template1" class="active">
					<div class="template_one">
						<span>December 19, 2024</span>
						<h2>Sample Post Title</h2>
						<img class="preview_image" src="https://placehold.co/600x400" alt="" />
						<div class="preview_content"></div>
					</div>
				</div>
				<div id="template2">
					<div class="template_one">
						<img class="preview_image" src="https://placehold.co/600x400" alt="" />
						<h2>Sample Post Title</h2>
						<span>December 19, 2024</span>
						<div class="preview_content"></div>
					</div>
				</div>
				<div id="template3">
					<div class="template_one">
						<img class="preview_image" src="https://placehold.co/600x400" alt="" />
						<span>December 19, 2024</span>
						<h2>Sample Post Title</h2>
						<div class="preview_content"></div>
					</div>
				</div>
				
			</div>
		</div>
    <?php
    }
	
	
	/**
	* Render the content for the plugin's admin sub menu page.
	* @description: Function to display the submenu setting page For API configuration
	*/
	public function bcg_setting() {
		?>
		<div class="loading" style="display:none;">Loading&#8230;</div>
		<h1>API Settings</h1>
		<div class="main-wrapper">
			<?php
			// Get the option value
			$custom_setting = get_option('bcg_api_endpoint', '');
			?>
			<div class="wrap">
				<h2>API Configuration:</h2>
				<div id="api-form-wrapper">
					<label for="api_uri">API Endpoint URL:</label>
					<input type="text" id="api_uri" placeholder="Enter your api endpoint" name="api_uri" class="regular-text" value="<?php echo $custom_setting; ?>" />
					<button id="save_settings" name="save_settings" class="button button-primary">Save Settings</button>
				</div>	
			</div>
		</div> 
		<?php
	}

	/**
	* GENERATE BLOG
	*/
    public function generate_blog_content() {
        
		if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'blog_generator_nonce')) {
			wp_send_json_error('Invalid nonce', 403);
		}
		
		if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
		if (empty($keywords)) {
			wp_send_json_error('Keywords are required.');
		}

        // Here you would integrate with an AI API or logic to generate content.
        $generated_content = "Welcome to WordPress. This is your first post. Edit or delete it, then start writing! Welcome to WordPress. This is your first post. Edit or delete it, then start writing! Welcome to WordPress. This is your first post. Edit or delete it, then start writing! Welcome.: $keywords";
		
		$post_title    = 'Post title come here'; //wp_trim_words($content, 10, '');
		
		// Placeholder for image URL based on keywords (You can integrate with an image API here).
        $image_url = site_url().'/wp-content/uploads/2024/12/FleetView_1900x1069.jpg';
        
		$response = [
			'title' => $post_title,
			'content' => $generated_content,
			'image_url' => $image_url, 
		];
		wp_send_json_success($response);
    }

	/**
	* SAVE BLOG POST
	*/
    public function save_blog_post() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
		
		$content 		= isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
		$category_id    = isset($_POST['category']) ? intval($_POST['category']) : 0;
		$template_name  = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
		$post_title 	= isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
		$image_url      = $_POST['postImg'];
		
        $post_id = wp_insert_post([
            'post_title'    => $post_title,
            'post_content'  => $content,
            'post_status'   => 'draft',
            'post_category' => [$category_id]
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to save the post.');
        }
		
		if ($post_id) {
			// Add custom meta field
			$meta_key = 'post_template'; // Replace with your meta key
			$meta_value = $template_name; // Replace with your meta value
			add_post_meta($post_id, $meta_key, $meta_value, true);
			
		}

		$response = [
			'message' => 'SUCCESS: Post saved successfully.',
			'post_id' => $post_id
		];
		wp_send_json_success($response);
    }
	
	/**
	* SAVE BLOG FEATURE IMAGE
	*/
	public function save_blog_post_image() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $post_id   = intval($_POST['post_id']);		
		$image_url = $_POST['postImg'];
		
		if (filter_var($image_url, FILTER_VALIDATE_URL)) {
			
			// Get the WordPress upload directory information
			$upload_dir = wp_upload_dir();

			// Get the file name
			$filename = basename($image_url);

			// Initialize cURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $image_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Optional: Disable SSL verification
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Optional: Disable SSL verification
			$image_data = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($image_data && $http_code == 200) {
				// Create a unique file path in the uploads directory
				$file_path = $upload_dir['path'] . '/' . $filename;

				// Save the file to the uploads directory
				file_put_contents($file_path, $image_data);

				// Insert the file as an attachment
				$attachment_id = wp_insert_attachment([
					'guid'           => $upload_dir['url'] . '/' . $filename,
					'post_mime_type' => mime_content_type($file_path),
					'post_title'     => sanitize_file_name($filename),
					'post_content'   => '',
					'post_status'    => 'inherit',
				], $file_path, $post_id);

				if ($attachment_id) {
					// Generate metadata for the attachment and update it
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
					wp_update_attachment_metadata($attachment_id, $attach_data);

					// Set the attachment as the featured image
					set_post_thumbnail($post_id, $attachment_id);
					wp_send_json_success(['message' => 'SUCCESS: Post saved successfully.', 'post_id' => $post_id]);
				}
			}else{
				wp_send_json_error('Failed to save the post');
			}
		}else{
			wp_send_json_error('Failed to save the post');
		}
	   
    }
	
	/**
	* SAVE API SETTINGS DATA
	*/
	public function save_api_settings(){
		if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'blog_generator_nonce')) {
			wp_send_json_error('Invalid nonce', 403);
		}
		
		if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $api_url = isset($_POST['api_url']) ? sanitize_text_field($_POST['api_url']) : '';
		
		if (empty($api_url)) {
			wp_send_json_error('API Endpoint is required.');
		}
		
		if (!empty($api_url) && filter_var($api_url, FILTER_VALIDATE_URL)) {
			
			// Sanitize the URL (if validation passes)
			$sanitized_url = sanitize_text_field($api_url);
			
			// Update the option in the wp_options table
			update_option('bcg_api_endpoint', $sanitized_url);
			
			$response = [
				'message' => 'SUCCESS: Settings saved successfully.'
			];
			wp_send_json_success($response);
			
		} else {
			// Handle invalid input
			wp_send_json_error('Invalid API Endpoint URL.');
		}
		
	}
	
	// Add settings link in the Plugins list
	public function add_settings_link($links) {
		$settings_link = '<a href="admin.php?page=bcg_settings">Settings</a>';
		array_push($links, $settings_link);
		return $links;
	}
	
}

new BlogContentGenerator();
