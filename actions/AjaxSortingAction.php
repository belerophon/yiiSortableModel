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

/**
 * This action is triggered by SortableCGridView widget to store in the background the new records order.
 * @author Marc Busqué Pérez <marc@lamarciana.com>
 * @package Yii Sortable Model
 * @copyright Copyright &copy; 2012 Marc Busqué Pérez
 * @license LGPL
 * @since 1.0
 */
class AjaxSortingAction extends CAction {

    public $filterByColumn = null;

    public function run() {
        if (isset($_POST)) {
            $order_field = $_POST['order_field'];
            $model = call_user_func(array($_POST['model'], 'model'));
            $dragged_entry = $model->findByPk($_POST['dragged_item_id']);
            /* load dragged entry before changing orders */
            $prev = $dragged_entry->{$order_field};

            $new = $model->findByPk($_POST['replacement_item_id'])->{$order_field};
            /* filter a subset from the table */
            $filterByColumnVals = null;
            if (isset($this->filterByColumn)) {
                if (is_string($this->filterByColumn)) {
                    $filterByColumnVals[$this->filterByColumn] = $dragged_entry->{$this->filterByColumn};
                } else if (is_array($this->filterByColumn)) {
                    foreach ($this->filterByColumn as $column) {
                        $filterByColumnVals[$column] = $dragged_entry->{$column};
                    }
                } else {
                    throw new CDbException('SortableCActiveRecordBehavior expects filterByColumn to be a string or array of strings');
                }
            }
            
            /* @var $db CDbConnection */
            $db = $model->getDbConnection();
            $success = true;
            if ($db->getCurrentTransaction() === null) {
                $transaction = $db->beginTransaction();
            }
            try {
                /* update order only for the affected records */
                if ($prev < $new) {
                    for ($i = $prev + 1; $i <= $new; $i++) {
                        $entry = $model->findByAttributes(array_merge($filterByColumnVals, array($order_field => $i)));
                        $entry->{$order_field} = $entry->{$order_field} - 1;
                        $success = $success && $entry->update();
                    }
                } elseif ($prev > $new) {
                    for ($i = $prev - 1; $i >= $new; $i--) {
                        $entry = $model->findByAttributes(array_merge($filterByColumnVals, array($order_field => $i)));
                        $entry->{$order_field} = $entry->{$order_field} + 1;
                        $success = $success && $entry->update();
                    }
                }
                /* dragged entry order is changed at last, to not interfere during the changing orders loop */
                $dragged_entry->{$order_field} = ($new == $prev) ? $new + 1 : $new;
                $success = $success && $dragged_entry->update();
                if (isset($transaction)) {
                    if ($success === true) {
                        $transaction->commit();
                    } else {
                        $transaction->rollback();
                        throw new CDbException("Could sort the set! Some rows could not be updated!");
                    }
                }
            } catch (Exception $ex) {
                if (isset($transaction)) {
                    $transaction->rollback();
                }
                throw $ex;
            }
        }
    }

}
