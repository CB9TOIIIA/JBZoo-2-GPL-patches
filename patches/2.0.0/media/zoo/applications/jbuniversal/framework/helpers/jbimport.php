<?php
/**
 * JBZoo Application
 *
 * This file is part of the JBZoo CCK package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    Application
 * @license    GPL-2.0
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @link       https://github.com/JBZoo/JBZoo
 * @author     Denis Smetannikov <denis@jbzoo.com>
 */

// no direct access
defined('_JEXEC') or die('Restricted access');


/**
 * Class JBImportHelper
 */
class JBImportHelper extends AppHelper
{
    const STEP_SIZE = 20;

    const LOSE_NONE    = 0;
    const LOSE_DISABLE = 1;
    const LOSE_REMOVE  = 2;

    const KEY_NONE  = 0;
    const KEY_ID    = 1;
    const KEY_NAME  = 2;
    const KEY_ALIAS = 3;
    const KEY_SKU   = 4;

    const OPTIONS_NO  = 0;
    const OPTIONS_YES = 1;

    /**
     * @var JSONData
     */
    protected $_data = null;

    /**
     * @param App $app
     */
    public function __construct($app)
    {
        parent::__construct($app);

        // make sure the line endings are recognized irrespective of the OS
        $this->app->jbenv->maxPerfomance();
        ini_set('auto_detect_line_endings', true);
    }

    /**
     * Get info for pre import step
     * @param string $file
     * @param array $options
     * @return array
     */
    public function getInfo($file, $options)
    {
        $options = $this->app->data->create($options);

        $info = array();

        // get applications
        $applist = JBModelApp::model()->getList();
        if (!empty($applist)) {
            $info['applist'] = array();
            foreach ($applist as $app) {
                $info['applist'][$app->id] = $app->name;
            }

            reset($applist);
            $application = current($applist);

            $info['app'] = current($applist);
        }

        // get types
        $info['types'] = array();
        foreach ($info['app']->getTypes() as $type) {
            $info['types'][$type->id] = array();

            foreach ($type->getElements() as $element) {
                // filter elements
                $info['types'][$type->id][$element->getElementType()][] = $element;
            }

        }

        $info['item_count'] = 0;
        $info['columns']    = array();

        // get params
        $separator       = $options->get('separator', ',') ? $options->get('separator', ',') : ',';
        $enclosure       = $options->get('enclosure', '"') ? $options->get('enclosure', '"') : '"';
        $containsHeaders = (int)$options->get('header', 1);

        // get column names and row count
        $rowCount = 0;
        if (($handle = fopen($file, "r")) !== false) {

            while (($data = fgetcsv($handle, 0, $separator, $enclosure)) !== false) {

                if ($rowCount == 0) {
                    if ($containsHeaders) {
                        $info['columns'] = $data;
                    } else {
                        $info['columns'] = array_fill(0, count($data), '');
                    }
                }

                $rowCount++;
            }
            fclose($handle);
        }

        $info['count'] = $containsHeaders ? $rowCount - 1 : $rowCount;

        return $info;
    }

