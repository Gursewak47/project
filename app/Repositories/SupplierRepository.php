<?php

namespace App\Repositories;

use App\Models\Brandable;
use App\Models\SimpletricsProduct;
use App\Models\SimpletricsProductSupplier;
use App\Models\Supplier;
use App\Models\Supplier\SupplierBankAccount;
use App\Models\Supplier\SupplierEmail;
use App\Models\Supplier\SupplierPhoneNumber;
use App\Models\Supplier\SupplierSocialAccount;
use App\Models\SupplierBrandProduct;
use App\Repositories\Abstraction\ModelRepository;
use App\Repositories\NoteRepository;

class SupplierRepository extends ModelRepository
{
    public function __construct(private NoteRepository $noteRepository, private BrandRepository $brandRepository)
    {
    }
    public function getSupplierById(int $id, array $relations = []): Supplier
    {
        if (empty($relations)) {
            $relations = [
                'supplierPhoneNumbers',
                'supplierSocialAccounts',
                'supplierBankAccounts',
                'supplierEmails',
                'notes',
                'brands',
                'brandables.supplierBrandProducts' => function ($query) {
                    $query->select('supplier_brandable_id', 'simpletrics_products.*');
                },
            ];
        }
        return Supplier::select('*')
            ->with($relations)->findOrFail($id);
    }
    public function createSupplier(array $request): Supplier
    {
        $supplier = Supplier::create($request);
        $this->saveChildRecords($supplier, $request);
        return $supplier;
    }
    public function saveChildRecords(Supplier $supplier, array $request)
    {
        $this->saveEmails(@$request['supplier_emails'], $supplier);
        $this->saveBankAccounts(@$request['supplier_bank_accounts'], $supplier);
        $this->savePhoneNumbers(@$request['supplier_phone_numbers'], $supplier);
        $this->saveSocialAccounts(@$request['supplier_social_accounts'], $supplier);
        $this->saveNotes(@$request['notes'], $supplier);
        $this->linkSimpletricsProducts(@$request['simpletrics_product_ids'], $supplier);
        $this->saveBrands(@$request['brands'], $supplier);
        $this->saveSupplierBrandProducts(@$request['brands'], $supplier);
    }
    private function saveSupplierBrandProducts($brands, $supplier)
    {
        $supplier->load(['brandables', 'brands']);
        if (!is_null($brands)) {
            foreach ($brands as $brand) {
                $supplierBrandable = Brandable::where('brand_id', $brand['id'])
                    ->where('brandable_id', $supplier->id)
                    ->where('brandable_type', Supplier::class)->first();
                $supplierProductObject = [];
                if (@$brand['products']) {
                    foreach ($brand['products'] as $product) {
                        //If product is still linked
                        $productBrandable = Brandable::where('brand_id', $brand['id'])->where('brandable_id', $product['id'])->where('brandable_type', SimpletricsProduct::class)->first();
                        if ($productBrandable) {
                            $supplierProductObject[] = SupplierBrandProduct::updateOrCreate([
                                'supplier_brandable_id'            => $supplierBrandable->id,
                                'simpletrics_product_brandable_id' => $productBrandable->id,
                            ], [
                                'supplier_brandable_id'            => $supplierBrandable->id,
                                'simpletrics_product_brandable_id' => $productBrandable->id,
                            ])->id;
                        }
                    }
                    if (is_null($supplierBrandable->metadata)) {
                        $supplierBrandable->metadata = [];
                    }
                    $supplierBrandable->metadata = array_merge($supplierBrandable->metadata, [
                        'all_products' => false,
                    ]);
                } else {
                    if (is_null($supplierBrandable->metadata)) {
                        $supplierBrandable->metadata = [];
                    }
                    $supplierBrandable->metadata = array_merge($supplierBrandable->metadata, [
                        'all_products' => true,
                    ]);
                }
                $supplierBrandable->update();
                SupplierBrandProduct::where('supplier_brandable_id', $supplierBrandable->id)->whereNotIn('id', $supplierProductObject)->delete();
            }
        }
    }
    private function saveBrands(?array $request, Supplier $supplier)
    {
        $this->brandRepository->linkBrands($supplier, $request);
    }
    private function saveEmails(array|null $request, $supplier)
    {
        $supplierEmails = collect();
        if (!is_null($request)) {
            foreach ($request as $email) {
                $supplierEmail = new SupplierEmail();
                if (@$email['id']) {
                    $supplierEmail = $this->findOrFail($email['id']);
                }
                $supplierEmail->fill($email);
                $supplierEmail->supplier()->associate($supplier);
                $supplierEmail->save();
                $supplierEmails->push($supplierEmail);
            }
        }
        $supplier->supplierEmails()->whereNotIn('id', $supplierEmails->pluck('id')->values()->all())->delete();
    }
    private function saveBankAccounts(array|null $request, $supplier)
    {
        $supplierAccounts = collect();
        if (!is_null($request)) {
            foreach ($request as $account) {
                $supplierAccount = new SupplierBankAccount();
                if (@$account['id']) {
                    $supplierAccount = $this->findOrFail($account['id']);
                }
                $supplierAccount->fill($account);
                $supplierAccount->supplier()->associate($supplier);
                $supplierAccount->save();
                $supplierAccounts->push($supplierAccount);
            }
        }
        $supplier->supplierBankAccounts()->whereNotIn('id', $supplierAccounts->pluck('id')->values()->all())->delete();
    }
    private function savePhoneNumbers(array|null $request, $supplier)
    {
        $supplierPhoneNumbers = collect();
        if (!is_null($request)) {
            foreach ($request as $phoneNumber) {
                $supplierPhoneNumber = new SupplierPhoneNumber();
                if (@$phoneNumber['id']) {
                    $supplierPhoneNumber = $this->findOrFail($phoneNumber['id']);
                }
                $supplierPhoneNumber->fill($phoneNumber);
                $supplierPhoneNumber->supplier()->associate($supplier);
                $supplierPhoneNumber->save();
                $supplierPhoneNumbers->push($supplierPhoneNumber);
            }
        }
        $supplier->supplierPhoneNumbers()->whereNotIn('id', $supplierPhoneNumbers->pluck('id')->values()->all())->delete();
    }
    private function saveSocialAccounts(array|null $request, $supplier)
    {
        $supplierSocialAccounts = collect();
        if (!is_null($request)) {
            foreach ($request as $socialAccount) {
                $supplierSocialAccount = new SupplierSocialAccount();
                if (@$socialAccount['id']) {
                    $supplierSocialAccount = $this->findOrFail($socialAccount['id']);
                }
                $supplierSocialAccount->fill($socialAccount);
                $supplierSocialAccount->supplier()->associate($supplier);
                $supplierSocialAccount->save();
                $supplierSocialAccounts->push($supplierSocialAccount);
            }
        }
        $supplier->supplierSocialAccounts()->whereNotIn('id', $supplierSocialAccounts->pluck('id')->values()->all())->delete();
    }
    public function linkSimpletricsProducts(array|null $request, Supplier $supplier)
    {
        $products = collect();
        if (!is_null(@$request)) {
            foreach ($request as $productId) {
                $products->push(SimpletricsProductSupplier::updateOrCreate([
                    'simpletrics_product_id' => $productId,
                    'supplier_id'            => $supplier->id,
                ], [
                    'simpletrics_product_id' => $productId,
                    'supplier_id'            => $supplier->id,
                ]));
            }
        }
        SimpletricsProductSupplier::where('supplier_id', $supplier->id)->whereNotIn('id', $products->pluck('id')->values()->all())->delete();
    }
    private function saveNotes(array|null $request, $supplier)
    {
        if (!is_null(@$request)) {
            $this->noteRepository->linkNotes($supplier, $request);
        }
    }
    public function updateSupplier(Supplier|int $supplier, array $request): Supplier
    {
        if (is_int($supplier)) {
            $supplier = $this->getSupplierById($supplier);
        }
        $supplier->fill($request);
        $supplier->update();
        $this->saveChildRecords($supplier, $request);
        return $supplier;
    }
    public function deleteSupplier(Supplier $supplier): bool
    {
        return $supplier->delete();
    }
}
