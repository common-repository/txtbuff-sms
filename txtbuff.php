<?php /*

**************************************************************************

Plugin Name:  TxtBuff SMS
Plugin URI:   http://news.txtbuff.com/txtbuff-sms-plugin-released/
Version:      1.0.0
Description:  Displays the latest SMS quotes and text messages from <a href="http://www.txtbuff.com">TxtBuff</a>. Visit <a href="./options-general.php?page=txtbuff">Options &raquo; TxtBuff SMS</a> after activation of the plugin. 
Author: Carol Sinel
Author URI: http://www.txtbuff.com


**************************************************************************/

class TxtBuff {
	var $settings = array();
	var $defaults = array();
	var $message;
	var $error;

	// Plugin initialization
	function TxtBuff() {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's folder and name it "txtbuff-[value in wp-config].mo"
		load_plugin_textdomain( 'txtbuff', PLUGINDIR . '/txtbuff' );

		// Add this plugin's hooks, filters, and widget
		add_action( 'admin_menu', array(&$this, 'AddAdminMenu') );
		add_action( 'plugins_loaded', array(&$this, 'AddWidget') );
		add_action( 'init', array(&$this, 'HandlePOST') );
		add_action( 'wp_head', array(&$this, 'OutputCSS') );

		// Create the default options
		$this->defaults = array(
			'source' => 'latest',
			'author' => '',
			'category' => '',
			'count' => 3,
			'characters' => 150,
			'css' => '/* The container box */
DIV#rss-box {
	background: url("http://www.fileden.com/files/2006/10/4/260611/My%20Documents/plugin_bg.gif"); 
	background-repeat: repeat-y; 
	background-position: right;
	background-color: #FFF7E6;
	border: solid 3px #7E8D45;
	font-family: "Arial";
	width: 144px;
}

/* The "TxtBuff" title text */
DIV#rss-title, DIV#rss-title a, DIV#rss-title a:hover {
	font-family: "Arial Black";
	font-style: normal;
	font-weight: bold;
	font-size: 28px;
	color: #BB0000;
	padding-top: 5px;
	padding-bottom: 4px;
	text-align: center;
	text-decoration: none;
}

/* The box surrounding the text below the title */
DIV#rss-desc {
	background-color: #946B1A;
	text-align: center;
	padding-top: 5px;
	padding-bottom: 5px;
	margin-bottom: 10px;
}
/* The text below the title */
DIV#rss-desc a {
	font-family: "Arial";
	font-style: normal;
	font-weight: bold;
	font-size: 11px;
	color: #FFF7E6;
	text-decoration: none;
}

/* The unordered list containing the quotes */
UL#rss-items {
	background-color: transparent;
	list-style-type: none; 
	padding-left: 1em;
	padding-right: 1em !important;
	margin-top: 15px;
	margin-left: 0;
	text-align: left;
}

/* Each quote item */
LI#rss-item {
	font-style: normal;
	font-weight: normal;
	font-size: "10";
	color: #31220C !important;
	list-style-image: none;
	font-size: 11px;
	font-family: Arial;
	padding:0;
	margin:0;
}

