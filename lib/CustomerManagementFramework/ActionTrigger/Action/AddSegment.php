<?php
/**
 * Created by PhpStorm.
 * User: mmoser
 * Date: 22.11.2016
 * Time: 13:16
 */

namespace CustomerManagementFramework\ActionTrigger\Action;

use CustomerManagementFramework\ActionTrigger\Trigger\ActionDefinitionInterface;
use CustomerManagementFramework\Factory;
use CustomerManagementFramework\Model\CustomerInterface;
use Pimcore\Model\Object\CustomerSegment;

class AddSegment extends AbstractAction {

    const OPTION_SEGMENT_ID = 'segmentId';

    public function process(ActionDefinitionInterface $actionDefinition, CustomerInterface $customer)
    {

        $options = $actionDefinition->getOptions();

        if(empty($options[self::OPTION_SEGMENT_ID])) {
            $this->logger->error("AddSegment action: segmentId option not set");
        }


        if($segment = CustomerSegment::getById(intval($options[self::OPTION_SEGMENT_ID]))) {

            $this->logger->info(sprintf("AddSegment action: add segment %s (%s) to customer %s (%s)", (string)$segment, $segment->getId(), (string) $customer, $customer->getId()));

            if($segment->getCalculated()) {
                Factory::getInstance()->getSegmentManager()->mergeCalculatedSegments($customer, [$segment]);
            } else {
                Factory::getInstance()->getSegmentManager()->mergeManualSegments($customer, [$segment]);
            }

        } else {
            $this->logger->error(sprintf("AddSegment action: segment with ID %s not found", $options[self::OPTION_SEGMENT_ID]));
        }
    }

    public static function createActionDefinitionFromEditmode(\stdClass $setting)
    {
        $action = parent::createActionDefinitionFromEditmode($setting);

        $options = $action->getOptions();

        if(isset($options['segment'])) {
            $segment = CustomerSegment::getByPath($options['segment']);
            $options['segmentId'] = $segment->getId();
            unset($options['segment']);
        }

        $action->setOptions($options);

        return $action;
    }

    public static function getDataForEditmode(ActionDefinitionInterface $actionDefinition)
    {

        $options = $actionDefinition->getOptions();

        if(isset($options['segmentId'])) {
            if($segment = CustomerSegment::getById(intval($options['segmentId']))) {
                $options['segment'] = $segment->getFullPath();
            }
        }

        $actionDefinition->setOptions($options);

        return $actionDefinition->toArray();
    }

}