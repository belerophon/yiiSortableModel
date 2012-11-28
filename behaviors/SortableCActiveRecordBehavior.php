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
class SortableCActiveRecordBehavior extends CActiveRecordBehavior
{
   /**
    * @var string the field name in the database table which stores the order for the record. This should be a positive integer field. Defaults to 'order'
    */
   public $orderField = 'order';   
   public $filterByColumn = null;


   /**
    * Responds to {@link CActiveRecord::onBeforeSave} event.
    * @param CModelEvent $event event parameter
    */
   public function beforeSave($event)
   {
      $sender = $event->sender;
      if ($sender->isNewRecord) {
         $model = call_user_func(array(get_class($sender), 'model'));
         
         $criteria = new CDbCriteria();
         $criteria->order = '`'.$this->orderField.'` DESC';
         $criteria->limit = 1;
         if($this->filterByColumn){
             $criteria->condition = "{$this->filterByColumn}=:filterColumn";
             $criteria->params = array(':filterColumn' => $sender->{$this->filterByColumn});
         }
         //$temp1 = $sender->{$this->filterByColumn};
         $last_record = $model->find($criteria);
         //$temp = $last_record->{$this->orderField};
         if ($last_record) {
            $sender->{$this->orderField} = $last_record->{$this->orderField} + 1;
         } else {
            $sender->{$this->orderField} = 1;
         }
      }

      return parent::beforeSave($event);
   } 

   /**
    * Responds to {@link CActiveRecord::onBeforeDelete} event.
    * Update records order field in a manner that their values are still successively increased by one (so, there is no gap caused by the deleted record)
    * @param CEvent $event event parameter
    */
   public function afterDelete($event)
   {
      $sender = $event->sender;
      $model = call_user_func(array(get_class($sender), 'model'));
      
      $criteria = new CDbCriteria();
      $criteria->order = '`'.$this->orderField.'` ASC';
      $criteria->addCondition('`'.$this->orderField.'` > '.$sender->{$this->orderField});
      if($this->filterByColumn){
         $criteria->addCondition("{$this->filterByColumn}=:filterColumn");
         $criteria->params = array(':filterColumn' => $sender->{$this->filterByColumn});
      }
         
      $following_records = $model->findAll($criteria);
      foreach ($following_records as $record) {
         $record->{$this->orderField}--;
         $record->update();
      }

      return parent::afterDelete($event);
   }
}
