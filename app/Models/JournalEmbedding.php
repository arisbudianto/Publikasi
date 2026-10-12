<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class JournalEmbedding extends Model
{
    protected $fillable = ['model', 'scope_hash', 'dimensions', 'vector'];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public static function encode(array $vector): string
    {
        if (count($vector) < 2 || count($vector) > 4096) {
            throw new RuntimeException('Dimensi embedding tidak valid.');
        }

        foreach ($vector as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new RuntimeException('Nilai embedding tidak valid.');
            }
        }

        return pack('g*', ...array_map('floatval', $vector));
    }

    public function decodedVector(): array
    {
        $bytes = $this->vector;
        if (is_resource($bytes)) {
            $bytes = stream_get_contents($bytes);
        }
        if (! is_string($bytes) || strlen($bytes) !== $this->dimensions * 4) {
            throw new RuntimeException('Data embedding tersimpan tidak valid.');
        }

        $decoded = unpack('g*', $bytes);
        if (! is_array($decoded) || count($decoded) !== $this->dimensions) {
            throw new RuntimeException('Dimensi embedding tersimpan tidak valid.');
        }

        return array_values($decoded);
    }
}
