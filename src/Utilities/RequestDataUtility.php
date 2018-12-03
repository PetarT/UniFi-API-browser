<?php

namespace WingWifi\Utilities;

/**
 * Class RequestDataUtility
 * @package WingWifi\Utilities
 */
class RequestDataUtility
{
    /**
     * RequestDataUtility constructor.
     *
     * @param   array  $arr  Passed data to bind.
     *
     * @return  void
     */
    public function __construct($arr = array())
    {
        if (empty($arr)) {
            $this->bind($_GET);
            $this->bind($_POST);
        } else {
            $this->bind($arr);
        }
    }

    /**
     * Method to bind array values to object.
     *
     * @param   array  $input  Array of request data.
     *
     * @return  void
     */
    private function bind($input = array()) {
        foreach ($input as $key => $value) {
            $this->$key = $value;
        }
    }
}
