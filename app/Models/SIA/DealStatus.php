<?php

namespace App\Models\SIA;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DealStatus extends Model implements AuditableContract
{
    use Auditable;

    protected $connection = 'SIA';

    /**
     * Auditable events.
     *
     * @var array
     */
    protected $auditableEvents = [
        'deleted',
        'restored',
        'updated',
        'created'
    ];

    /**
    * Should the timestamps be audited?
    *
    * @var bool
    */
    protected $auditTimestamps = true;


    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'deal_status';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [];

    use SoftDeletes;
    protected $dates = ['deleted_at'];

}
