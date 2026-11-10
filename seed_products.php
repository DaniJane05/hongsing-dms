<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$db = Database::getConnection();

/*
|--------------------------------------------------------------------------
| Helper: Generate SVG Product Image
|--------------------------------------------------------------------------
*/

function generateProductSvg(
    string $code,
    string $name,
    string $category,
    string $accent = '#2563eb'
): string {

    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeCategory = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');

    /*
     * Different illustration depending on product category.
     */
    $illustration = '';

    switch ($category) {

        case 'container':
            $illustration = '
                <g transform="translate(245,145)">
                    <rect x="25" y="40" width="190" height="110"
                          rx="18" fill="#e5e7eb"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <rect x="40" y="20" width="160" height="35"
                          rx="12" fill="#f8fafc"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <rect x="55" y="70" width="130" height="45"
                          rx="8" fill="#ffffff"
                          opacity="0.85"/>
                    <line x1="55" y1="125" x2="185" y2="125"
                          stroke="' . $accent . '" stroke-width="4"/>
                </g>
            ';
            break;

        case 'preform':
            $illustration = '
                <g transform="translate(300,120)">
                    <path d="M75 20 H145 V48
                             C145 58 155 65 165 72
                             L165 190
                             H55
                             L55 72
                             C65 65 75 58 75 48 Z"
                          fill="#dbeafe"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <rect x="75" y="10" width="70" height="22"
                          rx="4" fill="#bfdbfe"
                          stroke="' . $accent . '" stroke-width="5"/>
                    <line x1="82" y1="40" x2="138" y2="40"
                          stroke="' . $accent . '" stroke-width="3"/>
                </g>
            ';
            break;

        case 'crate':
            $illustration = '
                <g transform="translate(245,145)">
                    <rect x="20" y="25" width="210" height="150"
                          rx="8" fill="#dbeafe"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <rect x="40" y="45" width="170" height="110"
                          fill="none"
                          stroke="' . $accent . '" stroke-width="5"/>
                    <line x1="40" y1="45" x2="210" y2="155"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="210" y1="45" x2="40" y2="155"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="125" y1="45" x2="125" y2="155"
                          stroke="' . $accent . '" stroke-width="4"/>
                </g>
            ';
            break;

        case 'bubble':
            $illustration = '
                <g transform="translate(230,130)">
                    <rect x="35" y="45" width="250" height="130"
                          rx="10" fill="#f8fafc"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <circle cx="70" cy="80" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="115" cy="80" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="160" cy="80" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="205" cy="80" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="250" cy="80" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>

                    <circle cx="70" cy="130" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="115" cy="130" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="160" cy="130" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="205" cy="130" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                    <circle cx="250" cy="130" r="18"
                            fill="#dbeafe" stroke="' . $accent . '" stroke-width="3"/>
                </g>
            ';
            break;

        case 'drum':
            $illustration = '
                <g transform="translate(275,105)">
                    <ellipse cx="100" cy="35" rx="85" ry="25"
                             fill="#bfdbfe"
                             stroke="' . $accent . '" stroke-width="6"/>
                    <rect x="15" y="35" width="170" height="170"
                          fill="#2563eb"
                          opacity="0.75"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <ellipse cx="100" cy="205" rx="85" ry="25"
                             fill="#1d4ed8"
                             stroke="' . $accent . '" stroke-width="6"/>
                    <line x1="30" y1="80" x2="170" y2="80"
                          stroke="#ffffff" stroke-width="5"
                          opacity="0.7"/>
                    <line x1="30" y1="160" x2="170" y2="160"
                          stroke="#ffffff" stroke-width="5"
                          opacity="0.7"/>
                </g>
            ';
            break;

        case 'roll':
            $illustration = '
                <g transform="translate(250,115)">
                    <ellipse cx="120" cy="55" rx="75" ry="45"
                             fill="#e0f2fe"
                             stroke="' . $accent . '" stroke-width="6"/>
                    <ellipse cx="120" cy="55" rx="25" ry="16"
                             fill="#ffffff"
                             stroke="' . $accent . '" stroke-width="5"/>
                    <path d="M45 55
                             C45 110 60 170 120 190
                             C180 170 195 110 195 55"
                          fill="#bfdbfe"
                          opacity="0.75"
                          stroke="' . $accent . '" stroke-width="6"/>
                </g>
            ';
            break;

        case 'cutlery':
            $illustration = '
                <g transform="translate(275,105)">
                    <path d="M45 25 V100
                             C45 120 65 120 65 100
                             V25"
                          fill="none"
                          stroke="' . $accent . '" stroke-width="8"/>
                    <line x1="55" y1="100" x2="55" y2="205"
                          stroke="' . $accent . '" stroke-width="8"/>

                    <path d="M135 25 V90
                             C135 110 160 110 160 90
                             V25"
                          fill="none"
                          stroke="' . $accent . '" stroke-width="8"/>
                    <line x1="147" y1="90" x2="147" y2="205"
                          stroke="' . $accent . '" stroke-width="8"/>
                </g>
            ';
            break;

        case 'bag':
            $illustration = '
                <g transform="translate(270,100)">
                    <path d="M35 65
                             L60 190
                             H190
                             L215 65
                             Z"
                          fill="#f8fafc"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <path d="M70 70
                             C70 10 180 10 180 70"
                          fill="none"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <line x1="80" y1="105" x2="170" y2="105"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="80" y1="135" x2="170" y2="135"
                          stroke="' . $accent . '" stroke-width="4"/>
                </g>
            ';
            break;

        case 'bottle':
            $illustration = '
                <g transform="translate(300,95)">
                    <rect x="65" y="25" width="70" height="35"
                          rx="5"
                          fill="#bfdbfe"
                          stroke="' . $accent . '" stroke-width="5"/>
                    <path d="M60 60
                             H140
                             V85
                             C140 95 155 105 160 120
                             V205
                             H40
                             V120
                             C45 105 60 95 60 85 Z"
                          fill="#dbeafe"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <rect x="60" y="125" width="80" height="45"
                          rx="5"
                          fill="#ffffff"
                          opacity="0.8"/>
                </g>
            ';
            break;

        case 'sheet':
            $illustration = '
                <g transform="translate(220,120)">
                    <polygon points="55,50 275,20 275,155 55,185"
                             fill="#dbeafe"
                             stroke="' . $accent . '" stroke-width="7"/>
                    <line x1="90" y1="60" x2="90" y2="180"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="130" y1="55" x2="130" y2="175"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="170" y1="48" x2="170" y2="170"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="210" y1="42" x2="210" y2="165"
                          stroke="' . $accent . '" stroke-width="4"/>
                </g>
            ';
            break;

        case 'bucket':
            $illustration = '
                <g transform="translate(275,105)">
                    <path d="M30 65
                             L55 195
                             H185
                             L210 65 Z"
                          fill="#dbeafe"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <path d="M55 65
                             C55 5 185 5 185 65"
                          fill="none"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <ellipse cx="120" cy="65" rx="90" ry="20"
                             fill="#bfdbfe"
                             stroke="' . $accent . '" stroke-width="6"/>
                </g>
            ';
            break;

        case 'hanger':
            $illustration = '
                <g transform="translate(260,95)">
                    <path d="M120 45
                             C120 5 170 5 170 40
                             C170 65 145 65 125 80
                             L35 145
                             H245
                             L155 80"
                          fill="none"
                          stroke="' . $accent . '" stroke-width="8"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
                </g>
            ';
            break;

        case 'cup':
            $illustration = '
                <g transform="translate(275,105)">
                    <path d="M40 40
                             H200
                             L180 195
                             H60 Z"
                          fill="#dbeafe"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <ellipse cx="120" cy="40" rx="80" ry="20"
                             fill="#bfdbfe"
                             stroke="' . $accent . '" stroke-width="6"/>
                </g>
            ';
            break;

        case 'sack':
            $illustration = '
                <g transform="translate(260,100)">
                    <path d="M55 35
                             L185 35
                             L210 205
                             H30 Z"
                          fill="#e5e7eb"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <line x1="55" y1="55" x2="185" y2="55"
                          stroke="' . $accent . '" stroke-width="6"/>
                    <line x1="60" y1="90" x2="180" y2="90"
                          stroke="' . $accent . '" stroke-width="4"/>
                    <line x1="55" y1="125" x2="185" y2="125"
                          stroke="' . $accent . '" stroke-width="4"/>
                </g>
            ';
            break;

        case 'pill':
            $illustration = '
                <g transform="translate(295,100)">
                    <rect x="45" y="55" width="130" height="150"
                          rx="18"
                          fill="#f8fafc"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <rect x="45" y="55" width="130" height="45"
                          rx="18"
                          fill="#bfdbfe"
                          stroke="' . $accent . '" stroke-width="7"/>
                    <line x1="45" y1="100" x2="175" y2="100"
                          stroke="' . $accent . '" stroke-width="5"/>
                </g>
            ';
            break;

        default:
            $illustration = '
                <g transform="translate(275,110)">
                    <rect x="30" y="40" width="190" height="140"
                          rx="15"
                          fill="#dbeafe"
                          stroke="' . $accent . '" stroke-width="7"/>
                </g>
            ';
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg"
     width="600"
     height="400"
     viewBox="0 0 600 400">

    <rect width="600" height="400" fill="#f8fafc"/>

    <rect x="20" y="20"
          width="560"
          height="360"
          rx="24"
          fill="#ffffff"
          stroke="#e5e7eb"
          stroke-width="3"/>

    {$illustration}

    <text x="300"
          y="285"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="25"
          font-weight="700"
          fill="#111827">
        {$safeCode}
    </text>

    <text x="300"
          y="320"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="17"
          font-weight="600"
          fill="#374151">
        {$safeCategory}
    </text>

    <text x="300"
          y="350"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="13"
          fill="#6b7280">
        Hong Sing LKRR Commercial
    </text>

</svg>
SVG;
}


/*
|--------------------------------------------------------------------------
| Product Data
|--------------------------------------------------------------------------
*/

$products = [

    [
        'code' => 'PLA-006',
        'name' => 'PP Microwavable Container 500ml (Case of 250)',
        'description' => 'Heat-resistant clear food containers with snap-on lids for meal prep and food service.',
        'price' => 1100.00,
        'weight' => 7.20,
        'stock' => 450,
        'category' => 'container',
        'accent' => '#2563eb'
    ],

    [
        'code' => 'PLA-007',
        'name' => 'PET Preforms 28mm Neck (Box of 1000)',
        'description' => 'Raw bottle preforms used for stretch blow molding beverage containers.',
        'price' => 3100.00,
        'weight' => 22.00,
        'stock' => 120,
        'category' => 'preform',
        'accent' => '#0891b2'
    ],

    [
        'code' => 'PLA-008',
        'name' => 'Stackable Plastic Storage Crate Large',
        'description' => 'Rigid ventilated polypropylene crate for agriculture harvesting and logistics.',
        'price' => 450.00,
        'weight' => 2.80,
        'stock' => 250,
        'category' => 'crate',
        'accent' => '#16a34a'
    ],

    [
        'code' => 'PLA-009',
        'name' => 'Bubble Wrap Roll 40" x 100m',
        'description' => 'Protective cushioning air bubble film roll for shipping and fragile items packaging.',
        'price' => 650.00,
        'weight' => 3.50,
        'stock' => 180,
        'category' => 'bubble',
        'accent' => '#7c3aed'
    ],

    [
        'code' => 'PLA-010',
        'name' => 'HDPE Chemical Drum 200L Lid Type',
        'description' => 'High-density blue plastic drum designed for bulk liquid chemicals and oil storage.',
        'price' => 1850.00,
        'weight' => 9.50,
        'stock' => 60,
        'category' => 'drum',
        'accent' => '#1d4ed8'
    ],

    [
        'code' => 'PLA-011',
        'name' => 'LLDPE Industrial Stretch Film Roll 500mm',
        'description' => 'High-clarity pallet wrapping stretch film for securing cargo loads during transport.',
        'price' => 380.00,
        'weight' => 2.20,
        'stock' => 600,
        'category' => 'roll',
        'accent' => '#0284c7'
    ],

    [
        'code' => 'PLA-012',
        'name' => 'Plastic Cutlery Sets - Spoon & Fork (Box of 500)',
        'description' => 'Bulk polystyrene (PS) disposable spoon and fork sets for fast-food operations.',
        'price' => 480.00,
        'weight' => 3.00,
        'stock' => 400,
        'category' => 'cutlery',
        'accent' => '#64748b'
    ],

    [
        'code' => 'PLA-013',
        'name' => 'Hinged Clamshell Container 8x8 (Pack of 150)',
        'description' => 'Eco-friendly clear PET clamshell boxes for bakery products and takeout meals.',
        'price' => 890.00,
        'weight' => 5.50,
        'stock' => 350,
        'category' => 'container',
        'accent' => '#059669'
    ],

    [
        'code' => 'PLA-014',
        'name' => 'Plastic Shopping T-Shirt Bags (Carton of 2000)',
        'description' => 'Durable HDPE grocery shopping carry bags with handles for retail outlets.',
        'price' => 620.00,
        'weight' => 6.80,
        'stock' => 800,
        'category' => 'bag',
        'accent' => '#f59e0b'
    ],

    [
        'code' => 'PLA-015',
        'name' => 'Heavy Duty Garbage Bags 36x48 (Pack of 100)',
        'description' => 'Thick black recycled LDPE trash liners for institutional and commercial waste disposal.',
        'price' => 750.00,
        'weight' => 9.00,
        'stock' => 300,
        'category' => 'bag',
        'accent' => '#374151'
    ],

    [
        'code' => 'PLA-016',
        'name' => 'Plastic Jerry Can 20L with Cap',
        'description' => 'Leak-proof HDPE container for water, fuel, or liquid chemical transport.',
        'price' => 220.00,
        'weight' => 1.10,
        'stock' => 500,
        'category' => 'bottle',
        'accent' => '#2563eb'
    ],

    [
        'code' => 'PLA-017',
        'name' => 'Clear PVC Shrink Film Roll 15-inch',
        'description' => 'High-clarity heat shrink film for secondary packaging and product bundling.',
        'price' => 820.00,
        'weight' => 4.80,
        'stock' => 150,
        'category' => 'roll',
        'accent' => '#9333ea'
    ],

    [
        'code' => 'PLA-018',
        'name' => 'PP Woven Sacks 50kg Capacity (Bundle of 100)',
        'description' => 'Heavy-duty polypropylene woven bags for grain, fertilizer, and agricultural feed storage.',
        'price' => 900.00,
        'weight' => 7.00,
        'stock' => 1000,
        'category' => 'sack',
        'accent' => '#ca8a04'
    ],

    [
        'code' => 'PLA-019',
        'name' => 'PET Water Bottles 500ml with Caps (Box of 200)',
        'description' => 'Food-safe clear PET plastic bottles complete with screw-top tamper-evident caps.',
        'price' => 580.00,
        'weight' => 3.80,
        'stock' => 600,
        'category' => 'bottle',
        'accent' => '#0284c7'
    ],

    [
        'code' => 'PLA-020',
        'name' => 'Plastic Hangers Heavy Duty (Box of 100)',
        'description' => 'Molded plastic clothes hangers with notch hooks for garment retail and industrial laundries.',
        'price' => 650.00,
        'weight' => 5.20,
        'stock' => 220,
        'category' => 'hanger',
        'accent' => '#db2777'
    ],

    [
        'code' => 'PLA-021',
        'name' => 'Correx Fluted Plastic Sheets 4x8ft (Pack of 10)',
        'description' => 'Lightweight polypropylene twinwall fluted sheets for signage, construction, and packing.',
        'price' => 2100.00,
        'weight' => 12.00,
        'stock' => 90,
        'category' => 'sheet',
        'accent' => '#2563eb'
    ],

    [
        'code' => 'PLA-022',
        'name' => 'Plastic Water Bucket 20L Heavy Duty',
        'description' => 'Rigid molded plastic utility bucket with reinforced metal bail handle.',
        'price' => 160.00,
        'weight' => 0.95,
        'stock' => 400,
        'category' => 'bucket',
        'accent' => '#0891b2'
    ],

    [
        'code' => 'PLA-023',
        'name' => 'Clear Plastic Drinking Cups 16oz (Carton of 1000)',
        'description' => 'Disposable cold drink PET cups for beverages, milk tea, and food stalls.',
        'price' => 1350.00,
        'weight' => 8.00,
        'stock' => 300,
        'category' => 'cup',
        'accent' => '#06b6d4'
    ],

    [
        'code' => 'PLA-024',
        'name' => 'Plastic Industrial Jerry Can 5L (Case of 24)',
        'description' => 'Compact HDPE liquid storage containers equipped with tamper-evident caps.',
        'price' => 780.00,
        'weight' => 4.80,
        'stock' => 250,
        'category' => 'bottle',
        'accent' => '#1d4ed8'
    ],

    [
        'code' => 'PLA-025',
        'name' => 'HDPE Squeeze Bottles 250ml (Box of 100)',
        'description' => 'Flexible squeeze bottles designed for sauces, condiments, or industrial lubricants.',
        'price' => 550.00,
        'weight' => 2.50,
        'stock' => 180,
        'category' => 'bottle',
        'accent' => '#16a34a'
    ],

    [
        'code' => 'PLA-026',
        'name' => 'Polycarbonate Sheet Clear 2mm 4x8ft',
        'description' => 'High-impact resistant transparent PC sheet for safety guards and roofing panels.',
        'price' => 3200.00,
        'weight' => 7.50,
        'stock' => 50,
        'category' => 'sheet',
        'accent' => '#0f766e'
    ],

    [
        'code' => 'PLA-027',
        'name' => 'Plastic Zip Lock Bags 8x12 (Pack of 1000)',
        'description' => 'Reclosable re-sealable polyethylene bags for electronic components and small parts packaging.',
        'price' => 880.00,
        'weight' => 5.40,
        'stock' => 400,
        'category' => 'bag',
        'accent' => '#7c3aed'
    ],

    [
        'code' => 'PLA-028',
        'name' => 'Polypropylene Strapping Band Roll 15mm',
        'description' => 'High-tensile strength PP strap roll for manual and machine box banding/bundling.',
        'price' => 1150.00,
        'weight' => 9.00,
        'stock' => 140,
        'category' => 'roll',
        'accent' => '#ea580c'
    ],

    [
        'code' => 'PLA-029',
        'name' => 'Plastic Pill/Medicine Bottles 100cc (Box of 200)',
        'description' => 'Pharmaceutical-grade HDPE amber/white bottles with child-resistant caps.',
        'price' => 690.00,
        'weight' => 3.10,
        'stock' => 320,
        'category' => 'pill',
        'accent' => '#dc2626'
    ]

];


/*
|--------------------------------------------------------------------------
| Make sure product_image can store image data
|--------------------------------------------------------------------------
*/

try {
    $db->exec("
        ALTER TABLE `product`
        MODIFY `product_image` MEDIUMBLOB NULL
    ");
} catch (PDOException $e) {
    /*
     * If the column is already MEDIUMBLOB or the database
     * doesn't allow this change, continue.
     */
}


/*
|--------------------------------------------------------------------------
| Delete existing PLA-006 to PLA-029
|--------------------------------------------------------------------------
*/

$delete = $db->prepare("
    DELETE FROM `product`
    WHERE `product_code` = ?
");


/*
|--------------------------------------------------------------------------
| Insert Product
|--------------------------------------------------------------------------
*/

$insert = $db->prepare("
    INSERT INTO `product`
    (
        `product_code`,
        `product_name`,
        `description`,
        `price`,
        `weight_kg`,
        `stock_quantity`,
        `status`,
        `product_image`
    )
    VALUES
    (
        :product_code,
        :product_name,
        :description,
        :price,
        :weight_kg,
        :stock_quantity,
        :status,
        :product_image
    )
");


$db->beginTransaction();

try {

    foreach ($products as $product) {

        /*
         * Remove old version first.
         */
        $delete->execute([
            $product['code']
        ]);


        /*
         * Generate unique product image.
         */
        $svg = generateProductSvg(
            $product['code'],
            $product['name'],
            $product['category'],
            $product['accent']
        );


        /*
         * Insert product + actual SVG image data.
         */
        $insert->bindValue(
            ':product_code',
            $product['code'],
            PDO::PARAM_STR
        );

        $insert->bindValue(
            ':product_name',
            $product['name'],
            PDO::PARAM_STR
        );

        $insert->bindValue(
            ':description',
            $product['description'],
            PDO::PARAM_STR
        );

        $insert->bindValue(
            ':price',
            $product['price']
        );

        $insert->bindValue(
            ':weight_kg',
            $product['weight']
        );

        $insert->bindValue(
            ':stock_quantity',
            $product['stock'],
            PDO::PARAM_INT
        );

        $insert->bindValue(
            ':status',
            'Available',
            PDO::PARAM_STR
        );

        $insert->bindValue(
            ':product_image',
            $svg,
            PDO::PARAM_LOB
        );

        $insert->execute();
    }

    $db->commit();

    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Product Seeder</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f8fafc;
                padding: 40px;
                color: #111827;
            }

            .box {
                max-width: 700px;
                margin: 50px auto;
                background: white;
                padding: 35px;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0,0,0,.08);
            }

            h1 {
                margin-top: 0;
                color: #166534;
            }

            .success {
                background: #dcfce7;
                color: #166534;
                padding: 15px;
                border-radius: 10px;
                margin: 20px 0;
            }

            ul {
                line-height: 1.8;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 18px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }
        </style>
    </head>

    <body>

        <div class="box">

            <h1>✓ Products Successfully Seeded</h1>

            <div class="success">
                24 products were inserted successfully.
                Each product has its own generated SVG image stored directly
                inside the MySQL database.
            </div>

            <ul>';

    foreach ($products as $product) {
        echo '<li>'
            . htmlspecialchars($product['code'])
            . ' — '
            . htmlspecialchars($product['name'])
            . '</li>';
    }

    echo '
            </ul>

            <a href="' . BASE_URL . 'admin/products.php">
                View Products
            </a>

        </div>

    </body>
    </html>';

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    echo '<h2>Product seeding failed.</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}