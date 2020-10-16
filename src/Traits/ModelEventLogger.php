<?php

namespace one2tek\laralog\Traits;

use one2tek\laralog\Models\LaraLog;

trait ModelEventLogger
{
    /**
     * Automatically boot with Model, and register Events handler.
     */
    protected static function bootModelEventLogger()
    {
        // Created
        self::created(function ($model) {
            $data = [
                'event_type' => 'created',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => get_class(auth()->user()),
                'causer_id' => auth()->user()->id,
                'properties' => ['new_attributes' => $model->getAttributes()]
            ];

            (new LaraLog)->create($data);
        });

        // Updated
        self::updated(function ($model) {
            $data = [
                'event_type' => 'updated',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => get_class(auth()->user()),
                'causer_id' => auth()->user()->id,
                'properties' => ['new_attributes' => $model->getDirty()]
            ];

            (new LaraLog)->create($data);
        });

        // Deleted
        self::deleted(function ($model) {
            $data = [
                'event_type' => 'deleted',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => get_class(auth()->user()),
                'causer_id' => auth()->user()->id,
                'properties' => []
            ];

            (new LaraLog)->create($data);
        });

        // Pivot detached
        static::pivotDetached(function ($model, $relationName1, $relationName2, $pivotIdsAttributes) {
            $data = [
                'event_type' => 'pivot_detached',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => get_class(auth()->user()),
                'causer_id' => auth()->user()->id,
                'properties' => ['new_attributes' => []]
            ];

            $logAttributes = (get_class_vars(get_class($model))['logAttributes']) ?? [];
            
            if (array_key_exists($relationName2, $logAttributes)) {
                foreach ($pivotIdsAttributes as $attributeId) {
                    $related = $model->$relationName2()->getRelated();
                    $relatedData = $related->select($logAttributes[$relationName2])->whereId($attributeId)->get();
                    
                    foreach ($logAttributes[$relationName2] as $colName) {
                        $data['properties']['new_attributes'][$relationName2][$colName] = $relatedData->pluck($colName)[0];
                    }
                }
            }

            (new LaraLog)->create($data);
        });
    }
}
