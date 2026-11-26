<?php

namespace App\Models;

use App\Models\Concerns\HasApproval;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementReview extends Model
{
    use HasApproval, HasAttachments;

    /** Masukan wajib ISO 9001:2015 klausul 9.3.2 */
    public const INPUTS = [
        'tindak_lanjut_sebelumnya' => 'a. Status tindakan dari tinjauan manajemen sebelumnya',
        'isu_internal_eksternal' => 'b. Perubahan isu eksternal & internal yang relevan',
        'kepuasan_pelanggan' => 'c.1 Kepuasan pelanggan & umpan balik pihak berkepentingan',
        'sasaran_mutu' => 'c.2 Tingkat pencapaian sasaran mutu',
        'kinerja_proses' => 'c.3 Kinerja proses & kesesuaian layanan',
        'ketidaksesuaian_capa' => 'c.4 Ketidaksesuaian & tindakan korektif',
        'pemantauan' => 'c.5 Hasil pemantauan & pengukuran',
        'hasil_audit' => 'c.6 Hasil audit',
        'kinerja_pemasok' => 'c.7 Kinerja penyedia eksternal (pemasok)',
        'sumber_daya' => 'd. Kecukupan sumber daya',
        'risiko_peluang' => 'e. Efektivitas tindakan menangani risiko & peluang',
        'peluang_perbaikan' => 'f. Peluang perbaikan',
    ];

    /** Keluaran ISO 9001:2015 klausul 9.3.3 */
    public const OUTPUTS = [
        'peluang_perbaikan' => 'a. Peluang perbaikan',
        'perubahan_smm' => 'b. Kebutuhan perubahan sistem manajemen mutu',
        'kebutuhan_sumber_daya' => 'c. Kebutuhan sumber daya',
        'kesimpulan' => 'Kesimpulan kesesuaian & efektivitas SMM',
    ];

    protected $fillable = [
        'number', 'meeting_date', 'period_from', 'period_to', 'location', 'attendees', 'inputs', 'outputs', 'status',
        'approved_by', 'approved_by_name', 'approved_by_position', 'approved_at', 'approval_note', 'file_id', 'created_by',
    ];

    protected $casts = ['meeting_date' => 'date', 'period_from' => 'date', 'period_to' => 'date', 'inputs' => 'array', 'outputs' => 'array', 'approved_at' => 'datetime'];

    public function actions(): HasMany
    {
        return $this->hasMany(ReviewAction::class)->orderBy('due_date');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
