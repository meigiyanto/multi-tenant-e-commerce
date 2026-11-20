<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FashionProductSeeder extends Seeder
{
    /**
     * Create a repeatable fashion catalog for every existing Store.
     */
    public function run(): void
    {
        $stores = Store::query()->orderBy('id')->get();

        if ($stores->isEmpty()) {
            $this->command?->warn('Fashion products were not seeded: no Store exists yet.');

            return;
        }

        foreach ($stores as $store) {
            DB::transaction(function () use ($store): void {
                foreach ($this->catalog() as $categoryData) {
                    $category = Category::query()->updateOrCreate(
                        ['store_id' => $store->id, 'slug' => $categoryData['slug']],
                        [
                            'name' => $categoryData['name'],
                            'description' => $categoryData['description'],
                            'is_active' => true,
                            'sort_order' => $categoryData['sort_order'],
                        ],
                    );

                    foreach ($categoryData['products'] as $productData) {
                        $this->seedProduct($store, $category, $productData, $categoryData['sku_prefix']);
                    }
                }
            });

            $this->command?->info("Fashion catalog seeded for Store #{$store->id}: {$store->name}");
        }
    }

    private function seedProduct(Store $store, Category $category, array $data, string $prefix): void
    {
        $description = implode("\n", [
            $data['summary'],
            '',
            'Material: '.$data['material'],
            'Fit: '.$data['fit'],
            'Care: '.$data['care'],
            'Product details: '.$data['details'],
        ]);

        $product = Product::withTrashed()->firstOrNew([
            'store_id' => $store->id,
            'slug' => $data['slug'],
        ]);

        if ($product->exists && $product->trashed()) {
            $product->restore();
        }

        $product->fill([
            'category_id' => $category->id,
            'name' => $data['name'],
            'status' => 'active',
            'description' => $description,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'],
            'track_inventory' => true,
        ]);
        $product->save();

        foreach ($data['colors'] as $color) {
            foreach ($data['sizes'] as $size) {
                $sku = implode('-', [
                    $prefix,
                    Str::upper(Str::slug($data['slug'])),
                    Str::upper(Str::slug($color)),
                    Str::upper(Str::slug($size)),
                ]);

                $variant = ProductVariant::query()->firstOrNew([
                    'store_id' => $store->id,
                    'sku' => $sku,
                ]);
                $variant->fill([
                    'product_id' => $product->id,
                    'name' => "{$color} / {$size}",
                    'options' => ['Color' => $color, 'Size' => $size],
                    'is_active' => true,
                ]);
                $variant->save();

                $initialStock = 12 + (($product->id + strlen($color) + strlen($size)) % 9);
                $inventory = Inventory::query()->firstOrCreate(
                    ['store_id' => $store->id, 'product_variant_id' => $variant->id],
                    ['quantity' => $initialStock, 'reserved_quantity' => 0, 'low_stock_threshold' => 5],
                );

                if ($inventory->wasRecentlyCreated && $inventory->quantity > 0) {
                    InventoryMovement::query()->create([
                        'store_id' => $store->id,
                        'inventory_id' => $inventory->id,
                        'user_id' => null,
                        'type' => 'initial',
                        'quantity' => $inventory->quantity,
                        'balance_after' => $inventory->quantity,
                        'reference' => 'fashion-product-seeder',
                        'notes' => 'Initial stock from FashionProductSeeder.',
                    ]);
                }
            }
        }
    }

    /**
     * Product descriptions carry the available product metadata; variant
     * attributes are stored in product_variants.options as structured JSON.
     */
    private function catalog(): array
    {
        return [
            [
                'name' => "Women's Fashion",
                'slug' => 'womens-fashion',
                'description' => 'Women’s apparel, footwear, and accessories.',
                'sku_prefix' => 'WF',
                'sort_order' => 10,
                'products' => [
                    [
                        'name' => 'Linen Relaxed Blouse', 'slug' => 'linen-relaxed-blouse',
                        'summary' => 'A breathable everyday blouse with a clean, relaxed silhouette.',
                        'material' => '55% linen, 45% cotton', 'fit' => 'Relaxed fit',
                        'care' => 'Cold gentle wash; line dry; warm iron.',
                        'details' => 'Button-front closure, long sleeves, curved hem, and lightweight woven texture.',
                        'price' => 329000, 'compare_at_price' => 389000,
                        'colors' => ['Ivory', 'Sage'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Floral Midi Dress', 'slug' => 'floral-midi-dress',
                        'summary' => 'A versatile floral midi dress for brunch, holidays, and casual occasions.',
                        'material' => '100% rayon', 'fit' => 'Regular fit with a defined waist',
                        'care' => 'Hand wash separately; do not bleach.',
                        'details' => 'Soft drape, short sleeves, waist tie, and concealed back zipper.',
                        'price' => 459000, 'compare_at_price' => 529000,
                        'colors' => ['Navy Floral', 'Rose Floral'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'High-Waist Straight Trousers', 'slug' => 'high-waist-straight-trousers',
                        'summary' => 'Tailored straight-leg trousers designed for workdays and smart casual looks.',
                        'material' => 'Polyester-rayon blend with elastane', 'fit' => 'High-rise, straight leg',
                        'care' => 'Machine wash cold; hang to dry.',
                        'details' => 'Front pleats, belt loops, side pockets, and hook-and-bar fastening.',
                        'price' => 399000, 'compare_at_price' => 469000,
                        'colors' => ['Black', 'Stone'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'A-Line Denim Skirt', 'slug' => 'a-line-denim-skirt',
                        'summary' => 'A timeless denim skirt with an easy A-line shape.',
                        'material' => '98% cotton, 2% elastane denim', 'fit' => 'Regular A-line fit',
                        'care' => 'Wash inside out with similar colors.',
                        'details' => 'Classic five-pocket styling, metal button, and knee-length cut.',
                        'price' => 349000, 'compare_at_price' => 419000,
                        'colors' => ['Washed Blue', 'Dark Indigo'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Single-Breasted Tailored Blazer', 'slug' => 'single-breasted-tailored-blazer',
                        'summary' => 'A polished layering piece for office outfits and elevated everyday styling.',
                        'material' => 'Polyester-viscose blend', 'fit' => 'Structured regular fit',
                        'care' => 'Professional dry clean recommended.',
                        'details' => 'Notched lapel, two-button front, flap pockets, and full lining.',
                        'price' => 699000, 'compare_at_price' => 799000,
                        'colors' => ['Black', 'Camel'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Soft Knit Cardigan', 'slug' => 'soft-knit-cardigan',
                        'summary' => 'A lightweight cardigan made for comfortable layering throughout the day.',
                        'material' => 'Cotton-acrylic knit', 'fit' => 'Easy relaxed fit',
                        'care' => 'Hand wash cold; dry flat.',
                        'details' => 'Ribbed cuffs, button closure, and two front patch pockets.',
                        'price' => 379000, 'compare_at_price' => 439000,
                        'colors' => ['Oatmeal', 'Dusty Pink'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Slim Straight Jeans', 'slug' => 'slim-straight-jeans',
                        'summary' => 'Everyday jeans with a flattering high rise and versatile straight leg.',
                        'material' => '99% cotton, 1% elastane denim', 'fit' => 'High-rise slim straight',
                        'care' => 'Wash inside out; avoid tumble drying.',
                        'details' => 'Five-pocket design, zip fly, and subtle contrast stitching.',
                        'price' => 429000, 'compare_at_price' => 499000,
                        'colors' => ['Mid Blue', 'Black'], 'sizes' => ['26', '27', '28', '29', '30'],
                    ],
                    [
                        'name' => 'Cotton Everyday Tunic', 'slug' => 'cotton-everyday-tunic',
                        'summary' => 'A comfortable cotton tunic with a modest, easy-to-style shape.',
                        'material' => '100% cotton poplin', 'fit' => 'Relaxed longline fit',
                        'care' => 'Machine wash cold; medium iron.',
                        'details' => 'Mandarin collar, half-button placket, side slits, and three-quarter sleeves.',
                        'price' => 299000, 'compare_at_price' => 359000,
                        'colors' => ['White', 'Terracotta'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Minimal Court Sneakers', 'slug' => 'minimal-court-sneakers',
                        'summary' => 'Clean low-top sneakers that pair easily with casual and smart-casual outfits.',
                        'material' => 'Synthetic leather upper, rubber outsole', 'fit' => 'Regular; choose your usual size',
                        'care' => 'Wipe clean with a soft damp cloth.',
                        'details' => 'Padded collar, lace-up fastening, and cushioned footbed.',
                        'price' => 549000, 'compare_at_price' => 629000,
                        'colors' => ['White', 'Cream'], 'sizes' => ['37', '38', '39', '40', '41'],
                    ],
                    [
                        'name' => 'Structured Everyday Tote', 'slug' => 'structured-everyday-tote',
                        'summary' => 'A spacious tote for daily essentials, commuting, and weekend plans.',
                        'material' => 'Textured vegan leather with polyester lining', 'fit' => 'One size',
                        'care' => 'Wipe with a dry or slightly damp cloth.',
                        'details' => 'Zip-top closure, inner zip pocket, two slip pockets, and reinforced handles.',
                        'price' => 489000, 'compare_at_price' => 569000,
                        'colors' => ['Tan', 'Black'], 'sizes' => ['One Size'],
                    ],
                ],
            ],
            [
                'name' => "Men's Fashion",
                'slug' => 'mens-fashion',
                'description' => 'Men’s apparel, footwear, and accessories.',
                'sku_prefix' => 'MF',
                'sort_order' => 20,
                'products' => [
                    [
                        'name' => 'Oxford Button-Down Shirt', 'slug' => 'oxford-button-down-shirt',
                        'summary' => 'A versatile Oxford shirt suitable for office days and weekend outfits.',
                        'material' => '100% cotton Oxford', 'fit' => 'Regular fit',
                        'care' => 'Machine wash cold; warm iron.',
                        'details' => 'Button-down collar, chest pocket, adjustable cuffs, and curved hem.',
                        'price' => 359000, 'compare_at_price' => 419000,
                        'colors' => ['White', 'Light Blue'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Essential Pique Polo Shirt', 'slug' => 'essential-pique-polo-shirt',
                        'summary' => 'A breathable polo shirt for smart-casual everyday wear.',
                        'material' => '100% cotton pique', 'fit' => 'Classic fit',
                        'care' => 'Machine wash cold; reshape while damp.',
                        'details' => 'Two-button placket, ribbed collar, and side vents.',
                        'price' => 259000, 'compare_at_price' => 309000,
                        'colors' => ['Navy', 'Charcoal'], 'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                    ],
                    [
                        'name' => 'Tapered Chino Pants', 'slug' => 'tapered-chino-pants',
                        'summary' => 'Comfortable chinos with a neat tapered profile for daily rotation.',
                        'material' => 'Cotton twill with stretch', 'fit' => 'Mid-rise tapered fit',
                        'care' => 'Machine wash cold with similar colors.',
                        'details' => 'Button closure, belt loops, front slant pockets, and rear welt pockets.',
                        'price' => 399000, 'compare_at_price' => 469000,
                        'colors' => ['Khaki', 'Navy'], 'sizes' => ['30', '32', '34', '36', '38'],
                    ],
                    [
                        'name' => 'Classic Straight Denim Jeans', 'slug' => 'classic-straight-denim-jeans',
                        'summary' => 'A dependable pair of straight-leg jeans with a timeless wash.',
                        'material' => '100% cotton denim', 'fit' => 'Regular straight fit',
                        'care' => 'Wash inside out; line dry.',
                        'details' => 'Five-pocket construction, zip fly, and durable contrast stitching.',
                        'price' => 449000, 'compare_at_price' => 519000,
                        'colors' => ['Indigo', 'Washed Black'], 'sizes' => ['30', '32', '34', '36', '38'],
                    ],
                    [
                        'name' => 'Unstructured Casual Blazer', 'slug' => 'unstructured-casual-blazer',
                        'summary' => 'A lightweight blazer that adds a refined finish without feeling formal.',
                        'material' => 'Linen-cotton blend', 'fit' => 'Modern regular fit',
                        'care' => 'Dry clean recommended.',
                        'details' => 'Soft shoulder construction, notch lapel, two-button closure, and patch pockets.',
                        'price' => 749000, 'compare_at_price' => 849000,
                        'colors' => ['Navy', 'Sand'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Fleece Pullover Hoodie', 'slug' => 'fleece-pullover-hoodie',
                        'summary' => 'A cozy pullover hoodie for relaxed days and cooler evenings.',
                        'material' => 'Cotton-polyester fleece', 'fit' => 'Relaxed fit',
                        'care' => 'Machine wash cold; wash inside out.',
                        'details' => 'Adjustable drawstring hood, kangaroo pocket, and ribbed cuffs and hem.',
                        'price' => 429000, 'compare_at_price' => 499000,
                        'colors' => ['Heather Grey', 'Black'], 'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                    ],
                    [
                        'name' => 'Lightweight Harrington Jacket', 'slug' => 'lightweight-harrington-jacket',
                        'summary' => 'A versatile zip-front jacket for transitional weather and layering.',
                        'material' => 'Water-resistant polyester shell with cotton lining', 'fit' => 'Regular fit',
                        'care' => 'Gentle machine wash; close zipper before washing.',
                        'details' => 'Stand collar, full zip, two side pockets, and elasticated hem.',
                        'price' => 629000, 'compare_at_price' => 729000,
                        'colors' => ['Olive', 'Midnight'], 'sizes' => ['S', 'M', 'L', 'XL'],
                    ],
                    [
                        'name' => 'Everyday Cotton Shorts', 'slug' => 'everyday-cotton-shorts',
                        'summary' => 'Easy cotton shorts designed for warm-weather comfort.',
                        'material' => '100% cotton twill', 'fit' => 'Regular above-knee fit',
                        'care' => 'Machine wash cold; do not bleach.',
                        'details' => 'Elasticated back waistband, button closure, and four practical pockets.',
                        'price' => 279000, 'compare_at_price' => 329000,
                        'colors' => ['Stone', 'Olive'], 'sizes' => ['30', '32', '34', '36', '38'],
                    ],
                    [
                        'name' => 'Retro Low-Top Sneakers', 'slug' => 'retro-low-top-sneakers',
                        'summary' => 'Low-top sneakers with a simple retro profile for everyday use.',
                        'material' => 'Synthetic leather and suede-look panels, rubber outsole',
                        'fit' => 'Regular; choose your usual size',
                        'care' => 'Brush gently and wipe clean; air dry.',
                        'details' => 'Lace-up fastening, padded tongue, and cushioned insole.',
                        'price' => 579000, 'compare_at_price' => 669000,
                        'colors' => ['White/Navy', 'White/Grey'], 'sizes' => ['40', '41', '42', '43', '44'],
                    ],
                    [
                        'name' => 'Genuine Leather Belt', 'slug' => 'genuine-leather-belt',
                        'summary' => 'A classic leather belt that works with both denim and tailored trousers.',
                        'material' => 'Genuine leather strap with brushed metal buckle',
                        'fit' => 'Choose one size above your trouser waist',
                        'care' => 'Keep dry; condition leather occasionally.',
                        'details' => 'Adjustable pin buckle, stitched edges, and 35 mm strap width.',
                        'price' => 299000, 'compare_at_price' => 349000,
                        'colors' => ['Black', 'Brown'], 'sizes' => ['90', '95', '100', '105', '110'],
                    ],
                ],
            ],
        ];
    }
}
