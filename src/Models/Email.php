<?php

namespace RickDBCN\FilamentEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Email
 *
 * @property string $from
 * @property string $to
 * @property string $cc
 * @property string $bcc
 * @property string $subject
 * @property string $text_body
 * @property string $html_body
 * @property string $raw_body
 * @property string $sent_debug_info
 * @property Carbon|null $created_at
 */
class Email extends Model
{
    use HasFactory;
    use Prunable;

    protected $table = 'filament_email_log';

    protected $guarded = [];

    private $defaultSearchFields = [
        'subject',
        'from',
        'to',
    ];

    public static function boot()
    {
        parent::boot();

        self::deleting(function ($record) {
            if (!empty($record->attachments)) {
                foreach (json_decode($record->attachments) as $attachment) {
                    $filePath = storage_path('app' . DIRECTORY_SEPARATOR . $attachment->path);
                    $parts = explode(DIRECTORY_SEPARATOR, $filePath);
                    array_pop($parts);
                    $folderPath = implode(DIRECTORY_SEPARATOR, $parts);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    if (file_exists($folderPath)) {
                        rmdir($folderPath);
                    }
                }
            }
        });
    }

    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(Config::get('filament-email.keep_email_for_days')));
    }

    private function getTableColumns()
    {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    private function getSearchableFields()
    {
        $columns = $this->getTableColumns();
        $fields = Config::get('filament-email.resource.table_search_fields', $this->defaultSearchFields);

        return Arr::where($fields, function ($value) use ($columns) {
            return in_array($value, $columns);
        });
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            foreach ($this->getSearchableFields() as $key => $field) {
                $query->{$key > 0 ? 'orWhere' : 'where'}($field, 'LIKE', "%{$search}%");
            }
        });
    }
}
