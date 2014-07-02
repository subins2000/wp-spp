<?
/**
 * Plugin Name: StatCounter Popular Posts
 * Plugin URI: http://subinsb.com/ask/statcounter-popular-posts
 * Description: Display Popular Posts from StatCounter
 * Version: 0.1.1
 * Author: Subin Siby
 * Author URI: http://subinsb.com
 * License: GPLv3
*/

function SCPP_optPage(){
 	if(isset($_POST['SPPid']) && $_POST['SPPid']!=""){
 		update_option("SPPproject", $_POST['SPPid']);
 		echo '<div id="message" class="updated"><p>Saved Settings</p></div>';
 	}
 	$SPPid = get_option("SPPproject")=="" ? "" : get_option("SPPproject");
?>
	<div id="message" class="update-nag">
   	Read more about this plugin <a href="http://subinsb.com/wp-statcounter-popular-posts-plugin">here</a>.
   </div>
	<h1>SC Popular Posts Options</h1>
	<p>You only have to enter the project ID of your StatCounter Project</p>
	<form action="" method="POST">
		<input type="text" name="SPPid" value="<?echo $SPPid;?>" size="40" placeholder="The ID that starts with p" /><br/>
		<button>Save Settings</button>
	</form>
	<h2>Example</h2>
	<p>If the summary page URL of your project looks like this :</p>
	<blockquote>http://statcounter.com/p1234567/summary/</blockquote>
	<p>Then <b>p1234567</b> is your Project ID.</p>
	<h2>Donate</h2>
	<p>Please donate if you liked this plugin</p>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="ZYQWUZ2B8ZXXA"><button name="submit" type="submit"><img alt="Donate" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif"></button><br><img alt="Donate" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1" border="0"></form>
<?
}

function SCPP_adminMenu() {
 	add_submenu_page('options-general.php', __('StatCounter Popular Posts'), __('StatCounter Popular Posts'), 'manage_options', 'SCPP_admin', 'SCPP_optPage');
}
add_action('admin_menu', 'SCPP_adminMenu');

$pluginRoot = realpath(dirname(__FILE__));
require $pluginRoot."/simple_html_dom.php";

class SPP extends WP_Widget {
	
	private $pluginRoot;
	
	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		parent::__construct(
			'spp_widget', // Base ID
			__('StatCounter Popular Posts', 'spp_widget'), // Name
			array( 'description' => __( 'StatCounter Popular Posts', 'spp_widget' ), ) // Args
		);
		global $pluginRoot;
		$this->pluginRoot = $pluginRoot;
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		$instance['type']  = isset($instance['type']) ? $instance['type']   : "";
		$instance['items'] = isset($instance['items']) ? $instance['items'] - 1 : 10 - 1;
		
		echo $args['before_title'] . "Popular Posts" . $args['after_title'];
		
		if($instance['type'] == "pageviews" || $instance['type']==""){
			$this->makeList("/popular/?pageloads&perpage=" . $instance['items']);
		}else{
			$this->makeList("/popular/?visitors&perpage=" . $instance['items']);
		}
		
		echo $args['after_widget'];
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		// outputs the options form on admin
?>
	<p>Should Popular Posts be made by Pageviews or Uniques Visitors ?</p>
	<select class="widefat" id="<?php echo $this->get_field_id('type'); ?>" name="<?php echo $this->get_field_name( 'type' ); ?>">
		<option value="pageviews" >Pageviews</option>
		<option value="visits" <?echo $instance['type']=="visits" ? "selected":""?>>Visits</option>
	</select>
	<p>The number of items to be displayed</p>
	<input type="number" id="<?php echo $this->get_field_id('items'); ?>" name="<?php echo $this->get_field_name( 'items' ); ?>" value="<? echo isset($instance['items']) ? $instance['items']:10;?>" />
	<br/><br/>
<?	
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['type'] = ( ! empty( $new_instance['type'] ) ) ? strip_tags( $new_instance['type'] ) : '';
		$instance['items'] = is_numeric($new_instance['items']) ? $new_instance['items'] : 10;

		return $instance;
	}
	
	public function makeList($url){
		global $wp_query;
 		global $wp;
 		global $wpdb;
		
		$SPPid  = get_option("SPPproject")=="" ? "" : get_option("SPPproject");
		$output = "";
		
		/* The Site URL */
		$siteURL		  = get_home_url(); // get_home_url()
		$siteURLParts = parse_url($siteURL);
 		$siteURL		  = $siteURLParts['host'].$siteURLParts['path'];
		
		/* Make the SC page URL */
		$url  = "http://statcounter.com/". $SPPid . $url . "&guest=1";
		
		$html = $this->getPage($url);
		$html = str_get_html($html);
		
		foreach($html->find("table.results td") as $tdElem){
			$aElem = $tdElem->find("div.iw a[href]", 0);
			if(isset($aElem->href)){
				$href = $aElem->href;
				$current_url = parse_url($href);
 				$current_url = $current_url['host'].$current_url['path'];
 				
  				$slug		 	 = str_replace($siteURL, "", $current_url);
  				$slugLast	= preg_match("/\//", $slug) ? explode("/", $slug)[0] : substr($slug, 1);
  				
  				/* Avoid Display of Home Page */
  				if($slug!=""){
					$sqlstr = $wpdb->prepare("SELECT wposts.ID, wposts.guid
    					FROM $wpdb->posts wposts
   					WHERE wposts.post_name LIKE %s OR wposts.post_name LIKE $slugLast", "%$slug%"
   				);
   				$results = $wpdb->get_results($sqlstr, ARRAY_N);
   				if(isset($results[0][0])){
   					$post_id = $results[0][0];
   			
   					$title	= get_the_title($post_id);
						$output .= $this->makeListItem($title, $href);
					}
				}
			}
		}
		if($output!=""){
			echo '<ol style="list-style: decimal;">';
				echo $output;
			echo '</ol>';
		}else{
			echo "No Popular Posts Yet";
		}
	}
	
	public function getPage($url){
		$root = $this->pluginRoot;
		if(file_exists($root."/pop_data.txt") && file_exists($root."/pop_data.html")){
			$info = unserialize(file_get_contents($root . "/pop_data.txt" ));
			if(is_array($info)){
				if($info['time'] < strtotime("-10 minutes") || $info['url'] != $url){
					return $this->getPopularData($url);
				}else{
					return file_get_contents($root."/pop_data.html");
				}
			}else{
				return $this->getPopularData($url);
			}
		}else{
			return $this->getPopularData($url);
		}
	}
	
	public function getPopularData($url){
		$root = $this->pluginRoot;
		$html = file_get_contents($url);
		if($html){
			/* We add a serialize data because we don't want anyone to mess with the file, so that when we unserialize a correct Array will be produced */
			file_put_contents($root . "/pop_data.txt", serialize(array(
		 		"time" => time(),
		 		"url"  => $url
			)));
			file_put_contents($root . "/pop_data.html", $html);
		}else{
			if(file_exists($root."/pop_data.html")){
				$html = file_get_contents($root."/pop_data.html");
			}else{
				$html = "";	
			}
		}
		return $html;
	}
	
	/* Make an Item of list */
	public function makeListItem($title, $url){
		return '<li><a href="'.$url.'" title="'.$title.'">'.$title.'</a></li>';
	}
}

add_action('widgets_init', create_function('', 'return register_widget(\'SPP\');'));