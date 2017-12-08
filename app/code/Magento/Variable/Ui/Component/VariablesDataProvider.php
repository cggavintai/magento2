<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Variable\Ui\Component;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\Reporting;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Class VariablesDataProvider
 * @package Magento\Variable\Ui\Component
 */
class VariablesDataProvider extends \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider
{
    /**
     * @var \Magento\Variable\Model\VariableFactory
     */
    private $collectionFactory;
    /**
     * @var \Magento\Email\Model\Source\Variables
     */
    private $storesVariables;

    /**
     * VariablesDataProvider constructor.
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param \Magento\Variable\Model\ResourceModel\Variable\CollectionFactory $collectionFactory
     * @param \Magento\Email\Model\Source\Variables $storesVariables
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        \Magento\Variable\Model\ResourceModel\Variable\CollectionFactory $collectionFactory,
        \Magento\Email\Model\Source\Variables $storesVariables,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->storesVariables = $storesVariables;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Prepare default variables
     *
     * @return array
     */
    private function getDefaultVariables()
    {
        $variables = [];
        foreach ($this->storesVariables->getData() as $variable) {
            $variables[] = [
                'code' => $variable['value'],
                'variable_name' => $variable['label'],
                'variable_type' => \Magento\Email\Model\Source\Variables::DEFAULT_VARIABLE_TYPE
            ];
        }

        return $variables;
    }

    /**
     * Prepare custom variables
     *
     * @return array
     */
    private function getCustomVariables()
    {
        $customVariables = $this->collectionFactory->create();

        $variables = [];
        foreach ($customVariables->getData() as $variable) {
            $variables[] = [
                'code' => $variable['code'],
                'variable_name' => $variable['name'],
                'variable_type' => 'custom'
            ];
        }

        return $variables;
    }

    /**
     * Sort variables array by field.
     *
     * @param array $items
     * @param string $field
     * @param string $direction
     * @return array
     */
    private function sortBy($items, $field, $direction)
    {
        usort($items, function ($item1, $item2) use ($field, $direction) {
            return $this->variablesCompare($item1, $item2, $field, $direction);
        });
        return $items;
    }

    /**
     * Compare variables array's elements on index.
     *
     * @param array $variable1
     * @param array $variable2
     * @param string $partIndex
     * @param string $direction
     *
     * @return int
     */
    private function variablesCompare($variable1, $variable2, $partIndex, $direction)
    {
        $values = [$variable1[$partIndex], $variable2[$partIndex]];
        sort($values, SORT_STRING);
        return $variable1[$partIndex] === $values[$direction == SortOrder::SORT_ASC ? 0 : 1] ? -1 : 1;
    }

    /**
     * Merge variables from different sources:
     * custom variables and default (stores configuration variables)
     *
     * @return array
     */
    public function getData()
    {
        $searchCriteria = $this->getSearchCriteria();
        $sortOrders = $searchCriteria->getSortOrders();

        // sort items by variable_type
        $sortOrder = $searchCriteria->getSortOrders();
        if (!empty($sortOrder) && $sortOrder[0]->getDirection() == 'DESC') {
            $items = array_merge(
                $this->getCustomVariables(),
                $this->getDefaultVariables()
            );
        } else {
            $items = array_merge(
                $this->getDefaultVariables(),
                $this->getCustomVariables()
            );
        }

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $value = str_replace('%', '', $filter->getValue());
                $filterField = $filter->getField();
                $items = array_values(array_filter($items, function ($item) use ($value, $filterField) {
                    return strpos(strtolower($item[$filterField]), strtolower($value)) !== false;
                }));
            }

        }

        return [
            'items' => $items
        ];
    }
}
