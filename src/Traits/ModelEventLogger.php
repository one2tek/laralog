<?php

namespace one2tek\laralog\Traits;

use one2tek\laralog\Jobs\CreateLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait ModelEventLogger
{
    protected static function bootModelEventLogger()
    {
        if (self::allowLogs() == false) {
            return;
        }
        
        // Created
        self::created(function ($model) {
            if (empty($model->getDirtyAttributes())) {
                return;
            }

            $data = [
                'event_type' => 'created',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => [
                    'new_attributes' => $model->getDirtyAttributes(),
                    'all_attributes' => $model->getAllAttributes()
                ],
            ];

            $relationsAttributes = self::logRelationshipAttributes($model, 'created');
            if (count($relationsAttributes)) {
                foreach ($relationsAttributes as $key => $value) {
                    $data['properties']['new_attributes'][$key] = $value;
                }
            }
            
            CreateLog::dispatch($data)->onQueue('laralog');
        });

        // Updated
        self::updated(function ($model) {
            if (empty($model->getDirtyAttributes())) {
                return;
            }

            $data = [
                'event_type' => 'updated',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => [
                    'new_attributes' => $model->getDirtyAttributes(),
                    'all_attributes' => $model->getAllAttributes()
                ]
            ];

            $relationsAttributes = self::logRelationshipAttributes($model, 'updated');
            if (count($relationsAttributes)) {
                foreach ($relationsAttributes as $key => $value) {
                    $data['properties']['new_attributes'][$key] = $value;
                }
            }

            CreateLog::dispatch($data)->onQueue('laralog');
        });

        // Deleted
        self::deleted(function ($model) {
            $data = [
                'event_type' => 'deleted',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'causer_id' => (auth()->check()) ? auth()->user()->id : null,
                'properties' => []
            ];

            CreateLog::dispatch($data)->onQueue('laralog');
        });

        // Pivot detached
        static::pivotDetached(function ($model, $relationName1, $relationName2, $pivotIdsAttributes) {
            $data = [
                'event_type' => 'pivot_detached',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
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
                CreateLog::dispatch($data)->onQueue('laralog');
            }
        });

        // Pivot attached
        static::pivotAttached(function ($model, $relationName1, $relationName2, $pivotIdsAttributes) {
            $data = [
                'event_type' => 'pivot_attached',
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
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
                CreateLog::dispatch($data)->onQueue('laralog');
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

    protected static function allowLogs()
    {
        if (in_array(app()->environment(), config('laralog.disable_on_environments'))) {
            return false;
        }

        if (isset(static::$allowLogs)) {
            return static::$allowLogs;
        }

        return true;
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

    protected function getAllAttributes()
    {
        $allAttributes = self::getAttributes();
        $attributesShouldBeMaskedBeforeLogged = self::attributesShouldBeMaskedBeforeLogged();
        $attributes = [];

        foreach ($allAttributes as $key => $value) {
            if (in_array($key, $attributesShouldBeMaskedBeforeLogged)) {
                $attributes[$key] = '******';
            } else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
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