    /**
     * @param $info
     * @return array
     */
    public function itemsControls($info)
    {
        $html       = array();
        $htmlHelper = $this->app->html;

        $options = array($htmlHelper->_('select.option', '', '- ' . JText::_('JBZOO_SELECT_APP') . ' -'));
        $options += $info['applist'];
        $html['apps'] = $htmlHelper->_('select.genericlist', $options, 'appid');

        $options       = array($htmlHelper->_('select.option', '', '- ' . JText::_('JBZOO_SELECT_TYPE') . ' -'));
        $html['types'] = $htmlHelper->_('zoo.typelist', $info['app'], $options, 'typeid', null, 'value', 'text');

        $html['fields_types'] = array();
        foreach ($info['types'] as $typeid => $element_types) {
            $html['fields_types'][$typeid] = $this->_createItemsControl($typeid, $element_types);
        }

        // lose control
        $loseOptions  = array(
            $htmlHelper->_('select.option', self::LOSE_NONE, JText::_('JBZOO_IMPORT_LOSE_NONE')),
            $htmlHelper->_('select.option', self::LOSE_DISABLE, JText::_('JBZOO_IMPORT_LOSE_DISABLE')),
            $htmlHelper->_('select.option', self::LOSE_REMOVE, JText::_('JBZOO_IMPORT_LOSE_REMOVE')),
        );
        $html['lose'] = $htmlHelper->_('select.genericlist', $loseOptions, 'lose');

        // what field is key
        $keyOptions  = array(
            $htmlHelper->_('select.option', self::KEY_NONE, JText::_('JBZOO_IMPORT_KEY_NONE')),
            $htmlHelper->_('select.option', self::KEY_ID, JText::_('JBZOO_IMPORT_KEY_ID')),
            $htmlHelper->_('select.option', self::KEY_NAME, JText::_('JBZOO_IMPORT_KEY_NAME')),
            $htmlHelper->_('select.option', self::KEY_ALIAS, JText::_('JBZOO_IMPORT_KEY_ALIAS')),
            $htmlHelper->_('select.option', self::KEY_SKU, JText::_('JBZOO_IMPORT_KEY_SKU')),
        );
        $html['key'] = $htmlHelper->_('select.genericlist', $keyOptions, 'key');

        // check options config
        $checkOptions         = array(
            $htmlHelper->_('select.option', self::OPTIONS_NO, JText::_('JBZOO_IMPORT_CHECK_OPTION_NO')),
            $htmlHelper->_('select.option', self::OPTIONS_YES, JText::_('JBZOO_IMPORT_CHECK_OPTION_YES')),
        );
        $html['checkOptions'] = $htmlHelper->_('select.genericlist', $checkOptions, 'checkOptions');

        return $html;
    }

    /**
     * @param $info
     * @return array
     */
    public function categoriesControls($info)
    {
        $html       = array();
        $htmlHelper = $this->app->html;

        $options = array($htmlHelper->_('select.option', '', '- ' . JText::_('JBZOO_SELECT_APP') . ' -'));
        $options += $info['applist'];
        $html['apps'] = $htmlHelper->_('select.genericlist', $options, 'appid');

        $html['fields_types'] = $this->_createCategoriesControl('categoryFileds');

        // lose control
        $loseOptions  = array(
            $htmlHelper->_('select.option', self::LOSE_NONE, JText::_('JBZOO_IMPORT_LOSE_NONE')),
            $htmlHelper->_('select.option', self::LOSE_DISABLE, JText::_('JBZOO_IMPORT_LOSE_DISABLE')),
            $htmlHelper->_('select.option', self::LOSE_REMOVE, JText::_('JBZOO_IMPORT_LOSE_REMOVE')),
        );
        $html['lose'] = $htmlHelper->_('select.genericlist', $loseOptions, 'lose');

        // what field is key
        $keyOptions  = array(
            $htmlHelper->_('select.option', self::KEY_NONE, JText::_('JBZOO_IMPORT_KEY_NONE')),
            $htmlHelper->_('select.option', self::KEY_ID, JText::_('JBZOO_IMPORT_KEY_ID')),
            $htmlHelper->_('select.option', self::KEY_NAME, JText::_('JBZOO_IMPORT_KEY_NAME')),
            $htmlHelper->_('select.option', self::KEY_ALIAS, JText::_('JBZOO_IMPORT_KEY_ALIAS')),
        );
        $html['key'] = $htmlHelper->_('select.genericlist', $keyOptions, 'key');

        return $html;
    }

    /**
     * Create fields control for item
     * @param $typeid
     * @param $elementTypes
     */
    protected function _createItemsControl($typeid, $elementTypes)
    {
        $htmlHelper = $this->app->html;

        $fields  = $this->app->jbcsvmapper->getItemFields($elementTypes);
        $options = array($htmlHelper->_('select.option', '', ' ** '));

        foreach ($fields as $groupKey => $group) {

            $options[] = $htmlHelper->_('select.option', '<OPTGROUP>', JText::_('JBZOO_ITEM_GROUP_' . strtoupper($groupKey)));

            foreach ($group as $fieldKey => $field) {
                $options[] = $htmlHelper->_('select.option', $fieldKey, $field);
            }

            $options[] = $htmlHelper->_('select.option', '</OPTGROUP>');
        }

        return $htmlHelper->_(
            'select.genericlist',
            $options,
            'assign[' . $typeid . '][__name_placeholder__]',
            'class="type-select type-select-' . $typeid . '"'
        );
    }

