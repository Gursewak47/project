<?php

namespace App\Http\Requests\Supplier;

use App\Models\Brand;
use App\Models\SimpletricsProduct;
use App\Models\Supplier;
use App\Models\Supplier\SupplierBrand;
use App\Models\Supplier\SupplierPhoneNumber;
use App\Models\Supplier\SupplierSocialAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'company_name' => [
                'nullable',
            ],
            'contract_person_name' => [
                'required',
            ],
            'paypal_email' => [
                'nullable',
                'email',
            ],
            'street_address' => [
                'nullable',
            ],
            'city' => [
                'nullable',
            ],
            'brands.*.id' => [
                'nullable',
                'exists:' . (new Brand())->getTable() . ',id',
            ],
            'brands.*.products.*.id' => [
                'nullable',
                'exists:' . (new SimpletricsProduct())->getTable() . ',id',
            ],
        ];
        $supplierEmail = appendKeyForArray(
            [
                'email' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ],
            'supplier_emails.*.'
        );
        $supplierBankAccount = appendKeyForArray([
            'account_name' => [
                'required',
                'string',
                'max:255',
            ],
            'swift_code' => [
                'required',
                'max:255',
            ],
            'account_number' => [
                'required',
                'numeric',
            ],
        ], 'supplier_bank_accounts.*.');
        $supplierPhoneNumber = appendKeyForArray([
            'phone_number' => [
                'required',
                'min:4',
                'numeric',
            ],
            'type' => [
                'required',
                'in:' . implode(',', SupplierPhoneNumber::TYPES_LABELS),
            ],
        ], 'supplier_phone_numbers.*.');
        $supplierNote = appendKeyForArray([
            'note' => [
                'required',
                'string',
                'max:255',
            ],
        ], 'notes.*.');
        $supplierBrand = appendKeyForArray([
            'brand_ids' => [
                'required',
                'array',
            ],
            'all_asins_for_brand_ids' => [
                'required',
                'array',
            ],
        ], 'supplier_brands.*.');
        $supplierBrandAsin = appendKeyForArray(
            [
                'asin' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'product_ids' => [
                    'required',
                    'array',
                ],
                'brand_id' => [
                    'required',
                    'exists:' . (new Brand())->getTable() . 'id',
                ],
            ],
            'supplier_brand_asins.*.'
        );
        $supplierSocialAccount = appendKeyForArray([
            'account' => [
                'required',
                'max:255',
            ],
            'type' => [
                'required',
                'in:' . implode(',', SupplierSocialAccount::TYPES_LABELS),
            ],
        ], 'supplier_social_accounts.*.');
        return collect($rules)->merge(collect($supplierEmail))->merge(collect($supplierBankAccount))->merge(collect($supplierPhoneNumber))->merge(collect($supplierNote))->merge(collect($supplierBrand))->merge(collect($supplierBrandAsin))->merge(collect($supplierSocialAccount))->toArray();
    }
}
