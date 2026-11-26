<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasAttachments;

    public const CONDITIONS = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak ringan', 'rusak_berat' => 'Rusak berat', 'afkir' => 'Afkir'];

    protected $fillable = [
        'number', 'category', 'name', 'brand_model', 'serial_number', 'location', 'quantity', 'condition', 'acquired_at',
        'inspection_interval_months', 'next_inspection_at', 'needs_calibration', 'next_calibration_at', 'status', 'notes', 'created_by',
    ];

    protected $casts = ['acquired_at' => 'date', 'next_inspection_at' => 'date', 'next_calibration_at' => 'date', 'needs_calibration' => 'boolean'];

    public function logs(): HasMany
    {
        return $this->hasMany(FacilityLog::class)->orderByDesc('performed_at')->orderByDesc('id');
    }

    public function conditionColor(): string
    {
        return ['baik' => 'green', 'rusak_ringan' => 'amber', 'rusak_berat' => 'red', 'afkir' => 'slate'][$this->condition] ?? 'slate';
    }

    public function isInspectionDue(): bool
    {
        return $this->status === 'aktif' && $this->next_inspection_at && $this->next_inspection_at->lte(now()->addDays(14));
    }

    public function isCalibrationDue(): bool
    {
        return $this->status === 'aktif' && $this->needs_calibration && $this->next_calibration_at && $this->next_calibration_at->lte(now()->addDays(14));
    }

    public function folderPath(): string
    {
        return 'Sarana/'.$this->category.'/'.$this->number;
    }
}
