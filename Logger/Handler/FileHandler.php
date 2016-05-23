<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 5/20/16
 * Time: 12:22 PM
 */

namespace TurnTo\SocialCommerce\Logger\Handler;

use \Magento\Framework\Logger\Handler;

class FileHandler extends \Magento\Framework\Logger\Handler\System
{
    protected $fileName = '/var/log/turnto.log';
}