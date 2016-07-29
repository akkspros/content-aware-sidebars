<?php
/**
 * @package Content Aware Sidebars
 * @author Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 * @copyright 2016 by Joachim Jensen
 */

if (!defined('CAS_App::DOMAIN')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

class CAS_Post_Type_Sidebar {

	const MODULE_NAME = "post_type";

	private $_theme_sidebars = array();

	/**
	 * Sidebar cache for post types
	 * @var array
	 */
	private $_sidebars_for_content = array();

	public function __construct(){
		add_action("admin_init",
			array($this,"initiate"));
		add_action('after_setup_theme',function() {
			if(is_admin()) {
				global $wp_registered_sidebars;
				foreach($wp_registered_sidebars as $sidebar) {
					$this->_theme_sidebars[$sidebar['id']] = array(
						'label' => $sidebar['name'],
						'options' => array(),
						'select' => array()
					);
				}
			}
		},98);
	}

	public function initiate() {
		$module = WPCACore::modules()->get(self::MODULE_NAME);
		if($this->_theme_sidebars && $module) {
			foreach ($module->_post_types()->get_all() as $post_type) {
				add_action('add_meta_boxes_'.$post_type->name,
					array($this,'create_meta_boxes'));
				add_action("save_post_".$post_type->name,
					array($this,"save_post_sidebars"),10,2);
			}
		}
	}

	/**
	 * Save sidebars and conditions for post
	 *
	 * @since  3.3
	 * @param  int      $post_id
	 * @param  WP_Post  $post
	 * @return void
	 */
	public function save_post_sidebars($post_id, $post) {

		// Save button pressed
		if (!isset($_POST['original_publish']) && !isset($_POST['save_post']))
			return;
		
		// Check post permissions
		if (!current_user_can('edit_post', $post_id))
			return;

		if($post->post_status == 'auto-draft') {
			return;
		}

		// Check autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return;

		$meta_key = WPCACore::PREFIX . self::MODULE_NAME;
		$new = isset($_POST['sidebars']) ? $_POST["sidebars"] : array();

		$relations = array();
		foreach($this->_get_content_sidebars(array($post_id)) as $relation) {
			$relations[$relation->ID] = $relation->group_id;
		}

		$user_can_create_sidebar = current_user_can(CAS_App::CAPABILITY);

		foreach ($new as $host => $sidebar_id_string) {
			$sidebar_ids = explode(",", $sidebar_id_string);
			foreach ($sidebar_ids as $sidebar_id) {
				//Post has sidebar already
				if(isset($relations[$sidebar_id])) {
					unset($relations[$sidebar_id]);
				//Discard empty
				} elseif($sidebar_id) {

					$condition_group_id = 0;
					
					//New sidebar
					//check permissions here
					if($sidebar_id[0] == "_") {
						if($user_can_create_sidebar) {
							$id = wp_insert_post(array(
								'post_title'  => str_replace("_",",",substr($sidebar_id,1)),
								'post_status' => "draft", 
								'post_type'   => CAS_App::TYPE_SIDEBAR
							));
							if($id) {
								//wp_insert_post does not handle meta before WP4.4
								add_post_meta($id, WPCACore::PREFIX.'host', $host);
								$condition_group_id = WPCACore::add_condition_group($id);
							}
						}
					} else {
						//Add post to group with other posts
						$id = intval($sidebar_id);
						$condition_groups = get_posts(array(
							'posts_per_page'   => 1,
							'meta_key'         => $meta_key,
							'meta_value'       => $post->post_type,
							'meta_compare'     => "!=",
							'post_parent'      => $id,
							'post_type'        => WPCACore::TYPE_CONDITION_GROUP,
							'post_status'      => WPCACore::STATUS_PUBLISHED
						));
						if($condition_groups) {
							$condition_group_id = $condition_groups[0]->ID;
						} else {
							$condition_group_id = WPCACore::add_condition_group($id);
						}
					}

					if($condition_group_id) {
						add_post_meta($condition_group_id, $meta_key, $post_id);
						//add_post_meta($condition_group_id, $meta_key."_direct", 1);
					}
				}
			}
		}

		//remove old relations
		//todo: sanity check if post is added to several groups?
		foreach ($relations as $sidebar_id => $group_id) {
			//group with no post_type meta will be removed
			//even if it has other meta (unlikely)
			$group_meta = get_post_meta($group_id, $meta_key);
			if(count($group_meta) <= 1) {
				wp_delete_post($group_id);
			} else {
				delete_post_meta($group_id, $meta_key, $post_id);
			}
		}
	}

	/**
	 * Create sidebar meta box for post types
	 *
	 * @since  3.3
	 * @param  WP_Post  $post
	 * @return void
	 */
	public function create_meta_boxes($post) {
		add_meta_box(
			'cas-content-sidebars',
			__('Sidebars - Quick Select','content-aware-sidebars'),
			array($this, 'render_sidebars_metabox'),
			$post->post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render sidebar metabox for post types
	 *
	 * @since  3.3
	 * @param  WP_Post  $post
	 * @return void
	 */
	public function render_sidebars_metabox($post) {

		$module = WPCACore::modules()->get(self::MODULE_NAME);
		
		$post_sidebars = array();
		foreach($this->_get_content_sidebars(array($post->ID)) as $sidebar) {
			$post_sidebars[$sidebar->ID] = $sidebar->ID;
		}

		$sidebars = CAS_App::instance()->manager()->sidebars;
		
		$host_meta = CAS_App::instance()->manager()->metadata()->get('host');
		foreach ($sidebars as $sidebar) {
			$host_id = $host_meta->get_data($sidebar->ID);
			if(isset($this->_theme_sidebars[$host_id])) {
				$this->_theme_sidebars[$host_id]['options'][$sidebar->ID] = array(
					"id" => $sidebar->ID,
					"text" => $sidebar->post_title.$module->_post_states($sidebar)
				);
			}
			if(isset($post_sidebars[$sidebar->ID])) {
				$this->_theme_sidebars[$host_id]['select'][$sidebar->ID] = $sidebar->ID;
			}
		}

		//todo: enqueue automatically in wpcacore
		if(wp_script_is("select2","registered")) {
			wp_deregister_script("select2");
		}

		wp_register_script(
			'select2',
			plugins_url('/lib/wp-content-aware-engine/assets/js/select2.min.js',dirname(__FILE__)),
			array('jquery'),
			'3.5.4',
			false
		);
		wp_enqueue_style(
			WPCACore::PREFIX.'condition-groups',
			plugins_url('/lib/wp-content-aware-engine/assets/css/condition_groups.css', dirname(__FILE__)),
			array(),
			WPCACore::VERSION
		);
		wp_enqueue_script('cas/sidebars/suggest', plugins_url('/js/suggest-sidebars.min.js', dirname(__FILE__)), array('select2'), CAS_App::PLUGIN_VERSION, true);
		wp_localize_script('cas/sidebars/suggest', 'CAS', array(
			'createNew' => __('Create New'),
			'labelNew' => __('New'),
			'notFound' => __('Type to Add New Sidebar')
		));

		$post_type = get_post_type_object($post->post_type);
		$content = array(
			__('Author')
		);
		if($post_type->hierarchical) {
			$content[] = __('Child Page');
		}
		if($post_type->name == 'page') {
			$content[] = __('Page Template');
		}
		$taxonomies = get_object_taxonomies($post,'objects');
		if($taxonomies) {
			foreach ($taxonomies as $tax) {
				$content[] = $tax->labels->singular_name;
			}
		}
		$content[] = __('Archive Page');

		$content = array_slice($content, 0, 3);

		$link = '<a href="'.admin_url('edit.php?post_type='.CAS_App::TYPE_SIDEBAR).'">'.__('Sidebar Manager').'</a>';

		foreach ($this->_theme_sidebars as $id => $sidebar) {
			echo '<div><label style="display:block;padding:8px 0 4px;font-weight:bold;" for="ca_sidebars_'.$id.'">'.$sidebar['label'].'</label>';

			echo '<input id="ca_sidebars_'.$id.'" class="js-cas-sidebars" type="text" name="sidebars['.$id.']" value="'.implode(",", $sidebar['select']).'" placeholder="'.__('Default').'" data-sidebars=\''.json_encode(array_values($sidebar['options'])).'\'  /></div>';
		}

		echo '<p class="howto">'.sprintf(__("Note: Selected Sidebars are displayed on this %s specifically."),strtolower($post_type->labels->singular_name))." ";

		echo sprintf(__("Display sidebars per %s etc. with the %s."),strtolower(implode(", ", $content)),$link)."</p>";
	}

	/**
	 * Get sidebars for select post types
	 *
	 * @since  3.3
	 * @param  array  $posts
	 * @return array
	 */
	private function _get_content_sidebars($posts = null) {
		if(is_array($posts) && $posts) {
			global $wpdb;
			return $wpdb->get_results(
				"SELECT meta.meta_value, sidebars.ID, sidebars.post_title, groups.ID as group_id
				FROM $wpdb->posts sidebars
				INNER JOIN $wpdb->posts groups ON groups.post_parent = sidebars.ID
				INNER JOIN $wpdb->postmeta meta ON meta.post_id = groups.ID
				WHERE sidebars.post_status <> 'trash'
				AND sidebars.post_type = '".CAS_App::TYPE_SIDEBAR."'
				AND meta.meta_key = '".WPCACore::PREFIX.self::MODULE_NAME."'
				AND meta.meta_value IN (".implode(",", $posts).")
				ORDER BY sidebars.post_title ASC"
			);
		}
		return false;
	}

}

//eol