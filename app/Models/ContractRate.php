<?php

namespace App\Models;

class ContractRate extends BaseModel
{
    public function contract() { return $this->belongsTo(OperatorContract::class, 'operator_contract_id'); }
    public function villaType() { return $this->belongsTo(VillaType::class); }
}
