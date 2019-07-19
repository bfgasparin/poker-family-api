<?php

namespace App\Eloquent\Concerns;

/**
 * Eloquent Model helper for Models that fire model events when the attribute 'status' changes
 */
trait HasStatusAttributeEvents
{
    /**
     * Boot the has HasStatusAttributeEvents trait for a model.
     *
     * @return void
     */
    public static function bootHasStatusAttributeEvents()
    {
        static::updated(function ($model) {
            // Note: do not use wasChanged method from Laravel model, because at this point, the changed attribute is not synced yet. Use isDirty instead
            if ($model->isDirty('status')) {
                // fire status events if status was changed
                $model->fireModelEvent(camel_case($model->status), false);
            }
        });

    }
}


