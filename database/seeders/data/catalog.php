<?php

/*
|--------------------------------------------------------------------------
| Starter catalogue — 6 collections, 18 subcategories, 54 perfumes.
|--------------------------------------------------------------------------
| Original product names and copy. Prices are in INR (MRP / selling price).
| `code` builds SKUs (SJ-<code>-001…). Images are left empty: the storefront
| renders bottle artwork from `family` until an admin uploads a photo.
|
| Product tuple keys:
|   name, family, gender, type (concentration), ml, mrp, price, stock,
|   top / heart / base (notes), short, and optional flags: featured, bestseller.
*/

return [
    [
        'name' => 'Men',
        'slug' => 'men',
        'description' => 'Bold woods, cool aquatics, spice and smoky oud — long-lasting perfumes made for him.',
        'children' => [
            [
                'name' => 'Woody', 'slug' => 'men-woody', 'code' => 'MWD',
                'description' => 'Teak, cedar and vetiver — polished, grounded and quietly confident.',
                'products' => [
                    ['name' => 'Teakwood Noir', 'family' => 'woody', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1499, 'price' => 899, 'stock' => 120,
                        'top' => ['Bergamot', 'Black Pepper'], 'heart' => ['Teakwood', 'Cardamom'], 'base' => ['Vetiver', 'Amber'],
                        'short' => 'A dark, polished woody scent for long evenings.', 'featured' => true, 'bestseller' => true],
                    ['name' => 'Cedar Ember', 'family' => 'woody', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1299, 'price' => 799, 'stock' => 85,
                        'top' => ['Grapefruit', 'Pink Pepper'], 'heart' => ['Cedarwood', 'Clary Sage'], 'base' => ['Tonka Bean', 'Smoked Woods'],
                        'short' => 'Warm cedar with a glowing, smoky edge.'],
                    ['name' => 'Vetiver Monsoon', 'family' => 'woody', 'gender' => 'men', 'type' => 'EDP', 'ml' => 50, 'mrp' => 999, 'price' => 649, 'stock' => 6,
                        'top' => ['Lemon', 'Ginger'], 'heart' => ['Vetiver', 'Wet Earth'], 'base' => ['Patchouli', 'Musk'],
                        'short' => 'Rain-soaked earth and green vetiver roots.'],
                ],
            ],
            [
                'name' => 'Fresh & Aquatic', 'slug' => 'men-fresh-aquatic', 'code' => 'MFA',
                'description' => 'Sea breeze, citrus and mint — clean energy for hot Indian days.',
                'products' => [
                    ['name' => 'Konkan Tide', 'family' => 'aquatic', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1399, 'price' => 849, 'stock' => 140,
                        'top' => ['Sea Salt', 'Mandarin'], 'heart' => ['Marine Accord', 'Lavender'], 'base' => ['Driftwood', 'White Musk'],
                        'short' => 'A breezy coastal fresh for everyday wear.', 'bestseller' => true],
                    ['name' => 'Glacier Mint', 'family' => 'fresh', 'gender' => 'men', 'type' => 'EDT', 'ml' => 100, 'mrp' => 1199, 'price' => 699, 'stock' => 95,
                        'top' => ['Peppermint', 'Lime'], 'heart' => ['Geranium', 'Violet Leaf'], 'base' => ['Ambroxan', 'Cedar'],
                        'short' => 'Icy mint and citrus — instant summer relief.'],
                    ['name' => 'Neel Aqua', 'family' => 'aquatic', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1299, 'price' => 799, 'stock' => 0,
                        'top' => ['Bergamot', 'Calone'], 'heart' => ['Rosemary', 'Sage'], 'base' => ['Oakmoss', 'Musk'],
                        'short' => 'A clean blue fragrance for office and gym.'],
                ],
            ],
            [
                'name' => 'Spicy & Leather', 'slug' => 'men-spicy-leather', 'code' => 'MSL',
                'description' => 'Saffron, pepper and supple leather — statement scents with presence.',
                'products' => [
                    ['name' => 'Saffron Leather', 'family' => 'leather', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1799, 'price' => 1099, 'stock' => 60,
                        'top' => ['Saffron', 'Cardamom'], 'heart' => ['Leather', 'Jasmine'], 'base' => ['Oud', 'Amber'],
                        'short' => 'Kesar-laced leather with a regal finish.', 'featured' => true],
                    ['name' => 'Black Pepper Smoke', 'family' => 'spicy', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1399, 'price' => 899, 'stock' => 70,
                        'top' => ['Black Pepper', 'Elemi'], 'heart' => ['Incense', 'Nutmeg'], 'base' => ['Guaiac Wood', 'Leather'],
                        'short' => 'Crackling pepper over soft incense smoke.'],
                    ['name' => 'Cardamom Code', 'family' => 'spicy', 'gender' => 'men', 'type' => 'EDP', 'ml' => 50, 'mrp' => 899, 'price' => 599, 'stock' => 110,
                        'top' => ['Cardamom', 'Bergamot'], 'heart' => ['Cinnamon', 'Lavender'], 'base' => ['Tonka Bean', 'Sandalwood'],
                        'short' => 'Green cardamom, lavender and creamy sandalwood.'],
                ],
            ],
            [
                'name' => 'Oud', 'slug' => 'men-oud', 'code' => 'MOD',
                'description' => 'Rich agarwood blends with rose, saffron and amber.',
                'products' => [
                    ['name' => 'Royal Oud Durbar', 'family' => 'oud', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 2499, 'price' => 1599, 'stock' => 40,
                        'top' => ['Saffron', 'Rose'], 'heart' => ['Oud', 'Patchouli'], 'base' => ['Amber', 'Leather'],
                        'short' => 'A majestic oud for weddings and big nights.', 'featured' => true, 'bestseller' => true],
                    ['name' => 'Midnight Agarwood', 'family' => 'oud', 'gender' => 'men', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1999, 'price' => 1299, 'stock' => 8,
                        'top' => ['Plum', 'Pink Pepper'], 'heart' => ['Agarwood', 'Labdanum'], 'base' => ['Benzoin', 'Vanilla'],
                        'short' => 'Dark plum and agarwood with a sweet resin trail.'],
                    ['name' => 'Oud Kesar Intense', 'family' => 'oud', 'gender' => 'men', 'type' => 'EDP', 'ml' => 50, 'mrp' => 1499, 'price' => 999, 'stock' => 55,
                        'top' => ['Saffron', 'Nutmeg'], 'heart' => ['Oud', 'Rose'], 'base' => ['Sandalwood', 'Musk'],
                        'short' => 'Saffron and oud, concentrated for all-day wear.'],
                ],
            ],
        ],
    ],

    [
        'name' => 'Women',
        'slug' => 'women',
        'description' => 'Indian florals, juicy fruits, soft musks and cosy gourmands — perfumes made for her.',
        'children' => [
            [
                'name' => 'Floral', 'slug' => 'women-floral', 'code' => 'WFL',
                'description' => 'Mogra, rose, tuberose and champa — garden-fresh and feminine.',
                'products' => [
                    ['name' => 'Mogra Bloom', 'family' => 'floral', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1299, 'price' => 799, 'stock' => 150,
                        'top' => ['Pear', 'Bergamot'], 'heart' => ['Jasmine Sambac', 'Tuberose'], 'base' => ['White Musk', 'Sandalwood'],
                        'short' => 'Fresh jasmine garlands, made modern.', 'featured' => true, 'bestseller' => true],
                    ['name' => 'Rajnigandha Nights', 'family' => 'floral', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1399, 'price' => 899, 'stock' => 80,
                        'top' => ['Mandarin', 'Neroli'], 'heart' => ['Tuberose', 'Orange Blossom'], 'base' => ['Vanilla', 'Cashmeran'],
                        'short' => 'Creamy night-blooming tuberose with soft vanilla.'],
                    ['name' => 'Gulab Silk', 'family' => 'floral', 'gender' => 'women', 'type' => 'EDP', 'ml' => 50, 'mrp' => 999, 'price' => 649, 'stock' => 120,
                        'top' => ['Lychee', 'Pink Pepper'], 'heart' => ['Damask Rose', 'Peony'], 'base' => ['Musk', 'Cedar'],
                        'short' => 'A dewy, silky rose for every day.', 'bestseller' => true],
                    ['name' => 'Champa Dawn', 'family' => 'floral', 'gender' => 'women', 'type' => 'EDT', 'ml' => 100, 'mrp' => 1199, 'price' => 749, 'stock' => 5,
                        'top' => ['Green Mandarin', 'Freesia'], 'heart' => ['Champaca', 'Ylang-Ylang'], 'base' => ['Sandalwood', 'Benzoin'],
                        'short' => 'Golden champa flowers at first light.'],
                ],
            ],
            [
                'name' => 'Fruity', 'slug' => 'women-fruity', 'code' => 'WFR',
                'description' => 'Mango, lychee and berries — bright, playful and sweet.',
                'products' => [
                    ['name' => 'Alphonso Kiss', 'family' => 'fruity', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1299, 'price' => 799, 'stock' => 100,
                        'top' => ['Mango', 'Pink Grapefruit'], 'heart' => ['Peach Blossom', 'Jasmine'], 'base' => ['Vanilla', 'Musk'],
                        'short' => 'Ripe Alphonso mango wrapped in soft musk.', 'featured' => true],
                    ['name' => 'Litchi Luxe', 'family' => 'fruity', 'gender' => 'women', 'type' => 'EDP', 'ml' => 50, 'mrp' => 899, 'price' => 599, 'stock' => 90,
                        'top' => ['Lychee', 'Raspberry'], 'heart' => ['Rose', 'Magnolia'], 'base' => ['Musk', 'Praline'],
                        'short' => 'Juicy lychee and rose with a candied finish.'],
                    ['name' => 'Berry Mehfil', 'family' => 'fruity', 'gender' => 'women', 'type' => 'EDT', 'ml' => 100, 'mrp' => 1099, 'price' => 699, 'stock' => 75,
                        'top' => ['Blackcurrant', 'Strawberry'], 'heart' => ['Violet', 'Rose'], 'base' => ['Patchouli', 'Amber'],
                        'short' => 'A party in a bottle — berries, violet and amber.'],
                ],
            ],
            [
                'name' => 'Musky & Powdery', 'slug' => 'women-musky-powdery', 'code' => 'WMP',
                'description' => 'Clean musks, iris and heliotrope — soft, skin-close elegance.',
                'products' => [
                    ['name' => 'Velvet Musk', 'family' => 'musky', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1399, 'price' => 899, 'stock' => 110,
                        'top' => ['Pear', 'Aldehydes'], 'heart' => ['Iris', 'Orris'], 'base' => ['White Musk', 'Ambrette'],
                        'short' => 'Your skin, but softer — a clean velvet musk.', 'bestseller' => true],
                    ['name' => 'Kasturi Cloud', 'family' => 'musky', 'gender' => 'women', 'type' => 'EDP', 'ml' => 50, 'mrp' => 999, 'price' => 649, 'stock' => 65,
                        'top' => ['Bergamot', 'Pink Pepper'], 'heart' => ['Heliotrope', 'Rose'], 'base' => ['Musk', 'Cashmere Wood'],
                        'short' => 'Powdery heliotrope floating on warm musk.'],
                    ['name' => 'Iris Whisper', 'family' => 'musky', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1599, 'price' => 999, 'stock' => 45,
                        'top' => ['Mandarin', 'Carrot Seed'], 'heart' => ['Iris', 'Violet'], 'base' => ['Suede', 'Vanilla'],
                        'short' => 'Cool iris and suede — understated luxury.'],
                ],
            ],
            [
                'name' => 'Gourmand', 'slug' => 'women-gourmand', 'code' => 'WGM',
                'description' => 'Vanilla, chai, caramel and cocoa — warm, edible and cosy.',
                'products' => [
                    ['name' => 'Vanilla Chai', 'family' => 'gourmand', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1399, 'price' => 849, 'stock' => 130,
                        'top' => ['Cardamom', 'Ginger'], 'heart' => ['Black Tea', 'Cinnamon'], 'base' => ['Vanilla', 'Tonka Bean'],
                        'short' => 'Masala chai and vanilla on a rainy evening.', 'featured' => true, 'bestseller' => true],
                    ['name' => 'Caramel Kulfi', 'family' => 'gourmand', 'gender' => 'women', 'type' => 'EDP', 'ml' => 50, 'mrp' => 999, 'price' => 649, 'stock' => 9,
                        'top' => ['Pistachio', 'Saffron'], 'heart' => ['Milk Accord', 'Caramel'], 'base' => ['Vanilla', 'Sandalwood'],
                        'short' => 'Pistachio, saffron and creamy caramel.'],
                    ['name' => 'Cocoa Rose', 'family' => 'gourmand', 'gender' => 'women', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1499, 'price' => 949, 'stock' => 60,
                        'top' => ['Raspberry', 'Bergamot'], 'heart' => ['Rose', 'Cocoa'], 'base' => ['Patchouli', 'Praline'],
                        'short' => 'Dark cocoa meets a velvety red rose.'],
                ],
            ],
        ],
    ],

    [
        'name' => 'Unisex',
        'slug' => 'unisex',
        'description' => 'Shareable scents that sit beautifully on everyone.',
        'children' => [
            [
                'name' => 'Citrus', 'slug' => 'unisex-citrus', 'code' => 'UCT',
                'description' => 'Nimbu, kinnow and bergamot — sparkling and uplifting.',
                'products' => [
                    ['name' => 'Nimbu Spritz', 'family' => 'citrus', 'gender' => 'unisex', 'type' => 'EDT', 'ml' => 100, 'mrp' => 999, 'price' => 599, 'stock' => 160,
                        'top' => ['Indian Lemon', 'Lime'], 'heart' => ['Neroli', 'Basil'], 'base' => ['Vetiver', 'Musk'],
                        'short' => 'Fresh-cut nimbu with a green basil twist.', 'bestseller' => true],
                    ['name' => 'Bergamot Sunrise', 'family' => 'citrus', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1299, 'price' => 799, 'stock' => 90,
                        'top' => ['Bergamot', 'Mandarin'], 'heart' => ['Petitgrain', 'Orange Blossom'], 'base' => ['Cedar', 'Amber'],
                        'short' => 'Golden citrus that lasts past lunch.'],
                    ['name' => 'Kinnow Zest', 'family' => 'citrus', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 50, 'mrp' => 849, 'price' => 549, 'stock' => 70,
                        'top' => ['Kinnow', 'Pink Grapefruit'], 'heart' => ['Ginger', 'Green Tea'], 'base' => ['White Musk', 'Ambrette'],
                        'short' => 'Punjab kinnow, ginger and clean musk.'],
                ],
            ],
            [
                'name' => 'Oud & Amber', 'slug' => 'unisex-oud-amber', 'code' => 'UOA',
                'description' => 'Resins, amber and oud — warm, deep and long-lasting.',
                'products' => [
                    ['name' => 'Amber Dusk', 'family' => 'oriental', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1699, 'price' => 1099, 'stock' => 75,
                        'top' => ['Saffron', 'Bergamot'], 'heart' => ['Amber', 'Labdanum'], 'base' => ['Vanilla', 'Benzoin'],
                        'short' => 'Glowing amber for the golden hour.', 'featured' => true],
                    ['name' => 'Desert Oud', 'family' => 'oud', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1999, 'price' => 1299, 'stock' => 50,
                        'top' => ['Pink Pepper', 'Cardamom'], 'heart' => ['Oud', 'Rose'], 'base' => ['Leather', 'Ambergris'],
                        'short' => 'Dry, smoky oud inspired by Rajasthan nights.'],
                    ['name' => 'Resin & Smoke', 'family' => 'oriental', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 50, 'mrp' => 1199, 'price' => 799, 'stock' => 7,
                        'top' => ['Elemi', 'Black Pepper'], 'heart' => ['Frankincense', 'Myrrh'], 'base' => ['Labdanum', 'Cedar'],
                        'short' => 'Temple incense and sacred resins.'],
                ],
            ],
            [
                'name' => 'Green & Herbal', 'slug' => 'unisex-green-herbal', 'code' => 'UGH',
                'description' => 'Tulsi, tea leaves and first-rain earth — calm and natural.',
                'products' => [
                    ['name' => 'Tulsi Garden', 'family' => 'green', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1199, 'price' => 749, 'stock' => 85,
                        'top' => ['Holy Basil', 'Lime'], 'heart' => ['Mint', 'Galbanum'], 'base' => ['Vetiver', 'Oakmoss'],
                        'short' => 'A courtyard of tulsi and fresh mint.'],
                    ['name' => 'Monsoon Petrichor', 'family' => 'green', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 100, 'mrp' => 1399, 'price' => 899, 'stock' => 95,
                        'top' => ['Rain Accord', 'Green Leaves'], 'heart' => ['Earth Accord', 'Violet Leaf'], 'base' => ['Vetiver', 'Musk'],
                        'short' => 'The smell of the first monsoon shower.', 'featured' => true],
                    ['name' => 'Green Tea Vetiver', 'family' => 'green', 'gender' => 'unisex', 'type' => 'EDT', 'ml' => 100, 'mrp' => 1099, 'price' => 699, 'stock' => 70,
                        'top' => ['Green Tea', 'Bergamot'], 'heart' => ['Jasmine', 'Mate'], 'base' => ['Vetiver', 'Cedar'],
                        'short' => 'Darjeeling tea leaves and cool vetiver.'],
                ],
            ],
        ],
    ],

    [
        'name' => 'Attars',
        'slug' => 'attars',
        'description' => 'Concentrated, alcohol-free perfume oils in the classic Indian tradition.',
        'children' => [
            [
                'name' => 'Classic Attars', 'slug' => 'attars-classic', 'code' => 'ACL',
                'description' => 'Mitti, shamama and hina — heritage blends from Kannauj.',
                'products' => [
                    ['name' => 'Mitti Attar', 'family' => 'green', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 12, 'mrp' => 899, 'price' => 699, 'stock' => 60,
                        'top' => ['Baked Earth'], 'heart' => ['Vetiver'], 'base' => ['Sandalwood'],
                        'short' => 'The first-rain scent of baked clay.'],
                    ['name' => 'Shamama Attar', 'family' => 'spicy', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 12, 'mrp' => 1299, 'price' => 999, 'stock' => 35,
                        'top' => ['Saffron', 'Clove'], 'heart' => ['Herbs', 'Spices'], 'base' => ['Oud', 'Amber'],
                        'short' => 'A deep winter attar of herbs, spice and oud.'],
                    ['name' => 'Hina Attar', 'family' => 'oriental', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 12, 'mrp' => 999, 'price' => 749, 'stock' => 40,
                        'top' => ['Henna', 'Cinnamon'], 'heart' => ['Rose', 'Spices'], 'base' => ['Amber', 'Musk'],
                        'short' => 'Warm henna and spice — a festive classic.'],
                ],
            ],
            [
                'name' => 'Floral Attars', 'slug' => 'attars-floral', 'code' => 'AFL',
                'description' => 'Single-flower oils: gulab, mogra and kewda.',
                'products' => [
                    ['name' => 'Gulab Attar', 'family' => 'floral', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 12, 'mrp' => 799, 'price' => 599, 'stock' => 80,
                        'top' => ['Rose Petals'], 'heart' => ['Damask Rose'], 'base' => ['Sandalwood'],
                        'short' => 'Pure rose on a sandalwood base.'],
                    ['name' => 'Mogra Attar', 'family' => 'floral', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 12, 'mrp' => 799, 'price' => 599, 'stock' => 75,
                        'top' => ['Green Notes'], 'heart' => ['Jasmine Sambac'], 'base' => ['Sandalwood'],
                        'short' => 'Night-fresh mogra, bottled.', 'bestseller' => true],
                    ['name' => 'Kewda Attar', 'family' => 'floral', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 12, 'mrp' => 699, 'price' => 499, 'stock' => 4,
                        'top' => ['Kewda'], 'heart' => ['Pandan Flower'], 'base' => ['Musk'],
                        'short' => 'Sweet, green screwpine flower.'],
                ],
            ],
        ],
    ],

    [
        'name' => 'Body Mists & Deos',
        'slug' => 'body-mists-deos',
        'description' => 'Light, everyday freshness — from morning to gym to night out.',
        'children' => [
            [
                'name' => 'Body Mists', 'slug' => 'body-mists', 'code' => 'BMS',
                'description' => 'Sheer, layerable mists you can spray all day.',
                'products' => [
                    ['name' => 'Peach Petal Mist', 'family' => 'fruity', 'gender' => 'women', 'type' => 'Body Mist', 'ml' => 150, 'mrp' => 449, 'price' => 349, 'stock' => 140,
                        'top' => ['Peach'], 'heart' => ['Peony'], 'base' => ['Musk'],
                        'short' => 'Soft peach and peony — light and pretty.'],
                    ['name' => 'Coconut Lagoon Mist', 'family' => 'gourmand', 'gender' => 'women', 'type' => 'Body Mist', 'ml' => 150, 'mrp' => 449, 'price' => 349, 'stock' => 120,
                        'top' => ['Coconut Water'], 'heart' => ['Frangipani'], 'base' => ['Vanilla'],
                        'short' => 'A Goa beach day in a mist.'],
                    ['name' => 'Lavender Haze Mist', 'family' => 'fresh', 'gender' => 'unisex', 'type' => 'Body Mist', 'ml' => 150, 'mrp' => 449, 'price' => 349, 'stock' => 100,
                        'top' => ['Lavender'], 'heart' => ['Chamomile'], 'base' => ['Musk'],
                        'short' => 'Calming lavender for slow evenings.'],
                ],
            ],
            [
                'name' => 'Deodorants', 'slug' => 'deodorants', 'code' => 'DEO',
                'description' => 'Our signature scents in no-gas body sprays.',
                'products' => [
                    ['name' => 'Teakwood Noir Deo', 'family' => 'woody', 'gender' => 'men', 'type' => 'Deodorant', 'ml' => 150, 'mrp' => 299, 'price' => 249, 'stock' => 200,
                        'top' => ['Bergamot'], 'heart' => ['Teakwood'], 'base' => ['Vetiver'],
                        'short' => 'Our best-selling woody scent, as a body spray.'],
                    ['name' => 'Konkan Tide Deo', 'family' => 'aquatic', 'gender' => 'men', 'type' => 'Deodorant', 'ml' => 150, 'mrp' => 299, 'price' => 249, 'stock' => 180,
                        'top' => ['Sea Salt'], 'heart' => ['Marine Accord'], 'base' => ['Driftwood'],
                        'short' => 'Cool coastal freshness for the gym bag.'],
                    ['name' => 'Mogra Bloom Deo', 'family' => 'floral', 'gender' => 'women', 'type' => 'Deodorant', 'ml' => 150, 'mrp' => 299, 'price' => 249, 'stock' => 160,
                        'top' => ['Pear'], 'heart' => ['Jasmine Sambac'], 'base' => ['White Musk'],
                        'short' => 'Fresh mogra all day long.'],
                ],
            ],
        ],
    ],

    [
        'name' => 'Gift Sets',
        'slug' => 'gift-sets',
        'description' => 'Ready-to-gift boxes and discovery kits for every occasion.',
        'children' => [
            [
                'name' => 'Discovery Kits', 'slug' => 'gift-discovery-kits', 'code' => 'GDK',
                'description' => 'Travel-size sets to find your signature scent.',
                'products' => [
                    ['name' => "Men's Discovery Kit (4 x 20 ml)", 'family' => 'woody', 'gender' => 'men', 'type' => 'EDP', 'ml' => 80, 'mrp' => 1599, 'price' => 999, 'stock' => 90,
                        'top' => ['Bergamot', 'Sea Salt'], 'heart' => ['Teakwood', 'Leather'], 'base' => ['Oud', 'Amber'],
                        'short' => 'Teakwood Noir, Konkan Tide, Saffron Leather and Royal Oud Durbar.', 'featured' => true, 'bestseller' => true],
                    ['name' => "Women's Discovery Kit (4 x 20 ml)", 'family' => 'floral', 'gender' => 'women', 'type' => 'EDP', 'ml' => 80, 'mrp' => 1599, 'price' => 999, 'stock' => 85,
                        'top' => ['Pear', 'Mango'], 'heart' => ['Jasmine Sambac', 'Black Tea'], 'base' => ['Vanilla', 'White Musk'],
                        'short' => 'Mogra Bloom, Alphonso Kiss, Velvet Musk and Vanilla Chai.', 'bestseller' => true],
                    ['name' => 'Attar Sampler (6 x 3 ml)', 'family' => 'oriental', 'gender' => 'unisex', 'type' => 'Attar', 'ml' => 18, 'mrp' => 1199, 'price' => 849, 'stock' => 50,
                        'top' => ['Rose Petals', 'Saffron'], 'heart' => ['Jasmine Sambac', 'Henna'], 'base' => ['Sandalwood', 'Oud'],
                        'short' => 'Six of our attars in roll-on minis.'],
                ],
            ],
            [
                'name' => 'Couple Combos', 'slug' => 'gift-couple-combos', 'code' => 'GCC',
                'description' => 'Paired scents for two — anniversaries, birthdays, just because.',
                'products' => [
                    ['name' => 'His & Hers Duo (2 x 100 ml)', 'family' => 'woody', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 200, 'mrp' => 2599, 'price' => 1599, 'stock' => 45,
                        'top' => ['Bergamot', 'Pear'], 'heart' => ['Teakwood', 'Jasmine Sambac'], 'base' => ['Vetiver', 'White Musk'],
                        'short' => 'Teakwood Noir for him, Mogra Bloom for her.', 'featured' => true],
                    ['name' => 'Monsoon Duo (100 ml + 50 ml)', 'family' => 'green', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 150, 'mrp' => 2299, 'price' => 1399, 'stock' => 30,
                        'top' => ['Rain Accord', 'Lemon'], 'heart' => ['Earth Accord', 'Vetiver'], 'base' => ['Patchouli', 'Musk'],
                        'short' => 'Monsoon Petrichor and Vetiver Monsoon together.'],
                ],
            ],
            [
                'name' => 'Festive Gift Boxes', 'slug' => 'gift-festive', 'code' => 'GFB',
                'description' => 'Diwali, weddings and Rakhi — beautifully boxed.',
                'products' => [
                    ['name' => 'Diwali Diya Gift Box (3 x 50 ml)', 'family' => 'oriental', 'gender' => 'unisex', 'type' => 'EDP', 'ml' => 150, 'mrp' => 2499, 'price' => 1499, 'stock' => 40,
                        'top' => ['Saffron', 'Cardamom'], 'heart' => ['Amber', 'Rose'], 'base' => ['Vanilla', 'Oud'],
                        'short' => 'Three warm festive scents in a keepsake box.', 'featured' => true],
                    ['name' => 'Shaadi Trousseau Set (4 x 50 ml)', 'family' => 'floral', 'gender' => 'women', 'type' => 'EDP', 'ml' => 200, 'mrp' => 2999, 'price' => 1899, 'stock' => 25,
                        'top' => ['Lychee', 'Neroli'], 'heart' => ['Damask Rose', 'Tuberose'], 'base' => ['Sandalwood', 'Vanilla'],
                        'short' => 'Four bridal florals for the big week.'],
                    ['name' => 'Rakhi Gift Set (Perfume + Deo)', 'family' => 'woody', 'gender' => 'men', 'type' => 'EDP', 'ml' => 250, 'mrp' => 1699, 'price' => 1099, 'stock' => 3,
                        'top' => ['Bergamot', 'Black Pepper'], 'heart' => ['Teakwood', 'Cardamom'], 'base' => ['Vetiver', 'Amber'],
                        'short' => 'Teakwood Noir 100 ml with its matching deo.'],
                ],
            ],
        ],
    ],
];
