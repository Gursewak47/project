<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Resources\Supplier\SupplierResource;
use App\Models\Product;
use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use App\Traits\PrimeVue\DataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class SupplierController extends Controller
{
    use DataTable;
    public function __construct(private SupplierRepository $supplierRepository)
    {
    }
    // public function getQueryResourceClass()
    // {
    //     return SupplierResource::class;
    // }
    public function getDatatableConfigKey()
    {
        return 'datatable.supplier';
    }
    public function getModel()
    {
        return Supplier::class;
    }
    public function getIndexQuery()
    {
        return Supplier::with([
            'supplierSocialAccounts',
            'supplierPhoneNumbers',
            'supplierEmails',
            'notes',
        ]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Inertia::render('Supplier/Index', [
            'datatableConfig' => $this->getDatatableConfig(),
            'datatable'       => Inertia::lazy(function () {
                return $this->datatable();
            }),
        ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return Inertia::render('Supplier/Create');
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSupplierRequest $request)
    {
        $this->supplierRepository->createSupplier($request->validated());
        return Redirect::route('suppliers.index');
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($supplierId)
    {
        return Inertia::render('Supplier/Create', [
            'supplier' => new SupplierResource($this->supplierRepository->getSupplierById($supplierId, [
                'supplierPhoneNumbers',
                'supplierSocialAccounts',
                'supplierBankAccounts',
                'supplierEmails',
                'notes',
                'brands',
                'brandables.supplierBrandProducts' => function ($query) {
                    $query->select('supplier_brandable_id', 'simpletrics_products.*');
                },
            ])->setAppends([
                'products',
            ])),
        ]);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(StoreSupplierRequest $request, $supplierId)
    {
        $this->supplierRepository->updateSupplier($supplierId, $request->validated());
        return Redirect::route('suppliers.index');
    }
    public function search(Request $request)
    {
        return Supplier::filter($request->filters, [
            'table_name' => (new Supplier())->getTable(),
        ])->limit($request->rows)->orderBy('id', 'asc')->get();
    }
    public function searchSupplierByAsin(Request $request)
    {
        $product = Product::where('asin', $request->asin)->first();
        return Supplier::select('suppliers.*')->joinRelationShip('productSuppliers.product')->where('product_suppliers.product_id', $product->id)->filter($request->filters, [
            'table_name' => (new Supplier())->getTable(),
        ])->limit($request->rows)->orderBy('id', 'asc')->get();
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->supplierRepository->deleteSupplier($this->supplierRepository->getSupplierById($id));
        return Redirect::route('suppliers.index');
    }
}
