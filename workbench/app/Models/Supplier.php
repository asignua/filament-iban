<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $country
 * @property string|null $iban
 * @property string|null $notes
 */
class Supplier extends Model
{
    protected $guarded = [];
}
