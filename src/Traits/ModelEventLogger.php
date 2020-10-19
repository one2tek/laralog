<?php

namespace one2tek\laralog\Traits;

use one2tek\laralog\Models\LaraLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait ModelEventLogger
{
    protected static function bootModelEventLogger()
    {
        // Created
        self::created(function ($model) {
            $data = [
                'event_type' => 'created',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => (auth()->check()) ? get_class(auth()->user()) : null,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => ['new_attributes' => $model->getDirtyAttributes()]
            ];

            $relationsAttributes = self::logRelationshipAttributes($model, 'created');
            if (count($relationsAttributes)) {
                foreach ($relationsAttributes as $key => $value) {
                    $data['properties']['new_attributes'][$key] = $value;
                }
            }

            (new LaraLog)->create($data);
        });

        // Updated
        self::updated(function ($model) {
            $data = [
                'event_type' => 'updated',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => (auth()->check()) ? get_class(auth()->user()) : null,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => [
                    'new_attributes' => $model->getDirtyAttributes()
                ]
            ];

            $relationsAttributes = self::logRelationshipAttributes($model, 'updated');
            if (count($relationsAttributes)) {
                foreach ($relationsAttributes as $key => $value) {
                    $data['properties']['new_attributes'][$key] = $value;
                }
            }

            (new LaraLog)->create($data);
        });

        // Deleted
        self::deleted(function ($model) {
            $data = [
                'event_type' => 'deleted',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => (auth()->check()) ? get_class(auth()->user()) : null,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
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
                'causer_type' => (auth()->check()) ? get_class(auth()->user()) : null,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => ['new_attributes' => []]
            ];

            $logAttributes = $model->attributesShouldBeLogged();
            
            if (array_key_exists($relationName2, $logAttributes)) {
                foreach ($pivotIdsAttributes as $attributeId) {
                    $related = $model->$relationName2()->getRelated();
                    $relatedData = $related->select($logAttributes[$relationName2])->whereId($attributeId)->get();
                    
                    foreach ($logAttributes[$relationName2] as $colName) {
                        $data['properties']['new_attributes'][$relationName2][$colName] = $relatedData->pluck($colName)[0];
                    }
                }
            }

            if (count($data['properties']['new_attributes'])) {
                (new LaraLog)->create($data);
            }
        });

        // Pivot attached
        static::pivotAttached(function ($model, $relationName1, $relationName2, $pivotIdsAttributes) {
            $data = [
                'event_type' => 'pivot_attached',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_type' => (auth()->check()) ? get_class(auth()->user()) : null,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => ['new_attributes' => []]
            ];

            $logAttributes = $model->attributesShouldBeLogged();
            
            if (array_key_exists($relationName2, $logAttributes)) {
                foreach ($pivotIdsAttributes as $attributeId) {
                    $related = $model->$relationName2()->getRelated();
                    $relatedData = $related->select($logAttributes[$relationName2])->whereId($attributeId)->get();
                    
                    foreach ($logAttributes[$relationName2] as $colName) {
                        $data['properties']['new_attributes'][$relationName2][$colName] = $relatedData->pluck($colName)[0];
                    }
                }
            }

            if (count($data['properties']['new_attributes'])) {
                (new LaraLog)->create($data);
            }
        });
    }

    protected static function logRelationshipAttributes($model, $event)
    {
        $data = [];

        $logAttributes = $model->attributesShouldBeLogged();
        foreach ($logAttributes as $relName => $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $relationInstance = $model->attributesInfo($model, $relName, 'instance');
            if ($relationInstance == 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
                $relationForeign = $model->attributesInfo($model, $relName, 'foreign');
                
                if ($event != 'created') {
                    if (!$model->isDirty($relationForeign)) {
                        continue;
                    }
                }

                foreach ($attribute as $attr) {
                    if (isset($model->$relName->$attr)) {
                        $data[$relName][$attr] = $model->$relName->$attr;
                    }
                }
            }
        }

        return $data;
    }

    protected function attributesShouldBeLogged()
    {
        return static::$logAttributes;
    }

    protected function attributesShouldBeIgnoredFromLogs()
    {
        return static::$ignoreLogAttributes;
    }

    protected function attributesShouldBeMaskedBeforeLogged()
    {
        return static::$maskBeforeLogAttributes;
    }

    protected static function attributesInfo($model, $attributeName, $infoType)
    {
        $relation = $model->$attributeName();
        $info = null;

        switch ($infoType) {
            case 'instance':
                $info = get_class($relation) ;
                break;

            case 'foreign':
                if ($relation instanceof BelongsTo) {
                    $info = $relation->getQualifiedForeignKeyName();
                    $lastColumn = explode('.', $info);
                    $lastColumn = end($lastColumn);
                    $info = str_replace('.'. $lastColumn, '', $lastColumn);
                } elseif ($relation instanceof BelongsToMany) {
                    $info = $relation->getQualifiedForeignPivotKeyName;
                } else {
                    $info = $relation->getQualifiedForeignKeyName;
                }
                break;
        }

        return $info;
    }

    protected function getDirtyAttributes()
    {
        $attributesShouldBeLogged = self::attributesShouldBeLogged();
        $attributesShouldBeIgnoredFromLogs = self::attributesShouldBeIgnoredFromLogs();
        $attributesShouldBeMaskedBeforeLogged = self::attributesShouldBeMaskedBeforeLogged();
        $allowToLogAllAttributes = in_array('*', $attributesShouldBeLogged);
        $dirtyAttributes = self::getDirty();

        foreach ($dirtyAttributes as $key => $value) {
            if ($allowToLogAllAttributes) {
                if (in_array($key, $attributesShouldBeIgnoredFromLogs)) {
                    unset($dirtyAttributes[$key]);
                } else {
                    if (in_array($key, $attributesShouldBeMaskedBeforeLogged)) {
                        $dirtyAttributes[$key] = '******';
                    }
                }
            } elseif (!in_array($key, $attributesShouldBeLogged)) {
                unset($dirtyAttributes[$key]);
            }
        }

        return $dirtyAttributes;
    }
}
