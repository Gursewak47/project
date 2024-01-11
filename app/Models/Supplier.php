<?php

namespace App\Models;

use App\Models\Supplier\SupplierBankAccount;
use App\Models\Supplier\SupplierBrand;
use App\Models\Supplier\SupplierEmail;
use App\Models\Supplier\SupplierPhoneNumber;
use App\Models\Supplier\SupplierSocialAccount;
use App\Traits\FilterTrait\FilterTrait;
use App\Traits\SortTrait\SortTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kirschbaum\PowerJoins\PowerJoins;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;
    use FilterTrait;
    use SortTrait;
    use PowerJoins;
    protected $fillable = [
        'company_name',
        'contract_person_name',
        'paypal_email',
        'street_address',
        'city',
    ];
    protected $casts = [
        'company_name'         => 'string',
        'contract_person_name' => 'string',
        'paypal_email'         => 'string',
        'street_address'       => 'string',
        'city'                 => 'string',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $appends = [
        'social_accounts',
        'contact_numbers',
        'emails',
    ];
    public function getSocialAccountsAttribute()
    {
        if ($this->relationLoaded('supplierSocialAccounts')) {
            return $this->supplierSocialAccounts->map(function ($socialAccount) {
                return $socialAccount->type.':'.$socialAccount->account;
            })
                ->implode(',');
        }
        return null;
    }
    public function getContactNumbersAttribute()
    {
        if ($this->relationLoaded('supplierPhoneNumbers')) {
            return $this->supplierPhoneNumbers->map(function ($phoneNumber) {
                return $phoneNumber->type.':'.$phoneNumber->phone_number;
            })
                ->implode(',');
        }
        return null;
    }
    public function getEmailsAttribute()
    {
        if ($this->relationLoaded('supplierEmails')) {
            return $this->supplierEmails->map(function ($email) {
                return $email->email;
            })->implode(',');
        }
        return null;
    }
    public function brandables()
    {
        return $this->hasMany(Brandable::class, 'brandable_id')->where('brandable_type', self::class);
    }
    public function brands()
    {
        return $this->morphToMany(Brand::class, 'brandable');
    }
    public function productSuppliers()
    {
        return $this->hasMany(ProductSupplier::class);
    }
    public function supplierPhoneNumbers()
    {
        return $this->hasMany(SupplierPhoneNumber::class);
    }
    public function supplierEmails()
    {
        return $this->hasMany(SupplierEmail::class);
    }
    public function supplierSocialAccounts()
    {
        return $this->hasMany(SupplierSocialAccount::class);
    }
    public function supplierBankAccounts()
    {
        return $this->hasMany(SupplierBankAccount::class);
    }
    public function supplierBrands()
    {
        return $this->hasMany(SupplierBrand::class);
    }
    public function notes()
    {
        return $this->morphMany(SimpletricsNote::class, 'noteable');
    }
    public function getProductsAttribute()
    {
        return SupplierBrandProduct::selectRaw('suppliers.id as suppliers_id')
                ->selectRaw('simpletrics_products.id as simpletrics_product_id')
                ->selectRaw('simpletrics_products_brandables.*')
                ->selectRaw("(simpletrics_products.id || ': ' || simpletrics_products.name) as label")
                ->leftJoin('brandables as supplier_brandables', function ($join) {
                    $join->on('supplier_brand_products.supplier_brandable_id', 'supplier_brandables.id')
                        ->where('supplier_brandables.brandable_type', self::class);
                })
                ->leftJoin('brandables as simpletrics_products_brandables', function ($join) {
                    $join->on('supplier_brand_products.simpletrics_product_brandable_id', 'simpletrics_products_brandables.id')
                        ->where('simpletrics_products_brandables.brandable_type', SimpletricsProduct::class);
                })
                ->leftJoin('suppliers', function ($join) {
                    $join->on('supplier_brandables.brandable_id', 'suppliers.id');
                })
                ->leftJoin('simpletrics_products', function ($join) {
                    $join->on('simpletrics_products_brandables.brandable_id', 'simpletrics_products.id');
                })
                ->get();
    }
}
