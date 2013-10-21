<?php

//Copyright 2011, Marc Busqué Pérez
//
//This file is a part of Yii Sortable Model
//
//Yii Sortable Model is free software: you can redistribute it and/or modify
//it under the terms of the GNU Lesser General Public License as published by
//the Free Software Foundation, either version 3 of the License, or
//(at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU Lesser General Public License for more details.
//
//You should have received a copy of the GNU Lesser General Public License
//along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

/**
 * Models having this behavior attached will automatically keep its records order consistent when items are added or deleted.
 * @author Marc Busqué Pérez <marc@lamarciana.com>
 * @package Yii Sortable Model
 * @copyright Copyright &copy; 2012 Marc Busqué Pérez
 * @license LGPL
 * @since 1.1
 */
class SortableCActiveRecordBehavior extends CActiveRecordBehavior {

    /**
     * @var string the field name in the database table which stores the order for the record. This should be a positive integer field. Defaults to 'order'
     */
    public $orderField = 'order';
    public $filterByColumn = null;

    /**
     * Responds to {@link CActiveRecord::onBeforeSave} event.
     * @param CModelEvent $event event parameter
     */
    public function beforeSave($event) {
        $sender = $event->sender;
        
        $filterByColumnVals = null;
        if (isset($this->filterByColumn)) {
            if (is_string($this->filterByColumn)) {
                $filterByColumnVals[$this->filterByColumn] = $sender->{$this->filterByColumn};
            } else if (is_array($this->filterByColumn)) {
                foreach ($this->filterByColumn as $column) {
                    $filterByColumnVals[$column] = $sender->{$column};
                }
            } else {
                throw new CDbException('SortableCActiveRecordBehavior expects filterByColumn to be a string or array of strings');
            }
        }

        self::updateOrderBeforeSave(
                 $sender, 
                 $this->orderField, 
                 $filterByColumnVals
                 );
        return parent::beforeSave($event);
        
    }
    
    public static function updateOrderBeforeSave($arModel, $orderField, $filterByColumnVals){
        if($arModel->isNewRecord){
            $criteria = new CDbCriteria();
            $criteria->order =  '`' . $orderField . '` DESC';
            $criteria->limit = 1;
            $criteria->params = array();
            
            if(isset($filterByColumnVals) && is_array($filterByColumnVals)){
                foreach($filterByColumnVals as $column => $value){
                    $criteria->addCondition("{$column}=:{$column}");
                    $criteria->params[":{$column}"] = $value;
                }
            }
            
            $finder = call_user_func(array(get_class($arModel), 'model'));
            $last_record = $finder->find($criteria);
            
            if (isset($last_record)) {
                $arModel->{$orderField} = $last_record->{$orderField} + 1;
            } else {
                $arModel->{$orderField} = 1;
            }
        }
    }

    /**
     * Responds to {@link CActiveRecord::onBeforeDelete} event.
     * Update records order field in a manner that their values are still successively increased by one (so, there is no gap caused by the deleted record)
     * @param CEvent $event event parameter
     */
    public function afterDelete($event) {
        $sender = $event->sender;
        $filterByColumnVals = null;
        if(isset($this->filterByColumn)){
            if(is_string($this->filterByColumn)){
                $filterByColumnVals[$this->filterByColumn] = $sender->{$this->filterByColumn};
            }else if(is_array($this->filterByColumn)){
                foreach($this->filterByColumn as $column){
                    $filterByColumnVals[$column] = $sender->{$column};
                }
            }else{
                throw new CDbException('SortableCActiveRecordBehavior expects filterByColumn to be a string or array of strings');
            }
        }
        self::updateOrderAfterDelete(
                $sender, 
                $this->orderField, 
                $sender->{$this->orderField}, 
                $filterByColumnVals
                );

        return parent::afterDelete($event);
    }

    public static function updateOrderAfterDelete($arModel, $orderField, $deletedValueOrderField, $filterByColumnVals) {
        $criteria = new CDbCriteria();
        $criteria->order = '`' . $orderField . '` ASC';
        $criteria->addCondition('`' . $orderField . '` > ' . $deletedValueOrderField);
        $criteria->params = array();
        
         if(isset($filterByColumnVals) && is_array($filterByColumnVals)){
                foreach($filterByColumnVals as $column => $value){
                    $criteria->addCondition("{$column}=:{$column}");
                    $criteria->params[":{$column}"] = $value;
                }
            }
        
       
        $finder = call_user_func(array(get_class($arModel), 'model'));   
        $following_records = $finder->findAll($criteria);
         foreach ($following_records as $record) {
            $record->{$orderField}--;
            $record->update();
        }
        
    }

}
