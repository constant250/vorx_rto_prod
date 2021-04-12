<?php

namespace App\Models\SIA;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PartyRelationship extends Model implements AuditableContract
{
    //
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
    
    public function party()
    {
    	return $this->belongsTo(Party::class);
    }
}
