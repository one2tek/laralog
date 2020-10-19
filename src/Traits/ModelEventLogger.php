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

            $logAttributes = $model->attributesToBeLogged();
            foreach ($logAttributes as $relName => $attribute) {
                if (is_array($attribute)) {
                    $relationInstance = $model->attributesInfo($relName, 'instance');
                    if ($relationInstance == 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
                        $relationForeign = $model->attributesInfo($relName, 'foreign');
                        
                        if ($model->isDirty($relationForeign)) {
                            foreach ($attribute as $attr) {
                                if (isset($model->$relName->$attr)) {
                                    $data['properties']['new_attributes'][$relName][$attr] = $model->$relName->$attr;
                                }
                            }
                        }
                    }
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

            $logAttributes = $model->attributesToBeLogged();
            foreach ($logAttributes as $relName => $attribute) {
                if (is_array($attribute)) {
                    $relationInstance = $model->attributesInfo($relName, 'instance');
                    if ($relationInstance == 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
                        $relationForeign = $model->attributesInfo($relName, 'foreign');
                        
                        if ($model->isDirty($relationForeign)) {
                            foreach ($attribute as $attr) {
                                if (isset($model->$relName->$attr)) {
                                    $data['properties']['new_attributes'][$relName][$attr] = $model->$relName->$attr;
                                }
                            }
                        }
                    }
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

            $logAttributes = $model->attributesToBeLogged();
            
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

            $logAttributes = $model->attributesToBeLogged();
            
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

    protected function attributesToBeLogged()
    {
        return static::$logAttributes;
    }

    protected function attributesToBeNotLogged()
    {
        return static::$ignoreChangedAttributes;
    }

    protected static function attributesInfo($attributeName, $infoType)
    {
        $relation = self::$attributeName();
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
        $logAttributes = self::attributesToBeLogged();
        $notlogAttributes = self::attributesToBeNotLogged();

        $allowToLogAllAttributes = in_array('*', $logAttributes);

        $dirtyAttributes = self::getDirty();

        foreach ($dirtyAttributes as $key => $value) {
            if ($allowToLogAllAttributes) {
                if (in_array($key, $notlogAttributes)) {
                    unset($dirtyAttributes[$key]);
                }
            } elseif (!in_array($key, $logAttributes)) {
                unset($dirtyAttributes[$key]);
            }
        }

        return $dirtyAttributes;
    }
}
