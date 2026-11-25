<?php

namespace App\Domain\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Menulis log aktivitas append-only dengan rantai hash.
 * hash = sha256(prev_hash + isi baris). Mengubah/menghapus satu baris di database akan memutus rantai.
 */
class ActivityLogger
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): ActivityLog
    {
        $user ??= auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $row = [
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? $this->labelFor($subject) : null,
            'description' => mb_substr($description, 0, 1000),
            'properties' => $properties ? json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : 'console',
        ];

        return DB::transaction(function () use ($row) {
            // Kunci penghitung khusus supaya rantai hash tidak bercabang saat dua permintaan bersamaan.
            DB::table('number_sequences')->insertOrIgnore(['scope_key' => '__activity_chain', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('number_sequences')->where('scope_key', '__activity_chain')->lockForUpdate()->first();

            $prev = DB::table('activity_logs')->orderByDesc('id')->value('hash') ?? self::GENESIS;
            $row['prev_hash'] = $prev;
            $row['hash'] = self::hashRow($row);

            $id = DB::table('activity_logs')->insertGetId($row);

            return ActivityLog::find($id);
        });
    }

    public static function hashRow(array $row): string
    {
        $fields = ['occurred_at', 'user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'description', 'properties', 'ip', 'user_agent'];
        $payload = $row['prev_hash'];
        foreach ($fields as $f) {
            $v = $row[$f] ?? null;
            if ($f === 'occurred_at' && $v) {
                $v = substr((string) $v, 0, 26);
            }
            if ($f === 'properties' && $v !== null && ! is_string($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if ($f === 'properties' && $v !== null) {
                // Normalisasi JSON (MySQL dapat menata ulang spasi).
                $v = json_encode(json_decode($v, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $payload .= '|'.$f.'='.($v === null ? '' : (string) $v);
        }

        return hash('sha256', $payload);
    }

    /** Memeriksa seluruh rantai. Mengembalikan daftar ID baris yang bermasalah (kosong = utuh). */
    public function verify(): array
    {
        $broken = [];
        $prev = self::GENESIS;
        DB::table('activity_logs')->orderBy('id')->chunk(1000, function ($rows) use (&$prev, &$broken) {
            foreach ($rows as $r) {
                $row = (array) $r;
                if ($row['prev_hash'] !== $prev || self::hashRow($row) !== $row['hash']) {
                    $broken[] = $row['id'];
                }
                $prev = $row['hash'];
            }
        });

        return $broken;
    }

    private function labelFor(Model $subject): string
    {
        foreach (['number', 'email', 'code', 'name', 'title'] as $attr) {
            if (! empty($subject->getAttribute($attr))) {
                return (string) $subject->getAttribute($attr);
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
