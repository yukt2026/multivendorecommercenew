<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\ProductType;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user || !$user->seller) {
            return false;
        }

        $product = $this->route('product');
        if ($product instanceof \App\Models\Product) {
            return $user->can('update', $product);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_returnable' => filter_var($this->input('is_returnable', false), FILTER_VALIDATE_BOOLEAN),
            'is_cod_allowed' => filter_var($this->input('is_cod_allowed', false), FILTER_VALIDATE_BOOLEAN),
            'is_quick_capable' => filter_var($this->input('is_quick_capable', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isSimple = $this->input('product_type') === ProductType::SIMPLE->value;

        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'product_type' => ['required', 'in:simple,variable'],
            'short_desc' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:40'],
            'return_window_days' => ['required', 'integer', 'min:0', 'max:90'],
            'is_returnable' => ['nullable', 'boolean'],
            'is_cod_allowed' => ['nullable', 'boolean'],
            'is_quick_capable' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'submit_action' => ['required', 'in:save_draft,submit_review,save_changes'],

            // Simple product variant rules
            'simple_sku' => [$isSimple ? 'required' : 'nullable', 'string', 'max:100'],
            'simple_barcode' => ['nullable', 'string', 'max:50'],
            'simple_mrp' => [$isSimple ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'simple_selling_price' => [$isSimple ? 'required' : 'nullable', 'numeric', 'min:0.01', 'lte:simple_mrp'],
            'simple_cost_price' => ['nullable', 'numeric', 'min:0'],
            'simple_stock' => ['nullable', 'integer', 'min:0'],
            'simple_low_stock_threshold' => ['nullable', 'integer', 'min:1'],
            'simple_weight_grams' => ['nullable', 'integer', 'min:0'],
            'simple_length' => ['nullable', 'numeric', 'min:0'],
            'simple_width' => ['nullable', 'numeric', 'min:0'],
            'simple_height' => ['nullable', 'numeric', 'min:0'],

            // Variable product variants rules
            'variants' => [!$isSimple ? 'required' : 'nullable', 'array', 'min:1'],
            'variants.*.sku' => [!$isSimple ? 'required' : 'nullable', 'string', 'max:100'],
            'variants.*.mrp' => [!$isSimple ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'variants.*.selling_price' => [!$isSimple ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.length' => ['nullable', 'numeric', 'min:0'],
            'variants.*.width' => ['nullable', 'numeric', 'min:0'],
            'variants.*.height' => ['nullable', 'numeric', 'min:0'],

            // Specifications
            'specifications' => ['nullable', 'array'],
            'specifications.*.group' => ['nullable', 'string', 'max:100'],
            'specifications.*.key' => ['nullable', 'string', 'max:100'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],

            // Media
            'media' => ['nullable', 'array'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:10240'],
            'deleted_media_ids' => ['nullable', 'array'],
            'deleted_media_ids.*' => ['integer'],
            'media_sort' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isSimple = $this->input('product_type') === ProductType::SIMPLE->value;
            $productId = $this->route('product')?->id;

            if ($isSimple) {
                // Ensure simple SKU is unique
                $sku = strtoupper(trim((string) $this->input('simple_sku', '')));
                if (!empty($sku)) {
                    $skuExists = ProductVariant::where('sku', $sku)
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', '!=', $productId);
                        })
                        ->exists();

                    if ($skuExists) {
                        $validator->errors()->add('simple_sku', "SKU '{$sku}' is already taken by another product variant.");
                    }
                }
            } else {
                $variants = $this->input('variants', []);
                $skus = [];

                foreach ($variants as $idx => $v) {
                    $mrp = (float) ($v['mrp'] ?? 0);
                    $price = (float) ($v['selling_price'] ?? 0);

                    if ($price > $mrp) {
                        $validator->errors()->add("variants.{$idx}.selling_price", "Variant selling price (₹{$price}) cannot exceed MRP (₹{$mrp}).");
                    }

                    $vSku = strtoupper(trim((string) ($v['sku'] ?? '')));
                    if (!empty($vSku)) {
                        if (in_array($vSku, $skus, true)) {
                            $validator->errors()->add("variants.{$idx}.sku", "Duplicate SKU '{$vSku}' in your variant list.");
                        }
                        $skus[] = $vSku;

                        $variantId = !empty($v['id']) ? (int) $v['id'] : null;
                        $existsDb = ProductVariant::where('sku', $vSku)
                            ->when($variantId, fn ($q) => $q->where('id', '!=', $variantId))
                            ->exists();

                        if ($existsDb) {
                            $validator->errors()->add("variants.{$idx}.sku", "SKU '{$vSku}' already exists in the system.");
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Product title is required.',
            'category_id.required' => 'Please select a catalog category.',
            'category_id.exists' => 'The selected category does not exist.',
            'simple_selling_price.lte' => 'Selling price cannot exceed the Maximum Retail Price (MRP).',
            'tax_percent.required' => 'GST / Tax rate is required.',
            'return_window_days.required' => 'Return window days is required.',
        ];
    }
}
