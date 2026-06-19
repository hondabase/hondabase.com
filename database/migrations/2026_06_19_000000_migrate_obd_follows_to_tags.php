<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAP = [
        '0' => ['obd0', 'obd0'],
        '1' => ['obd1', 'obd1'],
        '2' => ['obd2', 'obd2'],
        '2a' => ['obd2a', 'obd2a'],
        '2b' => ['obd2b', 'obd2b'],
        'obd0' => ['obd0', 'obd0'],
        'obd1' => ['obd1', 'obd1'],
        'obd2' => ['obd2', 'obd2'],
        'obd2a' => ['obd2a', 'obd2a'],
        'obd2b' => ['obd2b', 'obd2b'],
    ];

    public function up(): void
    {
        DB::table('follows')->where('kind', 'obd')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $value = strtolower(trim((string) $row->value));
                if (! isset(self::MAP[$value])) {
                    continue;
                }
                [$tagValue, $label] = self::MAP[$value];

                $existing = DB::table('follows')
                    ->where('user_id', $row->user_id)
                    ->where('kind', 'tag')
                    ->where('value', $tagValue)
                    ->first();

                if ($existing) {
                    DB::table('follows')->where('id', $row->id)->delete();

                    continue;
                }

                DB::table('follows')->where('id', $row->id)->update([
                    'kind' => 'tag',
                    'value' => $tagValue,
                    'label' => $label,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('follows')->where('kind', 'tag')->whereIn('value', [
            'obd0', 'obd1', 'obd2', 'obd2a', 'obd2b',
        ])->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $obdValue = substr((string) $row->value, 3);
                $existing = DB::table('follows')
                    ->where('user_id', $row->user_id)
                    ->where('kind', 'obd')
                    ->where('value', $obdValue)
                    ->first();

                if ($existing) {
                    DB::table('follows')->where('id', $row->id)->delete();

                    continue;
                }

                DB::table('follows')->where('id', $row->id)->update([
                    'kind' => 'obd',
                    'value' => $obdValue,
                    'label' => 'OBD'.$obdValue,
                ]);
            }
        });
    }
};
