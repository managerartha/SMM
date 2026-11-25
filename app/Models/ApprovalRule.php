<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRule extends Model
{
    protected $fillable = ['subject_key', 'label', 'approver_role', 'mode', 'phase', 'updated_by'];
}
