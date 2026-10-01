<?php

namespace App\Models;

class Shift extends BaseModel
{
    public function rosterEntries() { return $this->hasMany(RosterEntry::class); }

    public function label(): string { return $this->name.' ('.substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5).')'; }
}
