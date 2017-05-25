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

class In_ext
{
    /**
     * @var array
     */
    public $settings = [];

    /**
     * @var string
     */
    public $version = IN_VERSION;

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * Activate the extension by entering it into the exp_extensions table
     *
     * @return void
     */
    public function activate_extension()
    {
        $hooks = [
            'template_fetch_template' => 'template_fetch_template'
        ];

        foreach ($hooks as $hook => $method) {
            $data = [
                'class'     => __CLASS__,
                'method'    => $method,
                'hook'      => $hook,
                'settings'  => serialize([]),
                'priority'  => 9,
                'version'   => IN_VERSION,
                'enabled'   => 'y'
            ];

            ee()->db->insert('extensions', $data);
        }
    }

    /**
     * Disables the extension by removing it from the exp_extensions table.
     *
     * @return void
     */
    function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    /**
     * Updates the extension by performing any necessary db updates when the extension page is visited.
     *
     * @param string $current
     * @return mixed void on update, false if none
     */
    function update_extension($current = '')
    {
        if ($current == '' OR $current == IN_VERSION) {
            return false;
        }

        return true;
    }

    /**
     * Creates global variables from the template pre parse data.
     *
     * @param $row
     * @return string
     */
    public function template_fetch_template($row)
    {
        $row['template_data'] = $this->parse_inserts($row['template_data']);

        return $row;
    }

    /**
     * Old installations were installed with template_pre_parse as the method name.
     *
     * @param $row
     * @return string
     *
     * @deprecated
     */
    public function template_pre_parse($row) {
        return $this->template_fetch_template($row);
    }

    /**
    * @param string $data
    * @return string
    */
    private function parse_inserts($data)
    {
        // Load the class if needed
        if (! class_exists('In_sert')) {
            include PATH_THIRD . 'in/libraries/In_sert.php';
        }

        $inserts = new In_sert();
        $data = $inserts->parse($data);
        unset($inserts);

        return $data;
    }
}
