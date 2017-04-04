<?php namespace Oca\ListManager\Models;

use Model;

/**
 * Model
 */
class ListOriginal extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /*
     * Validation
     */
    public $rules = [
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'oca_listmanager_list_original';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['created_at'];
}