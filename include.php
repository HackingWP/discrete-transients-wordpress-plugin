<?php

/*
Plugin Name:    Discrete Transients API
Plugin URI:     http://www.attitude.sk
Description:    Moves transients to separate table for custom control
Version:        v0.1.1
Author:         Martin Adamko
Author URI:     http://www.attitude.sk
License:        The MIT License (MIT)

Copyright (c) <year> <copyright holders>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

if(!defined('DISCRETE_TRANSIENT_LOG_QUERIES')) {
    define('DISCRETE_TRANSIENT_LOG_QUERIES', false);
}

if(!defined('DISCRETE_TRANSIENT_TABLE')) {
    define('DISCRETE_TRANSIENT_TABLE', 'transients');
}

class discreteTransients
{
    static  $instance = null;
    private $table    = null;
    private $wpdb     = null;

    /**
     * Constructor
     *
     * @param void
     *
     */
    private function __construct()
    {
        global $wpdb;
        $this->wpdb =& $wpdb;

        $this->table = $this->wpdb->prefix.DISCRETE_TRANSIENT_TABLE;

        if(is_admin()) {
            add_action('activate_plugin', array($this, 'activate'));
            add_action('deactivate_plugin', array($this, 'deactivate'));
            add_action('init', array($this, 'flush'));
        }

        add_filter( 'query', array($this, 'filter_query'));

        add_filter('plugin_action_links', array($this,'flush_link'), 10, 3);
    }

    /**
     * Returns singleton instance of this class
     *
     */
    static function instance()
    {
        if(static::$instance===null) {
            $instance = new discreteTransients();
            $instance->log("\n\n\nStatus: Plugin started\n\n");
            return $instance;
        }

        return static::$instance;
    }

    /**
     * Deactivates transients plugin
     *
     * @param void
     *
     */
    function deactivate()
    {
        if($this->wpdb->get_var("SHOW TABLES LIKE '$this->table'")=== $this->table) {
            if(!$this->drop()) {
                wp_die('Failed to drop old transient table.');
            }
        }
        $this->log("Status: Plugin table dropped.");
        $this->log("Status: Plugin deactivated.");
    }

    /**
     * Activates transients plugin
     *
     * @param void
     *
     */
    function activate()
    {
        $this->deactivate();

        if(!$this->build()) {
            wp_die("Cannot create transients table.");
        }

        if(!$this->flush_options()) {
            wp_die("Cannot flush options table.");
        }

        $this->log("Status: Plugin table build.");
        $this->log("Status: Plugin activated.");
    }

    public function flush()
    {
        $this_plugin = plugin_basename(__FILE__);

        if(isset($_REQUEST['action']) && $_REQUEST['action']==='flush' && $_REQUEST['plugin']===$this_plugin) {
            $referer = get_admin_url(null, "plugins.php?flush=FAILED");

            if(wp_verify_nonce($_REQUEST['_wpnonce'], 'flush-plugin_' . $this_plugin)) {
                $this->deactivate();
                if(!$this->build()) {
                    wp_die("Cannot create transients table.");
                }
                $this->log("Status: Plugin table build.");
                $this->log("Status: Plugin table flushed.");

                $referer = get_admin_url(null, "plugins.php?flush=OK");
            }

            wp_redirect($referer, 301);
        }
    }

    /**
     * Creates transients table
     *
     * @param void
     *
     */
    private function build()
    {
        $sql =
<<<SQL
CREATE TABLE `{$this->table}` (
  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(64) NOT NULL DEFAULT '',
  `option_value` longtext NOT NULL,
  `autoload` varchar(20) NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
SQL;

        return $this->wpdb->query($sql)===false ? false : true;
    }

    /**
     * Drops transients table
     *
     * @param void
     *
     */
    private function drop()
    {
        $sql =
<<<SQL
DROP TABLE IF EXISTS `{$this->table}`;
SQL;
        return $this->wpdb->query($sql)===false ? false : true;
    }

    /**
     * Adds link for truncating transients cache by running plugin activation process
     *
     * @param  $links        array   Passed array of links
     * @param  $pluin_file   string  Passed string of currently looped plugin
     * @return               array   Modified array of links
     *
     */
    public function flush_link($links, $plugin_file)
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($plugin_file == $this_plugin) {
            $link = '<a href="' . wp_nonce_url('index.php?action=flush&amp;plugin=' . $this_plugin, 'flush-plugin_' . $this_plugin) . '" title="' . esc_attr__('Flush this table') . '" class="edit">' . __('Flush') . '</a>';
            array_unshift($links, $link);
        }

        return $links;
    }

    function log($str)
    {
        if(DISCRETE_TRANSIENT_LOG_QUERIES) {
            $fp = fopen(dirname(__FILE__).'/debug.log', 'a');
            if(strlen($str)>256) {
                $str = substr($str,0,256).'...';
            }
            fwrite($fp, date("[c] "). $str."\n");
            fclose($fp);
        }
    }

    /**
     * Clears `wp_options` table
     *
     * @param void
     * @returns mixed
     *
     */
    private function flush_options()
    {
        $sql = "DELETE FROM {$this->wpdb->options} WHERE option_name LIKE '%_transient_%'; #discrete-transients-skip";
        if($this->wpdb->query($sql)===false) {
            return false;
        }
        $sql = "OPTIMIZE TABLE {$this->wpdb->options}";
        return $this->wpdb->query($sql)===false ? false : true;
    }

    /**
     * Plugin magic happens here
     *
     * By modifying table from/to which WordPress writes to/reads from transients.
     *
     * @param   $sql string SQL query passed from every DB query
     * @returns      string Modified SQL query
     *
     */
    public function filter_query($sql)
    {
        $dirty = false;

        // Handle requests to options table
        if(!strstr($sql, '#discrete-transients-skip') && strstr($sql, $this->wpdb->options)) {
            if(strstr($sql, '_transient_' )) {
                $sql = str_replace($this->wpdb->options, $this->table, $sql);
                $dirty = true;
            }
            // Special case: Handle autoload query for transients and options
            if(strstr($sql, "SELECT option_name, option_value FROM {$this->wpdb->options} WHERE autoload = 'yes'")) {
                $sql =
<<<SQL
(SELECT option_name, option_value FROM {$this->wpdb->options} WHERE autoload = 'yes')
UNION
(SELECT option_name, option_value FROM {$this->table} WHERE autoload = 'yes')
SQL;
                $dirty = true;
            }
        }

        if($dirty) {
            $this->log("✓ SQL: ".$sql);
        } else {
            $this->log("✖ SQL: ".$sql);
        }

        return $sql;
    }
}

global $wp_discrete_transients;
$wp_discrete_transients = discreteTransients::instance();

// Replay getting the autoloaded options now with included and active plugin
wp_cache_delete( 'alloptions', 'options' );
wp_load_alloptions();
