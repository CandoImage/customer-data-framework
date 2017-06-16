<?php
/**
 * Created by PhpStorm.
 * User: mmoser
 * Date: 22.11.2016
 * Time: 12:43
 */

namespace CustomerManagementFrameworkBundle\ActionTrigger\EventHandler;

use CustomerManagementFrameworkBundle\ActionTrigger\Condition\Checker;
use CustomerManagementFrameworkBundle\ActionTrigger\Event\CustomerListEventInterface;
use CustomerManagementFrameworkBundle\ActionTrigger\Event\EventInterface;
use CustomerManagementFrameworkBundle\ActionTrigger\Event\SingleCustomerEventInterface;
use CustomerManagementFrameworkBundle\Model\ActionTrigger\Rule;
use CustomerManagementFrameworkBundle\Factory;
use CustomerManagementFrameworkBundle\Model\CustomerInterface;
use Psr\Log\LoggerInterface;

class DefaultEventHandler implements EventHandlerInterface{

    private $rulesGroupedByEvents;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $rules = new Rule\Listing();
        $rules->setCondition("active = 1");
        $rules = $rules->load();

        $rulesGroupedByEvents = [];

        foreach($rules as $rule) {
            if($triggers = $rule->getTrigger()) {
                foreach($triggers as $trigger) {
                    $rulesGroupedByEvents[$trigger->getEventName()][] = $rule;
                }
            }
        }

        $this->rulesGroupedByEvents = $rulesGroupedByEvents;
    }

    public function handleSingleCustomerEvent(\Zend_EventManager_Event $e, SingleCustomerEventInterface $event)
    {

        $this->logger->debug(sprintf("handle single customer event: %s", $event->getName()));

        $appliedRules = $this->getAppliedRules($event);
        foreach($appliedRules as $rule) {
            $this->handleActionsForCustomer($rule, $event->getCustomer());
        }
    }

    public function handleCustomerListEvent(\Zend_EventManager_Event $e, CustomerListEventInterface $event)
    {
       // var_dump($this->getAppliedRules($event, false) );
        foreach($this->getAppliedRules($event, false) as $rule) {
            
            if($conditions = $rule->getCondition()) {
                $where = Checker::getDbConditionForRule($rule);

                $listing = Factory::getInstance()->getCustomerProvider()->getList();
                $listing->setCondition($where);
                $listing->setOrderKey('o_id');
                $listing->setOrder('asc');
                
                $paginator = new \Zend_Paginator($listing);
                $paginator->setItemCountPerPage(100);

                $this->logger->debug(sprintf("handleCustomerListEvent: found %s matching customers", $paginator->getTotalItemCount()));

                $totalPages = $paginator->getPages()->pageCount;
                for($i=1; $i<=$totalPages; $i++) {
                    $paginator->setCurrentPageNumber($i);

                    foreach($paginator as $customer) {
                        $this->handleActionsForCustomer($rule, $customer);
                    }

                    \Pimcore::collectGarbage();
                }
            }
        }
    }
    
    private function handleActionsForCustomer(Rule $rule, CustomerInterface $customer)
    {

        if($actions = $rule->getAction()) {
            foreach($actions as $action) {
                if($action->getActionDelay()) {
                    Factory::getInstance()->getActionTriggerQueue()->addToQueue($action, $customer);
                } else {
                    Factory::getInstance()->getActionTriggerActionManager()->processAction($action, $customer);
                }
            }
        }
    }

    /**
     * @param EventInterface $event
     * @param bool           $checkConditions
     *
     * @return Rule[]
     */
    private function getAppliedRules(EventInterface $event, $checkConditions = true) {

        $appliedRules = [];

        if(isset($this->rulesGroupedByEvents[$event->getName()]) && sizeof($this->rulesGroupedByEvents[$event->getName()])) {

            $rules = $this->rulesGroupedByEvents[$event->getName()];

            foreach($rules as $rule) {
                /**
                 * @var Rule $rule;
                 */

                foreach($rule->getTrigger() as $trigger) {
                    if($event->appliesToTrigger($trigger)) {

                        if($checkConditions) {
                            if($this->checkConditions($rule, $event)) {
                                $appliedRules[] = $rule;
                            }
                        } else {
                            $appliedRules[] = $rule;
                        }



                        break;
                    }
                }
            }
        }

        return $appliedRules;
    }

    protected function checkConditions(Rule $rule, SingleCustomerEventInterface $event) {

        return Checker::checkConditionsForRuleAndEvent($rule, $event);
    }
}