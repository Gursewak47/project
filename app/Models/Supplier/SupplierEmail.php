<?php

namespace App\Models\Supplier;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierEmail extends Model
{
    use HasFactory;
    protected $fillable = [
        'email',
    ];
    protected $casts = [
        'email' => 'string',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
