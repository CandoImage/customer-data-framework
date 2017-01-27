<?php
namespace CustomerManagementFramework\View\Formatter;

use CustomerManagementFramework\Translate\TranslatorInterface;
use Pimcore\Model\Object\ClassDefinition\Data;

interface ViewFormatterInterface extends TranslatorInterface
{
    /**
     * @param Data $fd
     * @return array|string
     */
    public function getLabelByFieldDefinition(Data $fd);

    /**
     * @param mixed $value
     * @return mixed
     */
    public function formatValue($value);

    /**
     * @param Data $fd
     * @param $value
     * @return string
     */
    public function formatValueByFieldDefinition(Data $fd, $value);
}
