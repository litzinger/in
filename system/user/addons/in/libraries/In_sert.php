<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * In: http://www.causingeffect.com/software/expressionengine/in
 *
 * Author: Aaron Waldon (Causing Effect) http://www.causingeffect.com
 * Author: Brian Litzinger http://www.boldminded.com
 *
 * License: MIT license.
 */

class In_sert
{
    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * Parses the given data for in:sert templates. Any found templates are saved as globals.
     *
     * @param $templateData
     * @return string
     */
    public function parse($templateData)
    {
        //make sure template files are enabled
        if (ee()->config->item('save_tmpl_files') !== 'y' && ee()->config->item('tmpl_file_basepath')) {
            return $templateData;
        }

        //parse embed variables
        if (@preg_match_all('/'. LD .'in:sert:(.*?)'. RD .'/s', $templateData, $matches)) {
            foreach ($matches[1] as $template) {
                $global = '';
                $args = [];
                $argMatches = [];

                // Only run preg_match if necessary
                if (strpos($template, ' ') !== false) {
                    $argString = trim((preg_match("/\s+.*/s", $template, $argMatches))) ? $argMatches[0] : '';
                    $args = ee()->functions->assign_parameters($argString);
                }

                // The tag can contain parameters, so save a reference for replacement later.
                $tag = $template;

                // Remove the args from the string to get the template name.
                if (!empty($argMatches)) {
                    $template = str_replace($argMatches[0], '', $template);
                }

                if (!isset(ee()->config->_global_vars['in:sert:' . $tag])) {
                    $pieces = explode('/', $template);
                    $groupName = $pieces[0];
                    $templateName = (isset($pieces[1])) ? $pieces[1] : 'index';

                    // Determine if the site name was passed in
                    $site = ee()->config->item('site_short_name');

                    if (strpos($groupName,':') !== false) {
                        $pieces = explode(':', $groupName);
                        $site = $pieces[0];
                        $groupName = $pieces[1];
                    }

                    // Determine the path
                    $path = rtrim(ee()->config->item('tmpl_file_basepath'), '/') . '/';
                    $path .= $site . '/' . $groupName . '.group/' . $templateName . '.html';

                    // Get the template contents
                    if (file_exists($path)) {
                        $global = file_get_contents($path);

                        // Check if there was an error getting the file contents.
                        if ($global === false) {
                            ee()->config->_global_vars['in:sert:' . $tag] = '';
                        }
                    } else {
                        ee()->config->_global_vars['in:sert:' . $tag] = '';
                    }

                    // Strip comments and parse segment_x vars
                    $global = preg_replace("/\{!--.*?--\}/s", '', $global);

                    // Swap config global vars
                    // If there are no curly brackets, no need to parse...
                    if (strpos($global, '{') !== false) {
                        $global = ee()->TMPL->parse_variables_row($global, ee()->config->_global_vars);
                    }

                    //segment variables
                    for ($i = 1; $i < 10; $i++) {
                        $global = str_replace(LD . 'segment_' . $i . RD, ee()->uri->segment($i), $global);
                    }

                    // Embed variables
                    if (!empty($args)) {
                        foreach ($args as $argKey => $argVal) {
                            $global = str_replace(LD.'embed:'.$argKey.RD, $argVal, $global);
                        }
                    }

                    // Save the variable
                    ee()->config->_global_vars['in:sert:' . $tag] = $global;
                }

                // Replace the string
                $templateData = str_replace(LD . 'in:sert:' . $tag . RD, $global, $templateData);
            }

            if (@preg_match('/' . LD . 'in:sert:(.*?)' . RD . '/', $templateData)) {
                $templateData = $this->parse($templateData);
            }
        }

        return $templateData;
    }
}
