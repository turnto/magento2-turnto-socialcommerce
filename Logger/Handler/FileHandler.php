<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 5/20/16
 * Time: 12:22 PM
 */

namespace TurnTo\SocialCommerce\Logger\Handler;

/**
 * Class FileHandler
 * @package TurnTo\SocialCommerce\Logger\Handler
 */
class FileHandler extends \Magento\Framework\Logger\Handler\System
{
    /**
     * Overrides the default log file path
     * @var string
     */
    protected $fileName = '/var/log/turnto.log';
}