/* Links inside quotes */
LI#rss-item a {
	font-family: Verdana;
	font-size: 10px;
	color: #31220C;
	text-decoration: underline;
}',
		);
	}


	// Register the options menu with WordPress
	function AddAdminMenu() {
		add_options_page( __('TxtBuff Options', 'txtbuff'), __('TxtBuff SMS', 'txtbuff'), 'manage_options', 'txtbuff', array(&$this, 'OptionsPage') );
	}


	// Register the widget with WordPress
	function AddWidget() {
		if ( function_exists('register_sidebar_widget') ) register_sidebar_widget( array('TxtBuff SMS', 'txtbuff'), array(&$this, 'Widget') );
	}


	// Load the plugin's options
	function MaybeLoadOptions( $force = FALSE ) {
		// If settings aren't set (i.e. first function run) or we're doing a forced refresh
		if ( empty($this->settings) || TRUE == $force ) {
			$this->settings = get_option('txtbuff');
		}

		// Still no settings? Okay, use the defaults
		if ( empty($this->settings) ) {
			$this->settings = $this->defaults;
		}
	}


	// Handle the options page submit
	function HandlePOST() {
		if ( 1 != $_POST['txtbuff_options'] ) return; // Nothing here to handle

		$this->MaybeLoadOptions( TRUE );

		// Reset to defaults
		if ( !empty($_POST['defaults']) ) {
			$this->settings = $this->defaults;
			delete_option('txtbuff'); // Incase $this->defaults changes in the future and the user is still using the defaults
			$this->message = __( 'Options reset to defaults.', 'txtbuff' );
		}

		// Handle normal form submit
		else {
			$oldsource = $this->settings['source'];
			switch ( $_POST['txtbuff_source'] ) {
				case 'author' :
					$this->settings['source'] = 'author';
					$sourcename = __( 'author', 'txtbuff' );
					break;
				case 'category' :
					$this->settings['source'] = 'category';
					$sourcename = __( 'category', 'txtbuff' );
					break;
				case 'latest' :
				default :
					$this->settings['source'] = 'latest';
					break;
			}

			$this->settings['author']     = strtolower( trim( stripslashes( $_POST['txtbuff_author'] ) ) );
			$this->settings['category']   = strtolower( trim( stripslashes( $_POST['txtbuff_category'] ) ) );
			$this->settings['count']      = (int) $_POST['txtbuff_count'];
			$this->settings['characters'] = (int) $_POST['txtbuff_characters'];
			$this->settings['css']        = stripslashes( $_POST['textbuff_css'] );

			// Check for valid author and category names
			if ( 'latest' !== $this->settings['source'] ) {
				$rss = $this->GetRSSItems();
				if ( 0 == count( $rss->items ) ) {
					// Revert the source change but save any other changes
					$newsource = $this->settings['source'];
					$this->settings['source'] = $oldsource;
					update_option( 'txtbuff', $this->settings );

					// Put the new source back so the form stays as the user submitted it
					$this->settings['source'] = $newsource;

					$this->error = sprintf( "%s not found! Please check the %s name to make sure it's valid.", ucfirst( $this->settings['source'] ), $this->settings['source'] );
					return;
				}
			}

			update_option( 'txtbuff', $this->settings );

			$this->message = __( 'Options saved.' );
		}
	}


	// Output the CSS in the <head>
	function OutputCSS() {
		$this->MaybeLoadOptions();
		echo "	<!-- TxtBuff Styling -->\n	<style type='text/css' media='screen'>\n" . $this->settings['css'] . "\n	</style>\n";
	}


	// Outputs the contents of the options page
	function OptionsPage() {
		$this->MaybeLoadOptions();

		if ( !empty($this->error) ) : ?>


<div id="message" class="error fade-ff0000"><p><strong><?php echo $this->error; ?></strong></p></div>
<?php elseif ( !empty($this->message) ) : ?>


<div id="message" class="updated fade"><p><strong><?php echo $this->message; ?></strong></p></div>
<?php endif; ?>

<div class="wrap">
	<h2><?php _e('TxtBuff Options', 'txtbuff'); ?></h2>

	<form method="post" action="">
	<input type="hidden" name="txtbuff_options" value="1" />
<?php wp_nonce_field('txtbuff'); ?>


	<p class="submit"><input type="submit" name="Submit" value="<?php _e('Update Options &raquo;') ?>" /></p>

	<fieldset class="options">
		<legend><?php _e('Select SMS Quote Source', 'txtbuff'); ?></legend>

		<p>
			<label><input type="radio" name="txtbuff_source" value="latest" class="tog" <?php checked($this->settings['source'], 'latest'); ?> /> <?php _e('Latest Quotes', 'txtbuff'); ?></label>
		</p>
		<p>
			<label><input type="radio" name="txtbuff_source" value="author" class="tog" <?php checked($this->settings['source'], 'author'); ?> /> <?php _e('Specific Author', 'txtbuff'); ?></label>
			<?php _e('&raquo; Author Name:', 'txtbuff'); ?> <input type="text" name="txtbuff_author" value="<?php echo attribute_escape($this->settings['author']); ?>" size="30" />
		</p>
		<p>
			<label><input type="radio" name="txtbuff_source" value="category" class="tog" <?php checked($this->settings['source'], 'category'); ?> /> <?php _e('Specific Category', 'txtbuff'); ?></label>
			<?php _e('&raquo; Category Name:', 'txtbuff'); ?> <input type="text" name="txtbuff_category" value="<?php echo attribute_escape($this->settings['category']); ?>" size="30" />
		</p>
	</fieldset>

	<fieldset class="options">
		<legend><?php _e('Quote Display Options', 'txtbuff'); ?></legend>

		<p>
			<label for="txtbuff_count"><?php _e('Number of quotes to display:', 'txtbuff'); ?></label>
			<input type="text" name="txtbuff_count" id="txtbuff_count" value="<?php echo attribute_escape($this->settings['count']); ?>" size="10" />
		</p>
		<p>
			<label for="txtbuff_characters"><?php _e('Maximum characters per quote to display:', 'txtbuff'); ?></label>
			<input type="text" name="txtbuff_characters" id="txtbuff_characters" value="<?php echo attribute_escape($this->settings['characters']); ?>" size="10" />
		</p>
	</fieldset>

	<fieldset class="options">
		<legend><?php _e('Appearance', 'txtbuff'); ?></legend>

		<p>You can edit the display CSS here.</p>

		<p><textarea name="textbuff_css" cols="50" rows="15" style="width:100%"><?php echo attribute_escape($this->settings['css']); ?></textarea></p>
	</fieldset>

	<p class="submit">
		<input type="submit" name="defaults" value="<?php _e('&laquo; Reset To Defaults', 'txtbuff') ?>" style="float:left" />
		<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;') ?>" />
	</p>

	</form>
</div>
<?php
	}


	// Figure out the feed URL and fetch the data
	function GetRSSItems() {
		$this->MaybeLoadOptions();

		require_once (ABSPATH . WPINC . '/rss.php');

		$feed = 'http://www.txtbuff.com/';

		switch ( $this->settings['source'] ) {
			case 'author' :
				$feed .= 'author/' . urlencode( $this->settings['author'] ) . '/feed/';
				break;
			case 'category' :
				$feed .= 'category/' . urlencode( $this->settings['category'] ) . '/feed/';
				break;
			case 'latest' :
			default :
				$feed = 'http://feeds.feedburner.com/txtbuff';
				break;
		}

		return @fetch_rss( $feed );
	}


	// Create the sidebar widget
	function Widget( $args ) {
		extract( $args );
?>
	<?php echo $before_widget; ?> 
		<?php echo $before_title .'&nbsp;' . $after_title; ?> 
<?php $this->OutputContent(); ?> 
	<?php echo $after_widget; ?> 
<?php
	}


	// Output the meat of this plugin
	function OutputContent() {
		$this->MaybeLoadOptions();

		$rss = $this->GetRSSItems();
		?>

<div id="rss-box">
	<div id="rss-title"><a class="rss-title" href="http://www.txtbuff.com" target="_blank"><?php _e('TxtBuff', 'txtbuff'); ?></a></div>
	<div id="rss-desc"><a class="rss-desc" href="http://www.txtbuff.com" target="_blank"><?php _e('SMS Quotes and Text Messages Collection', 'txtbuff'); ?></a></div>

	<ul id="rss-items">
<?php
	if ( isset( $rss->items ) && 0 != count( $rss->items ) ) :
		$rss->items = array_slice( $rss->items, 0, $this->settings['count'] );

		foreach ( $rss->items as $item ) :
			// Trim quotes that are too long
			if ( $this->settings['characters'] < strlen( $item['description'] ) ) {
				$item['description'] = substr( $item['description'], 0, $this->settings['characters'] ) . ' [...]';
			}
?>
		<li id="rss-item">
<?php echo $item['description']; ?><br />
			<a href="<?php echo wp_filter_kses($item['link']); ?>" target="_blank"><?php _e('&laquo;details&raquo;', 'txtbuff'); ?></a><br /><br />
		</li>
<?php
		endforeach;
	else :
?>
		<li id="rss-item">
			<em><?php _e('No items found.', 'txtbuff'); ?></em><br /><br />
		</li>
<?php
	endif;
?>
	</ul>
</div>

<?php
	}
}

$TxtBuff = new TxtBuff();


// Easy wrapper for themes
function txtbuff() {
	global $TxtBuff;
	$TxtBuff->OutputContent();
}

?>