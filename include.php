<?php

/*
Plugin Name:    Discrete Transients API
Plugin URI:     http://www.attitude.sk
Description:    Moves transients to separate table for custom control
Version:        v0.1.0
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

        add_action('activate_plugin', array($this, 'activate'));
        add_action('deactivate_plugin', array($this, 'deactivate'));

        add_filter( 'query', array($this, 'filter_query'));

        add_filter('plugin_action_links', array($this,'flush_link'), 10, 3);
    }

    /**
     * Returns singleton instance of this class
     *
     */
    function instance()
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
        $this->log("Status: Plugin table build.");
        $this->log("Status: Plugin activated.");
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

        return $this->wpdb->query($sql);
    }

    /**
     * Drops transients table
     *
     * @param void
     *
     */
    function drop()
    {
        $sql =
<<<SQL
DROP TABLE IF EXISTS `{$this->table}`;
SQL;
        return $this->wpdb->query($sql);
    }

    /**
     * Adds link for truncating transients cache by running plugin activation process
     *
     * @param  $links        array   Passed array of links
     * @param  $pluin_file   string  Passed string of currently looped plugin
     * @return               array   Modified array of links
     *
     */
    function flush_link($links, $plugin_file)
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($plugin_file == $this_plugin) {
            $link = '<a href="' . wp_nonce_url('plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $_GET['paged'] . '&amp;s=' . $_GET['s'], 'activate-plugin_' . $plugin_file) . '" title="' . esc_attr__('Flush this table') . '" class="edit">' . __('Flush') . '</a>';
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
     * Plugin magic happens here
     *
     * By modifying table from/to which WordPress writes to/reads from transients.
     *
     * @param   $sql string SQL query passed from every DB query
     * @returns      string Modified SQL query
     *
     */
    function filter_query($sql)
    {
        if(strstr($sql, '_transient_' )) {
            $sql = str_replace($this->wpdb->options, $this->table, $sql);

            if(DISCRETE_TRANSIENT_LOG_QUERIES) {
                $fp = fopen(dirname(__FILE__).'/debug.log', 'a');
                fwrite($fp, trim($sql)."\n");
                fclose($fp);
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
