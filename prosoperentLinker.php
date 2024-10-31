<?php
/*
Plugin Name: Prosperent Auto-Linker
Description: This Plugin is no longer being updated. Please download the new Prosperent plugin instead- <a href="http://wordpress.org/extend/plugins/prosperent-suite/">Prosperent Suite</a>
Version: 1.3
Author: Prosperent Brandon
License: GPL2
*/

/*
    Copyright 2012  Prosperent Brandon  (email : brandon@prosperent.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

add_action( 'admin_notices', 'prosperAutoLinkerNoticeWrite' );
function prosperAutoLinkerNoticeWrite() 
{
	echo '<div class="error" style="padding:6px 0;">';
	echo _e( '<span style="font-size:14px; padding-left:10px;"><b>The Prosperent Auto-Linker plugin is no longer being updated.<b></span><br><br>', 'my-text-domain' );
	echo _e( '<span style="font-size:16px; padding-left:10px;">Please download the new Prosperent plugin instead- <a href="http://wordpress.org/extend/plugins/prosperent-suite/">Prosperent Suite</a></span><br><br>', 'my-text-domain' );
	echo _e( '<span style="font-size:14px; padding-left:10px;">It contains the Prosperent Auto-Linker plus more great tools.</span><span style="font-size:12px;"></span>', 'my-text-domain' );
	echo '</div>';	
}

// is Prosperent Product Search plugin activated
if (is_plugin_active('prosperent-powered-product-search/ProsperentSearch.php'))
{
    if (!class_exists('prosper_autoLinker')):

    require_once('auto-linker.php');

    class Prosper_autoLinker_Short
    {
        public function __construct()
        {
            add_shortcode('linker', array($this, 'shortcode'));
        }


		
        public function shortcode($atts, $content = null)
        {
            global $wpdb;
            $wpdb->hide_errors();
            $myrows = $wpdb->get_row("SELECT *
                                          FROM $wpdb->options
                                          WHERE option_name = 'prosper_auto_linker'", ARRAY_A);

            $value = unserialize($myrows['option_value']);

            $target = $value['target'] ? '_blank' : '_self';
            $sub_dir = get_option('Parent_Directory');
            $base_url = get_option('Base_URL');

            extract(shortcode_atts(array(
                "to" => $sub_dir . !$base_url ? '/product?q=' : $base_url . '?q=',
                "q"  => $q
            ), $atts));

            $query = !$q ? $content : $q;

            // Remove links within links
            $content = strip_tags($content);
            $query = strip_tags($query);

            return '<a href="' . $to . urlencode($query) . '" TARGET="' . $target . '">' . $content . '</a>';
        }
    }

    new Prosper_autoLinker_Short();

    class autoLinker_Buttons
    {
        public function __construct()
        {
            add_action('admin_print_footer_scripts', array($this, 'qTagsButton'));
            add_action('admin_init', array($this, 'autoLinker_custom_add'));
        }
        public function autoLinker_custom_add()
        {
            // Add only in Rich Editor mode
            if (get_user_option('rich_editing') == 'true')
            {
                add_filter('mce_external_plugins', array($this, 'autoLinker_tiny_register'));
                add_filter('mce_buttons', array($this, 'autoLinker_tiny_add'));
            }
        }

        public function qTagsButton()
        {
            ?>
            <script type="text/javascript">
                QTags.addButton('auto-linker', 'auto-linker', '[linker]', '[/linker]', 0);
            </script>
            <?php
        }

        public function autoLinker_tiny_add($buttons)
        {
            array_push($buttons, "|", "linker");
            return $buttons;
        }

        public function autoLinker_tiny_register($plugin_array)
        {
            $plugin_array["linker"] = plugin_dir_url(__FILE__) . 'js/button.js';
            return $plugin_array;
        }
    }

    new autoLinker_Buttons();

    class prosper_autoLinker extends Prosperent_WP
    {
        public static $instance;

        /**
         * Constructor
         *
         * @return void
         */
        public function __construct()
        {
            $this->prosper_autoLinker();
        }

        public function prosper_autoLinker()
        {
            // Be a singleton
            if (!is_null(self::$instance))
                return;

            parent::__construct('1.0', 'auto-linker', 'prosper', __FILE__, array());
            register_activation_hook(__FILE__, array(__CLASS__, 'activation' ));
            self::$instance = $this;
        }

        /**
         * Handles activation tasks, such as registering the uninstall hook.
         *
         * @return void
         */
        public function activation()
        {
            register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
        }

        /**
         * Override the plugin framework's register_filters() to actually hook actions and filters.
         *
         * @return void
         */
        public function register_filters()
        {
            $filters = apply_filters('c2c_linkify_text_filters', array('the_content', 'the_excerpt', 'widget_text'));
            foreach ((array) $filters as $filter)
                add_filter( $filter, array(&$this, 'auto_linker'), 2);

            // Note that the priority must be set high enough to avoid links inserted by the plugin from
            // getting omitted as a result of any link stripping that may be performed.
            $options = $this->get_options();
            if ($options['auto_link_comments'])
            {
                add_filter('get_comment_text', array(&$this, 'auto_linker'), 11);
                add_filter('get_comment_excerpt', array(&$this, 'auto_linker'), 11);
            }
        }

        /**
         * Initializes the plugin's configuration and localizable text variables.
         *
         * @return void
         */
        public function load_config()
        {
            $this->name      = __('Auto-Linker', $this->textdomain);
            $this->menu_name = __('Auto-Linker', $this->textdomain);

            $this->config = array(
                'auto_link' => array('input' => 'inline_textarea', 'datatype' => 'hash', 'default' => array(
                        'boots =>
                        red shoes => Red Nike Shoes'
                    ),
                    'allow_html' => true, 'no_wrap' => true, 'input_attributes' => 'rows="15" cols="40"',
                    'label' 	 => __('Text and Query', $this->textdomain),
                    'help' 	 	 => 'Query is optional, you can leave it as just the text to be matched.'

                ),
                'auto_link_comments' => array( 'input' => 'checkbox', 'default' => false,
                        'label' 	 => __('Enable auto-link in comments?', $this->textdomain)

                ),
                'case_sensitive' => array('input' => 'checkbox', 'default' => false,
                        'label'  => __('Case sensitive matching?', $this->textdomain)
                ),
                'target' => array('input' => 'checkbox', 'default' => true,
                        'label'  => __('Open Links in New Window or Tab', $this->textdomain),
                        'help'   => '<b>Checked</b> = <b>_blank</b>: opens link in a new window or tab<p><b>Unchecked</b> = <b>_self</b>: opens link in the same window',
                )
            );
        }

        /**
         * Outputs the text above the text area
         *
         * @return void
         */
        public function options_page_description()
        {
            parent::options_page_description(__('Prosperent Auto-Linker Text Settings', $this->textdomain));

            echo '<p>' . __('Auto-link words or phrases in posts to the Prosperent Product Search.', $this->textdomain) . '</p>';
            echo '<p>' . __('Define text and the query they should be linked to in the field below. Follow this format:', $this->textdomain) . '</p>';
            echo "<blockquote><code>shoes => Nike shoes</code></blockquote>";
            echo '<p>' . __('The query parameter is optional so you can leave it as just the text to match as follows:', $this->textdomain) . '</p>';
            echo "<blockquote><code>shoes</code></blockquote>";
            echo __('List the more specific matches early. For example, if you want to link both <code>shoes</code> and <code>Nike shoes</code>, put <code>Nike shoes</code> first. Otherwise, <code>shoes</code> will match first, preventing <code>Nike shoes</code> from ever being found.', $this->textdomain);
        }

        /**
         * Perform auto-linker
         *
         * @param string $text
         * @return string
         */
        public function auto_linker($text)
        {
            $options    = $this->get_options();
            $preg_flags = $options['case_sensitive'] ? 's' : 'si';
            $target 	= $options['target'] ? '_blank' : '_self';
            $base_url   = get_option('Base_URL');

            $text = ' ' . $text . ' ';
            if (!empty($options['auto_link']))
            {
                foreach ($options['auto_link'] as $old_text => $new_text)
                {
                    $query = urlencode(trim(empty($new_text) ? $old_text : $new_text));

                    $new_text = '<a href="' . $options['sub_dir'] . '/product?q=' . $query . '" target="' . $target . '">' . $old_text . '</a>';
                    $text = preg_replace("|(?!<.*?)\b$old_text\b(?![^<>]*?>)|$preg_flags", $new_text, $text);
                }
                // Remove links within links
                $text = preg_replace( "#(<a [^>]+>)(.*)<a [^>]+>([^<]*)</a>([^>]*)</a>#iU", "$1$2$3$4</a>" , $text );
            }
            return trim($text);
        }
    }

    new prosper_autoLinker();

    endif;
}
