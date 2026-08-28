<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();

        $this->seedImageFiles();

        foreach ($this->products() as $data) {
            $product = Product::create([
                'category_id' => $categories[$data['category']],
                'name' => $data['name'],
                // Set explicitly: DatabaseSeeder mutes model events, so the
                // slug hook on the model cannot be relied on here.
                'slug' => Str::slug($data['name']),
                'status' => $data['status'],
                'featured' => $data['featured'],
                'short_description' => $data['short_description'],
                'full_description' => $data['full_description'],
            ]);

            foreach ($data['variants'] as $index => $variant) {
                $product->variants()->create([...$variant, 'sort_order' => $index]);
            }

            foreach ([
                ProductTechnicalDetail::TYPE_PURITY => $data['purity'],
                ProductTechnicalDetail::TYPE_STORAGE => $data['storage'],
            ] as $type => $rows) {
                foreach ($rows as $index => [$label, $value]) {
                    $product->technicalDetails()->create([
                        'type' => $type,
                        'label' => $label,
                        'value' => $value,
                        'sort_order' => $index,
                    ]);
                }
            }

            foreach ($data['images'] as $index => $path) {
                $product->images()->create(['path' => $path, 'sort_order' => $index]);
            }
        }
    }

    /**
     * @return array<string, int> name => id
     */
    private function seedCategories(): array
    {
        $rows = [
            ['Recovery', 'Peptides supporting tissue repair and post-training recovery.'],
            ['Healing', 'Compounds targeting wound healing and gut lining repair.'],
            ['Growth', 'Growth hormone secretagogues and related compounds.'],
            ['Research', 'Reference compounds held for laboratory use only.'],
            ['Metabolic', 'GLP-1 agonists and metabolic regulation compounds.'],
            ['Cognitive', 'Nootropic peptides studied for memory and focus.'],
            ['Longevity', 'Compounds studied for cellular ageing and senescence.'],
        ];

        $ids = [];

        foreach ($rows as [$name, $description]) {
            $ids[$name] = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
            ])->id;
        }

        return $ids;
    }

    /**
     * Copies the two checked-in placeholders onto the public disk so seeded
     * galleries reference real stored files, exactly like uploaded ones.
     */
    private function seedImageFiles(): void
    {
        foreach (['placeholder.svg', 'placeholder-alt.svg'] as $file) {
            $source = public_path($file);

            if (File::exists($source) && ! Storage::disk('public')->exists("products/{$file}")) {
                Storage::disk('public')->put("products/{$file}", File::get($source));
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'name' => 'BPC-157',
                'category' => 'Healing',
                'status' => 'active',
                'featured' => true,
                'short_description' => 'Body protection compound for tissue repair.',
                'full_description' => 'A synthetic pentadecapeptide derived from a protein found in gastric juice. Studied for its role in tendon, ligament and gut lining repair, and typically reconstituted with bacteriostatic water before use.',
                'images' => ['products/placeholder.svg', 'products/placeholder-alt.svg'],
                'variants' => [
                    ['label' => '5mg vial', 'price' => 45.00, 'stock' => 142, 'is_kit' => false, 'kit_inclusions' => []],
                    ['label' => '10mg vial', 'price' => 79.00, 'stock' => 64, 'is_kit' => false, 'kit_inclusions' => []],
                    ['label' => 'Starter kit', 'price' => 119.00, 'stock' => 12, 'is_kit' => true, 'kit_inclusions' => [
                        '5mg BPC-157 vial',
                        'Bacteriostatic water 30ml',
                        'Insulin syringes ×10',
                        'Alcohol swabs ×20',
                    ]],
                ],
                'purity' => [['HPLC', '99.2%'], ['Mass spec', 'Confirmed']],
                'storage' => [['Temperature', '2-8°C'], ['Light', 'Protect from light'], ['Form', 'Lyophilised']],
            ],
            [
                'name' => 'TB-500',
                'category' => 'Recovery',
                'status' => 'active',
                'featured' => false,
                'short_description' => 'Thymosin beta-4 fragment for recovery.',
                'full_description' => 'A synthetic fragment of the naturally occurring peptide thymosin beta-4, studied for its role in cell migration and recovery following soft tissue injury.',
                'images' => ['products/placeholder.svg'],
                'variants' => [
                    ['label' => '2mg vial', 'price' => 34.00, 'stock' => 24, 'is_kit' => false, 'kit_inclusions' => []],
                    ['label' => '10mg research kit', 'price' => 89.50, 'stock' => 40, 'is_kit' => true, 'kit_inclusions' => [
                        '10mg TB-500 vial',
                        'Bacteriostatic water 30ml',
                        'Reconstitution guide',
                    ]],
                ],
                'purity' => [['HPLC', '98.7%']],
                'storage' => [['Temperature', '2-8°C'], ['After reconstitution', 'Use within 30 days']],
            ],
            [
                // Vial-only: no kit format, and out of stock across the board.
                'name' => 'GHK-Cu',
                'category' => 'Healing',
                'status' => 'draft',
                'featured' => false,
                'short_description' => 'Copper peptide complex for skin and wound repair.',
                'full_description' => 'A naturally occurring copper complex of the tripeptide glycyl-L-histidyl-L-lysine. Widely studied in dermatological research for its role in collagen synthesis.',
                'images' => [],
                'variants' => [
                    ['label' => '50mg vial', 'price' => 62.00, 'stock' => 0, 'is_kit' => false, 'kit_inclusions' => []],
                    ['label' => '100mg vial', 'price' => 110.00, 'stock' => 0, 'is_kit' => false, 'kit_inclusions' => []],
                ],
                'purity' => [['HPLC', '99.6%'], ['Copper content', 'Within spec']],
                'storage' => [['Temperature', '2-8°C'], ['Light', 'Protect from light'], ['Freezing', 'Do not freeze']],
            ],
            [
                // Single format: the expand chevron must stay hidden for this row.
                'name' => 'Ipamorelin',
                'category' => 'Growth',
                'status' => 'archived',
                'featured' => false,
                'short_description' => 'Selective growth hormone secretagogue.',
                'full_description' => 'A pentapeptide that selectively stimulates growth hormone release without materially affecting cortisol or prolactin, which is what distinguishes it from earlier secretagogues.',
                'images' => [],
                'variants' => [
                    ['label' => '2mg vial', 'price' => 38.75, 'stock' => 8, 'is_kit' => false, 'kit_inclusions' => []],
                ],
                'purity' => [['HPLC', '97.9%']],
                'storage' => [['Temperature', '-20°C'], ['Form', 'Lyophilised']],
            ],
            [
                'name' => 'Semaglutide',
                'category' => 'Metabolic',
                'status' => 'active',
                'featured' => true,
                'short_description' => 'GLP-1 receptor agonist for metabolic research.',
                'full_description' => 'A long-acting glucagon-like peptide-1 receptor agonist. Supplied lyophilised; the kit format includes everything needed for reconstitution and measured dosing.',
                'images' => ['products/placeholder-alt.svg'],
                'variants' => [
                    ['label' => '2mg vial', 'price' => 89.00, 'stock' => 12, 'is_kit' => false, 'kit_inclusions' => []],
                    ['label' => '5mg reconstitution kit', 'price' => 175.00, 'stock' => 15, 'is_kit' => true, 'kit_inclusions' => [
                        '5mg Semaglutide vial',
                        'Bacteriostatic water 30ml',
                        'Insulin syringes ×15',
                        'Dosing chart',
                        'Alcohol swabs ×30',
                    ]],
                ],
                'purity' => [['HPLC', '99.8%'], ['Endotoxin', '< 10 EU/mg']],
                'storage' => [['Temperature', '2-8°C'], ['Light', 'Protect from light'], ['Freezing', 'Do not freeze']],
            ],
        ];
    }
}
