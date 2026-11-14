<?php

namespace Webkul\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Webkul\Core\Contracts\CoreConfig as CoreConfigContract;
use Webkul\Core\Services\OpenRouter;

class CoreConfig extends Model implements CoreConfigContract
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'core_config';

    protected $fillable = [
        'code',
        'value',
        'locale',
    ];

    protected $hidden = ['token'];

    public function getValueAttribute(?string $value): ?string
    {
        if ($this->code === OpenRouter::KEY && str_starts_with($value ?? '', 'encrypted:')) {
            return Crypt::decryptString(substr($value, strlen('encrypted:')));
        }

        return $value;
    }

    public function setValueAttribute(mixed $value): void
    {
        $this->attributes['value'] = $this->code === OpenRouter::KEY && filled($value)
            ? 'encrypted:'.Crypt::encryptString((string) $value)
            : $value;
    }

    protected function getArrayableAttributes()
    {
        $attributes = parent::getArrayableAttributes();
        if ($this->code === OpenRouter::KEY) {
            unset($attributes['value']);
        }

        return $attributes;
    }
}
