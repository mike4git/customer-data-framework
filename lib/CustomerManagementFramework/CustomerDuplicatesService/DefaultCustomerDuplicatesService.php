<?php
/**
 * Created by PhpStorm.
 * User: mmoser
 * Date: 21.11.2016
 * Time: 16:14
 */

namespace CustomerManagementFramework\CustomerDuplicatesService;

use Carbon\Carbon;
use CustomerManagementFramework\Model\CustomerInterface;
use CustomerManagementFramework\Plugin;
use Pimcore\Db;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Object\ClassDefinition;
use \Pimcore\Model\Object\Customer;

class DefaultCustomerDuplicatesService implements CustomerDuplicatesServiceInterface{

    private $config;

    /**
     * @var array
     */
    private $duplicateCheckFields;

    /**
     * @var array
     */
    protected $matchedDuplicateFields;

    public function __construct()
    {
        $this->config =  Plugin::getConfig()->CustomerDuplicatesService;
        $this->duplicateCheckFields = $this->config->duplicateCheckFields ? $this->config->duplicateCheckFields->toArray() : [];
    }

    public function getDuplicatesOfCustomer(CustomerInterface $customer, $limit = 10) {
        foreach($this->duplicateCheckFields as $fields) {

            if(!is_array($fields)) {
                return $this->getDuplicatesOfCustomerByFields($customer, $this->duplicateCheckFields, $limit = 10);
            }

            if($duplicates = $this->getDuplicatesOfCustomerByFields($customer, $fields, $limit = 10)) {
                return $duplicates;
            }
        }

        return [];
    }

    /**
     * @return array
     */
    public function getMatchedDuplicateFields()
    {
        return $this->matchedDuplicateFields;
    }



    protected function getDuplicatesOfCustomerByFields(CustomerInterface $customer, array $fields, $limit = 10) {


        if(!sizeof($fields)) {
            return [];
        }

        $list = new Customer\Listing;

        $conditions = [
            "o_id !=" . $customer->getId(),
            "o_published = 1",
            "active = 1",
        ];

        foreach($fields as $field) {
            $getter = 'get' . ucfirst($field);
            $value = $customer->$getter();

            if(is_null($value)) {
                return [];
            } else {
                if($cond = $this->createNormalizedMysqlCompareCondition($field, $value)) {
                    $conditions[] = $cond;
                }
            }
        }
        $conditions = '(' . implode(' and ', $conditions) . ')';

        $list->setCondition($conditions);
        $list->setLimit($limit);
        $list = $list->load();

        if($list) {
            $this->matchedDuplicateFields = $fields;
        }

        return $list ? : [];
    }

    protected function createNormalizedMysqlCompareCondition($field, $value) {
        $db = Db::get();

        $class = ClassDefinition::getByName(Plugin::getConfig()->General->CustomerPimcoreClass);
        $fd = $class->getFieldDefinition($field);

        if(!$fd) {
            return false;
        }

        if($value instanceof ElementInterface) {
            return $this->createNormalizedMysqlCompareConditionForSingleRelationFields($field, $value);
        }

        if(is_array($value) && ($value[0] instanceof ElementInterface)) {
            return $this->createNormalizedMysqlCompareConditionForMultiRelationFields($field, $value);
        }

        if(strpos($fd->getColumnType(), 'char') ==! false) {
            return $this->createNormalizedMysqlCompareConditionForStringFields($field, $value);
        }

        if($value instanceof Carbon || $value instanceof \Zend_Date) {
            return $this->createNormalizedMysqlCompareConditionForDateFields($field, $value);
        }

        return sprintf("%s = %s", $field, $db->quote(trim(strtolower($value))));
    }

    protected function createNormalizedMysqlCompareConditionForStringFields($field, $value) {
        $db = Db::get();

        return sprintf("TRIM(LCASE(%s)) = %s", $field, $db->quote(trim(strtolower($value))));
    }

    protected function createNormalizedMysqlCompareConditionForDateFields($field, $value) {

        return sprintf("%s = %s", $field, $value->getTimestamp());
    }

    protected function createNormalizedMysqlCompareConditionForSingleRelationFields($field, $value) {

        return sprintf("%s = %s", $field . '__id', $value->getId());
    }

    protected function createNormalizedMysqlCompareConditionForMultiRelationFields($field, $value) {
        $db = Db::get();

        $ids = [];
        foreach($value as $row) {
            $ids[] = $row->getId();
        }
        return sprintf("%s = %s", $field, $db->quote(',' . implode(',', $ids) . ','));
    }
}