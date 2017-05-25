<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * In: http://www.causingeffect.com/software/expressionengine/in
 *
 * Author: Aaron Waldon (Causing Effect) http://www.causingeffect.com
 * Author: Brian Litzinger http://www.boldminded.com
 *
 * License: MIT license.
 */

class In
{
    private $debug = false;

    public function __construct()
    {
        // If the template debugger is enabled, and a super admin user is logged in, enable debug mode
        $this->debug = (ee()->session->userdata['group_id'] == 1 && ee()->config->item('template_debugging') == 'y');
    }

    /**
     * Attempts to include the specified template.
     * The include functionality of this add-on is based on the Pre Embed add-on
     * by @_rsan (http://devot-ee.com/add-ons/pre-embed) and is licensed under the MIT license.
     *
     * @return string
     */
    public function clude()
    {
        // Make sure template files are enabled
        if (ee()->config->item('save_tmpl_files') !== 'y' && ee()->config->item('tmpl_file_basepath')) {
            $this->log_debug_message('Saving templates as files does *not* appear to be enabled.');

            return ee()->TMPL->no_results();
        }

        // Determine the template site, group, and name
        $tagParts = ee()->TMPL->tagparts;
        $site = ee()->config->item('site_short_name');

        if (is_array($tagParts) && isset($tagParts[2])) {
            // Site name was also passed in
            if (isset($tagParts[3])) {
                $site = $tagParts[2];
                $template = trim($tagParts[3], '/');
            } else {
                $template = trim($tagParts[2], '/');
            }
        } else {
            $this->log_debug_message('No include template was specified.');

            return ee()->TMPL->no_results();
        }

        $template = explode('/', $template);
        $groupName = $template[0];
        $templateName = (isset($template[1])) ? $template[1] : 'index';

        $path = rtrim(ee()->config->item('tmpl_file_basepath'), '/').'/';
        $path .= $site . '/' . $groupName . '.group/' . $templateName . '.html';

        $saveAs = ee()->TMPL->fetch_param('save_as', 'no');

        if ($saveAs != 'no' && isset(ee()->session->cache[ 'in' ][$saveAs])) {
            $embed = ee()->session->cache[ 'in' ][$saveAs];
        } else {
            // Get the template contents
            if (file_exists($path)) {
                $embed = file_get_contents($path);

                // Check if there was an error getting the file contents.
                if ($embed === false) {
                    $this->log_debug_message('Unable to retrieve the file contents.');

                    return ee()->TMPL->no_results();
                }
            } else {
                $this->log_debug_message('The file "' . $path . '" could not be found.');

                return ee()->TMPL->no_results();
            }
        }

        if ($saveAs != 'no') {
            ee()->session->cache[ 'in' ][$saveAs] = $embed;
        }

        // Parse embed variables
        if (@preg_match_all('/embed:(\w+)/', $embed, $matches)) {
            foreach ($matches[0] as $i => $fullMatch) {
                // Determine the value
                $value = isset(ee()->TMPL->tagparams[$matches[1][$i]]) ? ee()->TMPL->tagparams[$matches[1][$i]] : '';

                // Set the embed vars, so that they can be used in advanced conditionals
                ee()->TMPL->embed_vars[$fullMatch] = $value;

                // Parse the curly tag variables
                $embed = str_replace(LD . $fullMatch . RD, $value, $embed);
            }
        }

        // Strip comments and parse segment_x vars
        $embed = preg_replace("/\{!--.*?--\}/s", '', $embed);

        // Swap config global vars
        $embed = $this->_parse_global_vars($embed);

        // Segment variables
        for ($i = 1; $i < 10; $i++) {
            $embed = str_replace(LD.'segment_'.$i.RD, ee()->uri->segment($i), $embed);
        }

        // Replace current time variable
        if (strpos($embed, '{current_time') !== false) {
            if (preg_match_all('/{current_time\s+format=([\"\'])([^\\1]*?)\\1}/', $embed, $matches)) {
                for ($j = 0; $j < count($matches[0]); $j++) {
                    if (version_compare(APP_VER, '2.6.0', '<')) {
                        $embed = str_replace($matches[0][$j], ee()->localize->decode_date($matches[2][$j], ee()->localize->now), $embed);
                    } else {
                        $embed = str_replace($matches[0][$j], ee()->localize->format_date($matches[2][$j]), $embed);
                    }
                }
            }

            $embed = str_replace('{current_time}', ee()->localize->now, $embed);
        }

        // Parse globals if applicable
        $parseGlobals = ee()->TMPL->fetch_param('globals');

        // Parse late globals (expensive)
        // Or parse member vars
        if ($parseGlobals == 'all') {
            $embed = ee()->TMPL->parse_globals($embed);
        } else if ($parseGlobals == 'member') {
            foreach(['member_id', 'group_id', 'member_group', 'username', 'screen_name'] as $val) {
                if (
                    isset(ee()->session->userdata[$val]) &&
                    ($val == 'group_description' || strval(ee()->session->userdata[$val]) != '')
                ) {
                    $embed = str_replace('{logged_in_'.$val.'}', ee()->session->userdata[$val], $embed);
                }
            }
        }

        // Parse nested in:serts
        if (@preg_match('/'.LD.'in:sert:(.+?)'.RD.'/', $embed)) {
            $embed = $this->parse_serts($embed);
        }

        return $embed;
    }

    /**
     * If this method is called, all inserts within the tagdata will be parsed.
     *
     * @return string
     */
    public function serts()
    {
        return $this->parse_serts(ee()->TMPL->tagdata);
    }

    /**
     * Parses all in:sert tags.
     *
     * @param $tagdata
     * @return string
     */
    private function parse_serts($tagdata)
    {
        // Load the class if needed
        if (!class_exists('In_sert')) {
            include PATH_THIRD . 'in/libraries/In_sert.php';
        }

        $inserts = new In_sert();

        $tagdata = $inserts->parse($tagdata);
        unset($inserts);

        return $tagdata;
    }

    /**
     * Parse config global variables.
     * @param string $tagdata
     * @return string
     */
    protected function _parse_global_vars($tagdata = '')
    {
        // if there are no curly brackets, no need to parse...
        if (strpos($tagdata, '{') !== FALSE) {
            $tagdata = ee()->TMPL->parse_variables_row($tagdata, ee()->config->_global_vars);
        }

        return $tagdata;
    }

    /**
     * Simple method to log a debug message to the EE console if debug mode is enabled.
     *
     * @param string $message The debug message.
     * @return void
     */
    protected function log_debug_message($message = '')
    {
        if ($this->debug) {
            ee()->TMPL->log_item('&nbsp;&nbsp;***&nbsp;&nbsp;In debug: ' . $message);
        }
    }
}
