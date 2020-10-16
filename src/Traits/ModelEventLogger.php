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
    }
}