    /**
     * Create fields control for category
     */
    protected function _createCategoriesControl()
    {
        $htmlHelper = $this->app->html;

        $fields  = $this->app->jbcsvmapper->getCategoryFields();
        $options = array($htmlHelper->_('select.option', '', ' ** '));

        foreach ($fields as $groupKey => $group) {

            $options[] = $htmlHelper->_('select.option', '<OPTGROUP>', JText::_('JBZOO_ITEM_GROUP_' . strtoupper($groupKey)));

            foreach ($group as $fieldKey => $field) {
                $options[] = $htmlHelper->_('select.option', $fieldKey, $field);
            }

            $options[] = $htmlHelper->_('select.option', '</OPTGROUP>');
        }

        return $htmlHelper->_('select.genericlist', $options, 'assign[]', 'class="type-select"');
    }

    /**
     * @return string
     */
    public function getTmpFilename()
    {
        $tmp = $this->app->path->path('tmp:');
        return JPath::clean($tmp . '/' . uniqid('jbimport_') . '.csv');
    }

    /**
     * Get import data from session
     * @return JSONData
     */
    protected function _initSessionData()
    {
        $data        = $this->app->jbsession->getGroup('import');
        $this->_data = $this->app->data->create($data);

        return $this->_data;
    }

    /**
     * Get last line in CSV file
     * @param int $step
     * @return int
     */
    protected function _getLastLine($step = 0)
    {
        $lastLine = self::STEP_SIZE * $step;
        if ((int)$this->_data->header) {
            $lastLine++;
        }

        return $lastLine;
    }

    /**
     * Get lines from CSV file for current step
     * @param string $file
     * @param int $lastLine
     * @return array
     */
    protected function _getCSVLines($file, $lastLine)
    {
        return $this->app->jbcsv->getLinesfromFile($file, $this->_data, $lastLine, self::STEP_SIZE);
    }

    /**
     * Process one Item row
     * @param array $row
     * @param int $lineKey
     * @return int
     */
    protected function _processItemRow($row, $lineKey)
    {
        // create item
        $item = $this->_getItemByKey($row, $lineKey);

        $positions = array();

        // bind import data from CSV
        foreach ($this->_data->assign as $colKey => $itemField) {

            $itemField = JString::trim($itemField);
            if (!empty($itemField)) {

                $value = isset($row[$colKey]) ? $row[$colKey] : null;

                $fieldInfo = $this->app->jbcsvmapper->itemFieldToMeta($itemField);

                $positionKey = implode('__', $fieldInfo);
                if (!isset($positions[$positionKey])) {
                    $positions[$positionKey] = 0;
                }
                $positions[$positionKey]++;

                $cellElem = $this->app->jbcsvcell->createItem($fieldInfo['name'], $item, $fieldInfo['group'], $fieldInfo);
                $cellElem->fromCSV($value, $positions[$positionKey]);
            }
        }

        $id = $item->id;

        // save all changes
        $item->getParams()->set('jbzoo.no_index', 0);
        $this->app->table->item->save($item);

        // clean memory
        unset($item);

        return $id;
    }

    /**
     * Process one Category row
     * @param array $row
     * @param int $lineKey
     * @return int
     */
    protected function _processCategoryRow($row, $lineKey)
    {
        // create item
        $category = $this->_getCategoryByKey($row, $lineKey);

        // bind import data from CSV
        foreach ($this->_data->assign as $colKey => $itemField) {

            $itemField = JString::trim($itemField);
            if (!empty($itemField)) {

                $value = isset($row[$colKey]) ? $row[$colKey] : null;

                $fieldInfo   = $this->app->jbcsvmapper->categoryFieldToMeta($itemField);
                $positionKey = implode('__', $fieldInfo);

                $cellElem = $this->app->jbcsvcell->createCategory($fieldInfo['name'], $category, $fieldInfo['group'], $fieldInfo);
                $cellElem->fromCSV($value);
            }
        }

        $id = $category->id;

        // save all changes
        $this->app->table->category->save($category);

        // clean memory
        unset($category);

        return $id;
    }

