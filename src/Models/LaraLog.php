<?php

namespace one2tek\laralog\Models;

use Illuminate\Database\Eloquent\Model;

class LaraLog extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    public $guarded = [];

    /**
     * The attributes that should cast.
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'collection',
    ];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->connection)) {
            $this->setConnection(config('laralog.database_connection'));
        }

        if (! isset($this->table)) {
            $this->setTable(config('laralog.table_name'));
        }

        parent::__construct($attributes);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function causer()
    {
        return $this->morphTo();
    }
}
