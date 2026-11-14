<?php

namespace Webkul\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Webkul\Core\Contracts\CoreConfig as CoreConfigContract;
use Webkul\Core\Services\CrmAi;

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
        if (in_array($this->code, CrmAi::keyFields(), true) && str_starts_with($value ?? '', 'encrypted:')) {
            return Crypt::decryptString(substr($value, strlen('encrypted:')));
        }

        return $value;
    }

    public function setValueAttribute(mixed $value): void
    {
        $this->attributes['value'] = in_array($this->code, CrmAi::keyFields(), true) && filled($value)
            ? 'encrypted:'.Crypt::encryptString((string) $value)
            : $value;
    }

    protected function getArrayableAttributes()
    {
        $attributes = parent::getArrayableAttributes();
        if (in_array($this->code, CrmAi::keyFields(), true)) {
            unset($attributes['value']);
        }

        return $attributes;
    }
}