    /**
     * Get key field value
     * @param array $row
     * @param string $lineKey
     * @return Item
     */
    protected function _getItemByKey($row, $lineKey = null)
    {
        $item = null;

        if ($this->_data->key != self::KEY_NONE) {
            foreach ($this->_data->assign as $csvKey => $fieldName) {

                if ($this->_data->key == self::KEY_ID && $fieldName == 'id') {
                    $item = JBModelItem::model()->getById(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;

                } else if ($this->_data->key == self::KEY_NAME && $fieldName == 'name') {
                    $item = JBModelItem::model()->getByName(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;

                } else if ($this->_data->key == self::KEY_ALIAS && $fieldName == 'alias') {
                    $item = JBModelItem::model()->getByAlias(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;

                } else if ($this->_data->key == self::KEY_SKU && $fieldName == 'sku') {
                    $item = JBModelItem::model()->getBySku(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;
                }
            }
        }

        if (!$item) {
            $item = JBModelItem::model()->createEmpty($this->_data->appid, $this->_data->typeid, $lineKey);
        }

        return $item;
    }

    /**
     * Get key field value
     * @param array $row
     * @param string $lineKey
     * @return Item
     */
    protected function _getCategoryByKey($row, $lineKey = null)
    {
        $category = null;

        if ($this->_data->key != self::KEY_NONE) {
            foreach ($this->_data->assign as $csvKey => $fieldName) {

                if ($this->_data->key == self::KEY_ID && $fieldName == 'id') {
                    $category = JBModelCategory::model()->getById(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;

                } else if ($this->_data->key == self::KEY_NAME && $fieldName == 'name') {
                    $category = JBModelCategory::model()->getByName(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;

                } else if ($this->_data->key == self::KEY_ALIAS && $fieldName == 'alias') {
                    $category = JBModelCategory::model()->getByAlias(JString::trim($row[$csvKey]), $this->_data->appid);
                    break;
                }
            }
        }

        if (!$category) {
            $category = JBModelCategory::model()->createEmpty($this->_data->appid, $lineKey);
        }

        return $category;
    }

    /**
     * One step precess for items
     * @param int $step
     * @return array
     */
    public function itemsProcess($step = 0)
    {
        $this->_initSessionData();

        $lastLine = $this->_getLastLine($step);
        $lines    = $this->_getCSVLines($this->_data->file, $lastLine, $step);

        $lineKey  = 0;
        $addedIds = ($step == 0) ? array() : $this->app->jbsession->get('ids', 'import-ids');

        if (!empty($lines)) {
            foreach ($lines as $key => $row) {
                $lineKey    = $lastLine + $key;
                $addedIds[] = $this->_processItemRow($row, $lineKey);
            }
        }

        $this->app->jbsession->set('ids', $addedIds, 'import-ids');

        return array('progress' => round(($lineKey / $this->_data->count) * 100, 2));
    }

    /**
     * One step precess for categories
     * @param int $step
     * @return array
     */
    public function categoriesProcess($step = 0)
    {
        $this->_initSessionData();

        $lastLine = $this->_getLastLine($step);
        $lines    = $this->_getCSVLines($this->_data->file, $lastLine, $step);

        $lineKey  = 0;
        $addedIds = ($step == 0) ? array() : $this->app->jbsession->get('ids', 'import-ids');

        if (!empty($lines)) {
            foreach ($lines as $key => $row) {
                $lineKey    = $lastLine + $key;
                $addedIds[] = $this->_processCategoryRow($row, $lineKey);
            }
        }

        $this->app->jbsession->set('ids', $addedIds, 'import-ids');

        return array('progress' => round(($lineKey / $this->_data->count) * 100, 2));
    }

    /**
     * Call after all items loaded
     */
    public function itemsPostProcess()
    {
        $addedIds = $this->app->jbsession->get('ids', 'import-ids');
        $this->_initSessionData();

        if ($this->_data->lose == self::LOSE_DISABLE) {
            JBModelItem::model()->disableAll($this->_data->appid, $this->_data->typeid, $addedIds);

        } else if ($this->_data->lose == self::LOSE_REMOVE) {
            JBModelItem::model()->removeAll($this->_data->appid, $this->_data->typeid, $addedIds);
        }

        $this->app->jbsession->clearGroup('import-ids');
    }

    /**
     * Call after all items loaded
     */
    public function categoriesPostProcess()
    {
        $addedIds = $this->app->jbsession->get('ids', 'import-ids');
        $this->_initSessionData();

        if ($this->_data->lose == self::LOSE_DISABLE) {
            JBModelCategory::model()->disableAll($this->_data->appid, $addedIds);

        } else if ($this->_data->lose == self::LOSE_REMOVE) {
            JBModelCategory::model()->removeAll($this->_data->appid, $addedIds);
        }

        $this->app->jbsession->clearGroup('import-ids');
    }


}
